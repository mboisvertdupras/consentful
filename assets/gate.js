/**
 * The gate engine — built to a hashed build/assets/gate.<hash>.js, enqueued in the
 * footer. It owns the public window.consentful API, the adapter-load apply pipeline,
 * and change events. It recomputes grants from lib/ (it does NOT depend on the
 * decider's _init), so it stays correct even if loaded independently.
 */

import './banner.css';
import { initBanner } from './banner.js';
import { parseConfig } from './lib/config.js';
import {
	readCookie,
	writeCookie,
	parseConsent,
	validateConsent,
	serializeConsent,
} from './lib/cookie.js';
import { computeGrants, isTagGranted } from './lib/grants.js';
import { newConsentId, sendConsentRecord } from './lib/proof.js';
import {
	resolveJurisdictionSync,
	activeJurisdiction,
	mapRegionToJurisdiction,
} from './lib/jurisdiction.js';
import { google } from './adapters/google.js';
import { script } from './adapters/script.js';
import { gtm } from './adapters/gtm.js';

/**
 * Initialize the gate against a window/document. Returns the public API (also assigned
 * onto window.consentful).
 *
 * @param {unknown} rawConfig window.consentfulConfig.
 * @param {object}  env       { win, doc }.
 * @return {object} The public API.
 */
export function init( rawConfig, { win, doc } ) {
	const config = parseConfig( rawConfig );

	const handlers = { google, script, gtm };
	const listeners = new Set();

	// Drain any early-registered adapters the decider stub queued, then keep the
	// surface so the gate's own registerAdapter replaces the stub.
	const existing = win.consentful || {};
	const queue = Array.isArray( existing._adapterQueue ) ? existing._adapterQueue : [];

	const registerAdapter = ( name, impl ) => {
		if ( name && impl && typeof impl.apply === 'function' ) {
			handlers[ name ] = impl;
		}
	};
	for ( const [ name, impl ] of queue ) {
		registerAdapter( name, impl );
	}

	const purposeKeys = config.purposes.map( ( p ) => p.key );
	const alwaysOn = {};
	for ( const p of config.purposes ) {
		alwaysOn[ p.key ] = p.alwaysOn;
	}

	let stored = readStored();
	let gpc = win.navigator && win.navigator.globalPrivacyControl === true;

	// Sync, fail-closed jurisdiction resolution; may stay null (unresolved) for the async
	// geo fallback below. resolved/policy are mutable so a later geo resolution adapts.
	let resolvedId = resolveJurisdictionSync( config, { win, doc } );
	let resolved = activeJurisdiction( config, resolvedId );
	let policy = resolved.policy;

	let grants = recompute();

	function readStored() {
		return validateConsent(
			parseConsent( readCookie( config.cookie, doc ) ),
			{
				schemaVersion: config.schemaVersion,
				policyVersion: config.policyVersion,
				maxAgeMs: config.maxAgeMs,
			}
		);
	}

	function recompute() {
		return computeGrants( {
			purposes: config.purposes,
			policy,
			stored,
			gpc,
		} );
	}

	function applyAll() {
		for ( const tag of config.tags ) {
			// Resolve the handler by the adapter config's `handler` field, so several
			// instances (e.g. multiple custom snippets) can share one handler; fall back
			// to the adapter id. The decider already resolves Google this way.
			const adapterConfig = config.adapters[ tag.adapter ] || {};
			const handler = handlers[ adapterConfig.handler || tag.adapter ];
			if ( ! handler || typeof handler.apply !== 'function' ) {
				continue;
			}
			handler.apply( {
				tag,
				adapterConfig,
				grants,
				granted: isTagGranted( tag, grants ),
				win,
				doc,
			} );
		}
	}

	function dispatchChange() {
		const detail = { ...grants };
		if ( typeof win.CustomEvent === 'function' ) {
			doc.dispatchEvent( new win.CustomEvent( 'consentful:change', { detail } ) );
		}
		for ( const cb of listeners ) {
			try {
				cb( detail );
			} catch {
				// A listener throwing must not break the others.
			}
		}
	}

	/**
	 * Normalize a grants input against known purposes: unknown keys dropped, always-on
	 * forced true, and (under GPC) every non-essential forced false.
	 */
	function normalize( input ) {
		const obj = input && typeof input === 'object' ? input : {};
		const next = {};
		for ( const key of purposeKeys ) {
			if ( alwaysOn[ key ] ) {
				next[ key ] = true;
			} else if ( gpc ) {
				next[ key ] = false;
			} else {
				next[ key ] = Boolean( obj[ key ] );
			}
		}
		return next;
	}

	function persist( decision ) {
		const timestamp = Date.now();
		writeCookie(
			config.cookie,
			serializeConsent( {
				schemaVersion: config.schemaVersion,
				policyVersion: config.policyVersion,
				jurisdiction: resolved.id,
				grants: decision,
				timestamp,
			} ),
			{ maxAgeMs: config.maxAgeMs },
			doc
		);
		stored = { grants: decision, jurisdiction: resolved.id, timestamp };
		grants = recompute();
	}

	function setConsent( grantsInput ) {
		const decision = normalize( grantsInput );
		persist( decision );
		applyAll();
		dispatchChange();
		sendProof( decision );
		return { ...grants };
	}

	// Durable proof of consent (ADR 0002): post a pseudonymous record after each decision.
	// Fire-and-forget — only on an actual decision (never the passive initial load) and
	// only when configured; a proof failure must never break the consent pipeline.
	function sendProof( decision ) {
		if ( ! config.proof.enabled || ! config.proof.endpoint ) {
			return;
		}
		try {
			sendConsentRecord(
				config.proof.endpoint,
				{
					cid: newConsentId( win ),
					grants: decision,
					jurisdiction: resolved.id,
					policyVersion: config.policyVersion,
					schemaVersion: config.schemaVersion,
					bannerVersion: config.proof.bannerVersion,
					timestamp: Date.now(),
				},
				win
			);
		} catch {
			// A proof failure must never break the consent pipeline.
		}
	}

	function acceptAll() {
		const all = {};
		for ( const key of purposeKeys ) {
			all[ key ] = true;
		}
		return setConsent( all );
	}

	function rejectAll() {
		return setConsent( {} );
	}

	const api = {
		get() {
			return { ...grants };
		},
		hasDecision() {
			return stored !== null;
		},
		gpc() {
			return gpc;
		},
		purposes() {
			return config.purposes.map( ( p ) => ( { ...p } ) );
		},
		jurisdiction() {
			return resolved.id;
		},
		policy() {
			return { ...policy };
		},
		setConsent,
		acceptAll,
		rejectAll,
		onChange( cb ) {
			if ( typeof cb !== 'function' ) {
				return () => {};
			}
			listeners.add( cb );
			return () => listeners.delete( cb );
		},
		registerAdapter,
	};

	// Preserve any decider-stashed _init for debugging, but own the surface.
	win.consentful = Object.assign( {}, existing, api );

	// Initial pass so already-granted tags fire on load.
	applyAll();

	// The banner is compliance-critical but secondary to the gate; a thrown banner
	// error must never break the consent pipeline.
	let bannerHandle = { destroy() {} };
	try {
		bannerHandle = initBanner( win.consentful, rawConfig && rawConfig.banner, { win, doc } );
	} catch {
		// Banner failed to init — the gate still works headlessly.
	}

	// Async geo fallback (ADR 0002): the ONLY network call. Fires only for an undecided
	// visitor the sync signal couldn't place, when an endpoint is configured. GPC still
	// wins (recompute forces deny); geo only loosens defaults/variant pre-decision.
	if ( resolvedId === null && stored === null && config.geo.endpoint ) {
		try {
			fetchGeoRegion( config.geo.endpoint, win ).then( ( region ) => {
				const nextId = mapRegionToJurisdiction( region, config.geo.map );
				if ( ! nextId || nextId === resolved.id || ! config.jurisdictions[ nextId ] ) {
					return;
				}
				resolvedId = nextId;
				resolved = activeJurisdiction( config, nextId );
				policy = resolved.policy;
				grants = recompute();
				applyAll();
				dispatchChange();
				try {
					bannerHandle.destroy();
					bannerHandle = initBanner( win.consentful, rawConfig && rawConfig.banner, {
						win,
						doc,
					} );
				} catch {
					// Re-render failed — the gate still works headlessly.
				}
			} );
		} catch {
			// A fetch error must never break the gate.
		}
	}

	return win.consentful;
}

/**
 * Fetch a region code from the non-cached geo endpoint. Resolves to null on any failure
 * or when fetch is unavailable, so the caller stays on the strictest fallback.
 *
 * @param {string} endpoint Absolute endpoint URL.
 * @param {Window} win
 * @return {Promise<?string>} The region code, or null.
 */
function fetchGeoRegion( endpoint, win ) {
	if ( ! win.fetch ) {
		return Promise.resolve( null );
	}
	return win
		.fetch( endpoint, { credentials: 'omit' } )
		.then( ( r ) => ( r.ok ? r.json() : null ) )
		.then( ( d ) => ( d && typeof d.region === 'string' ? d.region : null ) )
		.catch( () => null );
}

if ( typeof window !== 'undefined' ) {
	init( window.consentfulConfig, { win: window, doc: document } );
}

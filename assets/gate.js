/**
 * The gate engine — built to a hashed build/assets/gate.<hash>.js, enqueued in the
 * footer. It owns the public window.consentful API, the adapter-load apply pipeline,
 * and change events. It recomputes grants from lib/ (it does NOT depend on the
 * decider's _init), so it stays correct even if loaded independently.
 */

import { parseConfig } from './lib/config.js';
import {
	readCookie,
	writeCookie,
	parseConsent,
	validateConsent,
	serializeConsent,
} from './lib/cookie.js';
import { computeGrants, isTagGranted } from './lib/grants.js';
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
			policy: config.policy,
			stored,
			gpc,
		} );
	}

	function applyAll() {
		for ( const tag of config.tags ) {
			const handler = handlers[ tag.adapter ];
			if ( ! handler || typeof handler.apply !== 'function' ) {
				continue;
			}
			const adapterConfig = config.adapters[ tag.adapter ] || {};
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
				jurisdiction: config.jurisdiction,
				grants: decision,
				timestamp,
			} ),
			{ maxAgeMs: config.maxAgeMs },
			doc
		);
		stored = { grants: decision, jurisdiction: config.jurisdiction, timestamp };
		grants = recompute();
	}

	function setConsent( grantsInput ) {
		const decision = normalize( grantsInput );
		persist( decision );
		applyAll();
		dispatchChange();
		return { ...grants };
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
			return config.jurisdiction;
		},
		policy() {
			return { ...config.policy };
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

	return win.consentful;
}

if ( typeof window !== 'undefined' ) {
	init( window.consentfulConfig, { win: window, doc: document } );
}

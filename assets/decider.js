/**
 * Inline <head> decider — built to build/decider.js (fixed path) and inlined by PHP so
 * it runs framework-free, before any tag. It sets the dataLayer + gtag shim, reads and
 * validates the cookie, computes initial grants (incl. GPC), emits the Google Consent
 * Mode v2 DEFAULT state ONCE, and exposes an early registerAdapter queue.
 *
 * Everything vendor-neutral flows through lib/. The only Google-specific code is the
 * default emission — see the commented block below.
 */

import { parseConfig } from './lib/config.js';
import { readCookie, parseConsent, validateConsent } from './lib/cookie.js';
import { computeGrants } from './lib/grants.js';
import { resolveJurisdictionSync, activeJurisdiction } from './lib/jurisdiction.js';
import { signalState, anyAdSignalDenied } from './adapters/google-signals.js';

/**
 * Run the decider against a window/document.
 *
 * @param {unknown} rawConfig window.consentfulConfig.
 * @param {object}  env       { win, doc }.
 * @return {object} { grants, hasDecision, gpc, jurisdiction } (also stashed on window.consentful._init).
 */
export function init( rawConfig, { win, doc } ) {
	const config = parseConfig( rawConfig );

	// dataLayer + gtag shim must exist before any consent call.
	win.dataLayer = win.dataLayer || [];
	if ( typeof win.gtag !== 'function' ) {
		win.gtag = function () {
			win.dataLayer.push( arguments );
		};
	}
	const gtag = win.gtag;

	const stored = validateConsent(
		parseConsent( readCookie( config.cookie, doc ) ),
		{
			schemaVersion: config.schemaVersion,
			policyVersion: config.policyVersion,
			maxAgeMs: config.maxAgeMs,
		}
	);

	const gpc = win.navigator && win.navigator.globalPrivacyControl === true;

	// Sync, fail-closed jurisdiction resolution (no fetch — the decider stays inline/fast).
	const jid = resolveJurisdictionSync( config, { win, doc } );
	const resolved = activeJurisdiction( config, jid );

	const grants = computeGrants( {
		purposes: config.purposes,
		policy: resolved.policy,
		stored,
		gpc,
	} );

	// --- Google-specific (Consent Mode v2 head-timing) -------------------------------
	// The `consent default` MUST be set before gtag.js loads, so it lives here in the
	// inline head decider rather than the deferred gate. Guarded to emit at most once.
	if ( ! win.__consentfulDefaultEmitted ) {
		win.__consentfulDefaultEmitted = true;
		for ( const adapterId of Object.keys( config.adapters ) ) {
			const adapter = config.adapters[ adapterId ];
			if ( ! adapter || adapter.handler !== 'google' ) {
				continue;
			}
			const purposeSignals = adapter.purposeSignals || {};
			const state = signalState( grants, purposeSignals );
			state.wait_for_update = parseInt( adapter.waitForUpdate, 10 ) || 0;
			gtag( 'consent', 'default', state );

			if ( adapter.adsDataRedaction === true && anyAdSignalDenied( state ) ) {
				gtag( 'set', 'ads_data_redaction', true );
			}
			if ( adapter.urlPassthrough === true ) {
				gtag( 'set', 'url_passthrough', true );
			}
		}
	}
	// --- end Google-specific ---------------------------------------------------------

	// Early registration surface for integrator adapters that load before the gate.
	const surface = ( win.consentful = win.consentful || {} );
	if ( typeof surface.registerAdapter !== 'function' ) {
		surface._adapterQueue = surface._adapterQueue || [];
		surface.registerAdapter = function ( name, impl ) {
			surface._adapterQueue.push( [ name, impl ] );
		};
	}

	const result = {
		grants,
		hasDecision: stored !== null,
		gpc,
		jurisdiction: resolved.id,
	};
	// Optimization only — the gate MUST be able to recompute without this.
	surface._init = result;
	return result;
}

if ( typeof window !== 'undefined' ) {
	init( window.consentfulConfig, { win: window, doc: document } );
}

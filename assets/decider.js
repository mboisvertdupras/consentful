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

	const jid = resolveJurisdictionSync( config, { win, doc } );
	const resolved = activeJurisdiction( config, jid );

	const grants = computeGrants( {
		purposes: config.purposes,
		policy: resolved.policy,
		stored,
		gpc,
	} );

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
	surface._init = result;
	return result;
}

if ( typeof window !== 'undefined' ) {
	init( window.consentfulConfig, { win: window, doc: document } );
}

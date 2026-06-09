import { readCookie } from './cookie.js';

/**
 * Map a region code to a jurisdiction id.
 *
 * @param {?string} region Region code (e.g. 'US', 'CA-QC').
 * @param {object}  map    Region-code => jurisdiction-id.
 * @return {?string} Jurisdiction id, or null when unmapped.
 */
export function mapRegionToJurisdiction( region, map ) {
	if ( typeof region !== 'string' || region === '' ) {
		return null;
	}
	const lookup = map && typeof map === 'object' ? map : {};
	const full = region.toUpperCase();
	if ( typeof lookup[ full ] === 'string' ) {
		return lookup[ full ];
	}
	const country = full.split( '-' )[ 0 ];
	return typeof lookup[ country ] === 'string' ? lookup[ country ] : null;
}

/**
 * Read a region code from the configured client/edge geo signal.
 *
 * @param {object} geo        { cookie, var } signal names.
 * @param {object} env        { win, doc }.
 * @param {Window} env.win
 * @param {Document} env.doc
 * @return {?string} Region code, or null when no signal is present.
 */
export function readGeoSignal( geo, { win, doc } ) {
	const cfg = geo && typeof geo === 'object' ? geo : {};
	if ( cfg.cookie ) {
		const fromCookie = readCookie( cfg.cookie, doc );
		if ( fromCookie ) {
			return fromCookie;
		}
	}
	if ( cfg.var && win[ cfg.var ] != null && win[ cfg.var ] !== '' ) {
		return String( win[ cfg.var ] );
	}
	return null;
}

/**
 * Resolve the active jurisdiction id synchronously from the geo signal.
 *
 * @param {object} config Parsed config ({ geo, jurisdictions }).
 * @param {object} env    { win, doc }.
 * @return {?string} Jurisdiction id, or null when unresolved.
 */
export function resolveJurisdictionSync( config, env ) {
	const region = readGeoSignal( config.geo, env );
	const id = mapRegionToJurisdiction( region, config.geo && config.geo.map );
	if ( id && config.jurisdictions && config.jurisdictions[ id ] ) {
		return id;
	}
	return null;
}

/**
 * Resolve a jurisdiction record from an id, falling back to default then strictest `*`.
 *
 * @param {object}  config Parsed config ({ jurisdictions, defaultJurisdiction }).
 * @param {?string} id     Candidate jurisdiction id.
 * @return {object} A jurisdiction record { id, label, policy }.
 */
export function activeJurisdiction( config, id ) {
	const jurisdictions = config.jurisdictions || {};
	if ( id && jurisdictions[ id ] ) {
		return jurisdictions[ id ];
	}
	const fallbackId = config.defaultJurisdiction || '*';
	if ( jurisdictions[ fallbackId ] ) {
		return jurisdictions[ fallbackId ];
	}
	return {
		id: '*',
		label: '',
		policy: {
			type: 'opt_in',
			version: 1,
			denyByDefault: true,
			blocksBeforeConsent: true,
			showsBanner: true,
			defaultGranted: [],
		},
	};
}

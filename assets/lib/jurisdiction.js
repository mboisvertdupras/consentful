/**
 * Jurisdiction resolution (ADR 0002) — pure, framework-free, es2015. Picks the active
 * jurisdiction per visitor from a JS-readable geo signal, fail-closed to the strictest
 * `*` fallback when nothing positively places the visitor in a looser jurisdiction.
 *
 * Resolution stays 100% client/edge-side so server HTML is byte-identical for every
 * visitor (cache-safety). Used by BOTH the decider (sync) and the gate.
 */

import { readCookie } from './cookie.js';

/**
 * Map a region code to a jurisdiction id. Tries the full `CC-RR` code first, then the
 * `CC` country prefix.
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
 * Read a region code from the configured client/edge geo signal: an edge-set cookie,
 * then a window var. Empty names are skipped.
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
 * Resolve the active jurisdiction id synchronously from the geo signal. Returns only an
 * id that exists in the config's jurisdictions map; otherwise null (unresolved).
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
 * Resolve a jurisdiction record from an id: the matching record, else the configured
 * default jurisdiction, else a synthetic strictest `*` record. Never throws.
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

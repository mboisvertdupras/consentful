import { readCookie } from './cookie.js';

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

export function resolveJurisdictionSync( config, env ) {
	const region = readGeoSignal( config.geo, env );
	const id = mapRegionToJurisdiction( region, config.geo && config.geo.map );
	if ( id && config.jurisdictions && config.jurisdictions[ id ] ) {
		return id;
	}
	return null;
}

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

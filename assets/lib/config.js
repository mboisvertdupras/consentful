/**
 * Config parse/coerce — the single normalizer for window.consentfulConfig.
 *
 * Values arrive untrusted (PHP-encoded JSON, or wp_localize_script strings), so every
 * field is coerced explicitly. Output is a typed, defensively-built object the decider
 * and gate both consume; never the raw object.
 */

const toInt = ( value, fallback ) => {
	const n = parseInt( value, 10 );
	return Number.isFinite( n ) ? n : fallback;
};

const toBool = ( value ) => value === true || value === 'true' || value === 1 || value === '1';

const toStr = ( value, fallback = '' ) =>
	typeof value === 'string' ? value : value == null ? fallback : String( value );

const toArray = ( value ) => ( Array.isArray( value ) ? value : [] );

const toObject = ( value ) =>
	value && typeof value === 'object' && ! Array.isArray( value ) ? value : {};

const parsePurposes = ( raw ) =>
	toArray( raw ).map( ( p ) => ( {
		key: toStr( toObject( p ).key ),
		alwaysOn: toBool( toObject( p ).alwaysOn ),
	} ) );

export const parsePolicy = ( raw ) => {
	const p = toObject( raw );
	return {
		type: toStr( p.type, 'opt_in' ),
		version: toInt( p.version, 1 ),
		denyByDefault: toBool( p.denyByDefault ),
		blocksBeforeConsent: toBool( p.blocksBeforeConsent ),
		showsBanner: toBool( p.showsBanner ),
		defaultGranted: toArray( p.defaultGranted ).map( ( k ) => toStr( k ) ),
	};
};

const parseJurisdictions = ( raw ) => {
	const obj = toObject( raw );
	const out = {};
	for ( const id of Object.keys( obj ) ) {
		const entry = toObject( obj[ id ] );
		out[ id ] = {
			id: toStr( entry.id, id ),
			label: toStr( entry.label ),
			policy: parsePolicy( entry.policy ),
		};
	}
	// Always leave the resolver a strictest fallback to land on.
	if ( Object.keys( out ).length === 0 ) {
		out[ '*' ] = { id: '*', label: '', policy: parsePolicy( {} ) };
	}
	return out;
};

const parseGeo = ( raw ) => {
	const geo = toObject( raw );
	const map = toObject( geo.map );
	const out = {};
	for ( const code of Object.keys( map ) ) {
		out[ code ] = toStr( map[ code ] );
	}
	return {
		cookie: toStr( geo.cookie ),
		var: toStr( geo.var ),
		endpoint: toStr( geo.endpoint ),
		map: out,
	};
};

const parseProof = ( raw ) => {
	const p = toObject( raw );
	return {
		// Default true, but only loosen when the key is absent — an explicit value wins.
		enabled: 'enabled' in p ? toBool( p.enabled ) : true,
		endpoint: toStr( p.endpoint ),
		bannerVersion: toInt( p.bannerVersion, 1 ),
	};
};

const parseTags = ( raw ) =>
	toArray( raw ).map( ( t ) => {
		const tag = toObject( t );
		return {
			id: toStr( tag.id ),
			purposes: toArray( tag.purposes ).map( ( k ) => toStr( k ) ),
			delivery: toStr( tag.delivery, 'direct' ),
			adapter: toStr( tag.adapter ),
		};
	} );

const parseAdapters = ( raw ) => {
	const out = {};
	const obj = toObject( raw );
	for ( const id of Object.keys( obj ) ) {
		out[ id ] = toObject( obj[ id ] );
	}
	return out;
};

/**
 * Coerce window.consentfulConfig into a typed config object.
 *
 * @param {unknown} raw The raw config value.
 * @return {object} Normalized config.
 */
export function parseConfig( raw ) {
	const cfg = toObject( raw );
	const maxAgeDays = toInt( cfg.maxAgeDays, 180 );
	return {
		cookie: toStr( cfg.cookie, 'consentful' ),
		schemaVersion: toInt( cfg.schemaVersion, 1 ),
		policyVersion: toInt( cfg.policyVersion, 1 ),
		maxAgeDays,
		maxAgeMs: maxAgeDays * 24 * 60 * 60 * 1000,
		defaultJurisdiction: toStr( cfg.defaultJurisdiction, '*' ),
		jurisdictions: parseJurisdictions( cfg.jurisdictions ),
		geo: parseGeo( cfg.geo ),
		proof: parseProof( cfg.proof ),
		purposes: parsePurposes( cfg.purposes ),
		tags: parseTags( cfg.tags ),
		adapters: parseAdapters( cfg.adapters ),
	};
}

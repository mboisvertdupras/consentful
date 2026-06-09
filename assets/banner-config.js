const toBool = ( value ) => value === true || value === 'true' || value === 1 || value === '1';

const toStr = ( value, fallback = '' ) =>
	typeof value === 'string' ? value : value == null ? fallback : String( value );

const toInt = ( value, fallback ) => {
	const n = parseInt( value, 10 );
	return Number.isFinite( n ) ? n : fallback;
};

const toObject = ( value ) =>
	value && typeof value === 'object' && ! Array.isArray( value ) ? value : {};

const POSITIONS = [ 'bar', 'corner', 'modal' ];
const THEMES = [ 'light', 'dark', 'auto' ];

const oneOf = ( value, allowed, fallback ) =>
	allowed.indexOf( value ) !== -1 ? value : fallback;

const COPY_DEFAULTS = {
	title: 'Your privacy',
	description: '',
	privacyLabel: 'Privacy policy',
	prefsTitle: 'Manage preferences',
	acceptAll: 'Accept all',
	rejectAll: 'Reject all',
	customize: 'Manage preferences',
	save: 'Save preferences',
	reopen: 'Privacy settings',
	saved: 'Your privacy choices were saved.',
	noticeTitle: 'Your privacy choices',
	noticeDescription:
		'We use cookies and similar technologies to run this site, and for analytics, advertising and personalization. You can opt out at any time.',
	doNotSell: 'Do Not Sell or Share My Personal Information',
	close: 'Close',
};

const parseCopy = ( raw ) => {
	const obj = toObject( raw );
	const copy = {};
	for ( const key of Object.keys( COPY_DEFAULTS ) ) {
		copy[ key ] = toStr( obj[ key ], COPY_DEFAULTS[ key ] );
	}
	return copy;
};

const parsePurposeCopy = ( raw ) => {
	const obj = toObject( raw );
	const out = {};
	for ( const key of Object.keys( obj ) ) {
		const entry = toObject( obj[ key ] );
		out[ key ] = { label: toStr( entry.label ), description: toStr( entry.description ) };
	}
	return out;
};

export function coerceBannerConfig( raw ) {
	const cfg = toObject( raw );
	return {
		enabled: 'enabled' in cfg ? toBool( cfg.enabled ) : true,
		position: oneOf( toStr( cfg.position ), POSITIONS, 'bar' ),
		theme: oneOf( toStr( cfg.theme ), THEMES, 'auto' ),
		primaryColor: toStr( cfg.primaryColor, '#2563eb' ),
		radius: toInt( cfg.radius, 8 ),
		version: toInt( cfg.version, 1 ),
		privacyUrl: toStr( cfg.privacyUrl ),
		copy: parseCopy( cfg.copy ),
		purposes: parsePurposeCopy( cfg.purposes ),
	};
}

const humanize = ( key ) => {
	const spaced = key.replace( /[_-]+/g, ' ' ).trim();
	return spaced ? spaced.charAt( 0 ).toUpperCase() + spaced.slice( 1 ) : key;
};

export function purposeCopy( cfg, key ) {
	const entry = cfg.purposes[ key ];
	return {
		label: entry && entry.label ? entry.label : humanize( key ),
		description: entry && entry.description ? entry.description : '',
	};
}

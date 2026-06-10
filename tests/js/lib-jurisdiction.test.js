import { describe, it, expect } from 'vitest';
import {
	mapRegionToJurisdiction,
	readGeoSignal,
	resolveJurisdictionSync,
	activeJurisdiction,
} from '../../assets/lib/jurisdiction.js';

const MAP = { US: 'US', GB: 'UK', FR: 'EU', 'CA-QC': 'QC' };

const config = () => ( {
	defaultJurisdiction: '*',
	jurisdictions: {
		'*': { id: '*', policy: { type: 'opt_in' } },
		US: { id: 'US', policy: { type: 'opt_out' } },
		QC: { id: 'QC', policy: { type: 'opt_in' } },
	},
	geo: { cookie: '', var: '', endpoint: '', map: MAP },
} );

describe( 'lib/jurisdiction mapRegionToJurisdiction', () => {
	it( 'matches the full CC-RR code', () => {
		expect( mapRegionToJurisdiction( 'CA-QC', MAP ) ).toBe( 'QC' );
	} );

	it( 'falls back to the CC country prefix', () => {
		expect( mapRegionToJurisdiction( 'US-CA', MAP ) ).toBe( 'US' );
	} );

	it( 'is case-insensitive', () => {
		expect( mapRegionToJurisdiction( 'fr', MAP ) ).toBe( 'EU' );
		expect( mapRegionToJurisdiction( 'ca-qc', MAP ) ).toBe( 'QC' );
	} );

	it( 'returns null for an unmapped region or junk', () => {
		expect( mapRegionToJurisdiction( 'JP', MAP ) ).toBeNull();
		expect( mapRegionToJurisdiction( '', MAP ) ).toBeNull();
		expect( mapRegionToJurisdiction( null, MAP ) ).toBeNull();
	} );
} );

describe( 'lib/jurisdiction readGeoSignal', () => {
	const env = ( cookie, vars = {} ) => ( {
		win: vars,
		doc: { cookie },
	} );

	it( 'reads the edge cookie first', () => {
		const region = readGeoSignal( { cookie: 'geo', var: 'cnfGeo' }, env( 'geo=US-CA', { cnfGeo: 'FR' } ) );
		expect( region ).toBe( 'US-CA' );
	} );

	it( 'reads the window var when no cookie name/value', () => {
		expect( readGeoSignal( { cookie: '', var: 'cnfGeo' }, env( '', { cnfGeo: 'FR' } ) ) ).toBe( 'FR' );
	} );

	it( 'coerces a non-string window var to String', () => {
		expect( readGeoSignal( { cookie: '', var: 'cnfGeo' }, env( '', { cnfGeo: 1 } ) ) ).toBe( '1' );
	} );

	it( 'returns null when nothing is configured or present', () => {
		expect( readGeoSignal( { cookie: '', var: '' }, env( 'geo=US' ) ) ).toBeNull();
		expect( readGeoSignal( { cookie: 'geo', var: 'cnfGeo' }, env( '', {} ) ) ).toBeNull();
	} );
} );

describe( 'lib/jurisdiction resolveJurisdictionSync', () => {
	const env = ( cookie, vars = {} ) => ( { win: vars, doc: { cookie } } );

	it( 'returns a known id placed by the signal', () => {
		const cfg = config();
		cfg.geo.cookie = 'geo';
		expect( resolveJurisdictionSync( cfg, env( 'geo=US' ) ) ).toBe( 'US' );
	} );

	it( 'returns null for an unmapped region', () => {
		const cfg = config();
		cfg.geo.cookie = 'geo';
		expect( resolveJurisdictionSync( cfg, env( 'geo=JP' ) ) ).toBeNull();
	} );

	it( 'returns null when the mapped id is not a known jurisdiction', () => {
		const cfg = config();
		cfg.geo.cookie = 'geo';
		cfg.geo.map = { GB: 'UK' };
		expect( resolveJurisdictionSync( cfg, env( 'geo=GB' ) ) ).toBeNull();
	} );

	it( 'returns null with no signal', () => {
		expect( resolveJurisdictionSync( config(), env( '' ) ) ).toBeNull();
	} );
} );

describe( 'lib/jurisdiction activeJurisdiction', () => {
	it( 'returns the record for a present id', () => {
		expect( activeJurisdiction( config(), 'US' ).policy.type ).toBe( 'opt_out' );
	} );

	it( 'falls back to defaultJurisdiction for an unknown/null id', () => {
		expect( activeJurisdiction( config(), null ).id ).toBe( '*' );
		expect( activeJurisdiction( config(), 'ZZ' ).id ).toBe( '*' );
	} );

	it( 'synthesizes a strictest * record when no default is present', () => {
		const cfg = { defaultJurisdiction: 'missing', jurisdictions: {} };
		const rec = activeJurisdiction( cfg, null );
		expect( rec.id ).toBe( '*' );
		expect( rec.policy.type ).toBe( 'opt_in' );
		expect( rec.policy.defaultGranted ).toEqual( [] );
	} );
} );

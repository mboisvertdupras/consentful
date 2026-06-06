import { describe, it, expect } from 'vitest';
import { parseConfig, parsePolicy } from '../../assets/lib/config.js';

describe( 'lib/config parseConfig', () => {
	it( 'coerces the §1 shape with defensive defaults', () => {
		const cfg = parseConfig( {
			cookie: 'consentful',
			schemaVersion: 1,
			policyVersion: 1,
			maxAgeDays: 180,
			defaultJurisdiction: '*',
			purposes: [
				{ key: 'necessary', alwaysOn: true },
				{ key: 'analytics', alwaysOn: false },
			],
			jurisdictions: {
				'*': {
					id: '*',
					label: 'Default (strictest)',
					policy: {
						type: 'opt_in',
						version: 1,
						denyByDefault: true,
						blocksBeforeConsent: true,
						showsBanner: true,
						defaultGranted: [],
					},
				},
				US: {
					id: 'US',
					label: 'United States (state opt-out)',
					policy: {
						type: 'opt_out',
						version: 1,
						denyByDefault: false,
						blocksBeforeConsent: false,
						showsBanner: false,
						defaultGranted: [ 'analytics', 'marketing' ],
					},
				},
			},
			geo: {
				cookie: 'cnf_geo',
				var: 'cnfGeo',
				endpoint: 'https://example.test/wp-json/consentful/v1/geo',
				map: { US: 'US', FR: 'EU', 'CA-QC': 'QC' },
			},
			tags: [ { id: 'ga4', purposes: [ 'analytics' ], delivery: 'direct', adapter: 'google' } ],
			adapters: { google: { handler: 'google', measurementIds: [ 'G-X' ] } },
		} );

		expect( cfg.cookie ).toBe( 'consentful' );
		expect( cfg.schemaVersion ).toBe( 1 );
		expect( cfg.maxAgeMs ).toBe( 180 * 24 * 60 * 60 * 1000 );
		expect( cfg.defaultJurisdiction ).toBe( '*' );
		expect( cfg.purposes ).toEqual( [
			{ key: 'necessary', alwaysOn: true },
			{ key: 'analytics', alwaysOn: false },
		] );
		expect( Object.keys( cfg.jurisdictions ) ).toEqual( [ '*', 'US' ] );
		expect( cfg.jurisdictions[ '*' ].policy.type ).toBe( 'opt_in' );
		expect( cfg.jurisdictions.US.policy.type ).toBe( 'opt_out' );
		expect( cfg.jurisdictions.US.policy.defaultGranted ).toEqual( [ 'analytics', 'marketing' ] );
		expect( cfg.geo ).toEqual( {
			cookie: 'cnf_geo',
			var: 'cnfGeo',
			endpoint: 'https://example.test/wp-json/consentful/v1/geo',
			map: { US: 'US', FR: 'EU', 'CA-QC': 'QC' },
		} );
		expect( cfg.tags[ 0 ] ).toEqual( {
			id: 'ga4',
			purposes: [ 'analytics' ],
			delivery: 'direct',
			adapter: 'google',
		} );
		expect( cfg.adapters.google.measurementIds ).toEqual( [ 'G-X' ] );
	} );

	it( 'coerces string-encoded scalars (wp_localize_script)', () => {
		const cfg = parseConfig( {
			schemaVersion: '2',
			policyVersion: '3',
			maxAgeDays: '90',
			jurisdictions: {
				'*': { policy: { version: '4', denyByDefault: 'true', showsBanner: '1' } },
			},
			geo: { map: { US: 'US' } },
		} );
		expect( cfg.schemaVersion ).toBe( 2 );
		expect( cfg.policyVersion ).toBe( 3 );
		expect( cfg.maxAgeDays ).toBe( 90 );
		expect( cfg.jurisdictions[ '*' ].id ).toBe( '*' );
		expect( cfg.jurisdictions[ '*' ].policy.version ).toBe( 4 );
		expect( cfg.jurisdictions[ '*' ].policy.denyByDefault ).toBe( true );
		expect( cfg.jurisdictions[ '*' ].policy.showsBanner ).toBe( true );
		expect( cfg.geo.map.US ).toBe( 'US' );
	} );

	it( 'synthesizes a strictest * jurisdiction when the map is empty/missing', () => {
		const cfg = parseConfig( { schemaVersion: 1 } );
		expect( Object.keys( cfg.jurisdictions ) ).toEqual( [ '*' ] );
		expect( cfg.jurisdictions[ '*' ] ).toEqual( {
			id: '*',
			label: '',
			policy: parsePolicy( {} ),
		} );
	} );

	it( 'falls back to safe defaults for missing/garbage input', () => {
		const cfg = parseConfig( undefined );
		expect( cfg.cookie ).toBe( 'consentful' );
		expect( cfg.schemaVersion ).toBe( 1 );
		expect( cfg.policyVersion ).toBe( 1 );
		expect( cfg.maxAgeDays ).toBe( 180 );
		expect( cfg.defaultJurisdiction ).toBe( '*' );
		expect( cfg.purposes ).toEqual( [] );
		expect( cfg.tags ).toEqual( [] );
		expect( cfg.adapters ).toEqual( {} );
		expect( cfg.geo ).toEqual( { cookie: '', var: '', endpoint: '', map: {} } );
		expect( cfg.jurisdictions[ '*' ].policy.type ).toBe( 'opt_in' );
	} );
} );

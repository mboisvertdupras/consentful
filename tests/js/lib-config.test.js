import { describe, it, expect } from 'vitest';
import { parseConfig } from '../../assets/lib/config.js';

describe( 'lib/config parseConfig', () => {
	it( 'coerces the §2 shape with defensive defaults', () => {
		const cfg = parseConfig( {
			cookie: 'consentful',
			schemaVersion: 1,
			policyVersion: 1,
			maxAgeDays: 180,
			jurisdiction: '*',
			purposes: [
				{ key: 'necessary', alwaysOn: true },
				{ key: 'analytics', alwaysOn: false },
			],
			policy: {
				type: 'opt_in',
				version: 1,
				denyByDefault: true,
				blocksBeforeConsent: true,
				showsBanner: true,
				defaultGranted: [],
			},
			tags: [ { id: 'ga4', purposes: [ 'analytics' ], delivery: 'direct', adapter: 'google' } ],
			adapters: { google: { handler: 'google', measurementIds: [ 'G-X' ] } },
		} );

		expect( cfg.cookie ).toBe( 'consentful' );
		expect( cfg.schemaVersion ).toBe( 1 );
		expect( cfg.maxAgeMs ).toBe( 180 * 24 * 60 * 60 * 1000 );
		expect( cfg.purposes ).toEqual( [
			{ key: 'necessary', alwaysOn: true },
			{ key: 'analytics', alwaysOn: false },
		] );
		expect( cfg.policy.defaultGranted ).toEqual( [] );
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
			policy: { version: '4', denyByDefault: 'true', showsBanner: '1' },
		} );
		expect( cfg.schemaVersion ).toBe( 2 );
		expect( cfg.policyVersion ).toBe( 3 );
		expect( cfg.maxAgeDays ).toBe( 90 );
		expect( cfg.policy.version ).toBe( 4 );
		expect( cfg.policy.denyByDefault ).toBe( true );
		expect( cfg.policy.showsBanner ).toBe( true );
	} );

	it( 'falls back to safe defaults for missing/garbage input', () => {
		const cfg = parseConfig( undefined );
		expect( cfg.cookie ).toBe( 'consentful' );
		expect( cfg.schemaVersion ).toBe( 1 );
		expect( cfg.policyVersion ).toBe( 1 );
		expect( cfg.maxAgeDays ).toBe( 180 );
		expect( cfg.jurisdiction ).toBe( '*' );
		expect( cfg.purposes ).toEqual( [] );
		expect( cfg.tags ).toEqual( [] );
		expect( cfg.adapters ).toEqual( {} );
		expect( cfg.policy.type ).toBe( 'opt_in' );
	} );
} );

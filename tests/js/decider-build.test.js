import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import vm from 'node:vm';
import { describe, it, expect, beforeEach } from 'vitest';

const DECIDER_PATH = resolve( process.cwd(), 'build/decider.js' );

function builtDecider() {
	try {
		return readFileSync( DECIDER_PATH, 'utf8' );
	} catch {
		throw new Error(
			'build/decider.js is missing — run `npm run build:assets` before the test suite. ' +
				'PHP fails open without this file, so its absence must fail the suite, not skip.'
		);
	}
}

function optInConfig() {
	return {
		cookie: 'consentful',
		schemaVersion: 1,
		policyVersion: 1,
		maxAgeDays: 180,
		defaultJurisdiction: '*',
		jurisdictions: {
			'*': {
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
			},
		},
		geo: { cookie: '', var: '', endpoint: '', map: {} },
		proof: { enabled: false, endpoint: '', bannerVersion: 1 },
		purposes: [
			{ key: 'necessary', alwaysOn: true },
			{ key: 'functional', alwaysOn: false },
			{ key: 'analytics', alwaysOn: false },
			{ key: 'marketing', alwaysOn: false },
		],
		tags: [],
		adapters: {
			google: {
				handler: 'google',
				purposeSignals: {
					necessary: [ 'security_storage' ],
					functional: [ 'functionality_storage' ],
					analytics: [ 'analytics_storage' ],
					marketing: [ 'ad_storage', 'ad_user_data', 'ad_personalization' ],
				},
				adsDataRedaction: true,
				urlPassthrough: true,
				waitForUpdate: 500,
			},
		},
	};
}

function consentDefaults() {
	return ( window.dataLayer || [] )
		.map( ( a ) => Array.from( a ) )
		.filter( ( a ) => a[ 0 ] === 'consent' && a[ 1 ] === 'default' );
}

/**
 * Executes the production es2015 IIFE exactly as a browser would: seeded
 * window.consentfulConfig, then the script. Pins the bundle's self-invocation,
 * which no source-module test can cover.
 */
describe( 'built decider artifact', () => {
	beforeEach( () => {
		document.cookie = 'consentful=; max-age=0; path=/';
		delete window.consentful;
		delete window.consentfulConfig;
		delete window.dataLayer;
		delete window.gtag;
		delete window.__consentfulDefaultEmitted;
	} );

	it( 'self-executes and emits the default-deny Consent Mode state', () => {
		window.consentfulConfig = optInConfig();

		vm.runInThisContext( builtDecider(), { filename: 'build/decider.js' } );

		const defaults = consentDefaults();
		expect( defaults.length ).toBe( 1 );
		const state = defaults[ 0 ][ 2 ];
		expect( state.security_storage ).toBe( 'granted' );
		expect( state.analytics_storage ).toBe( 'denied' );
		expect( state.ad_storage ).toBe( 'denied' );
		expect( state.wait_for_update ).toBe( 500 );

		expect( window.consentful._init.hasDecision ).toBe( false );
		expect( window.consentful._init.grants.analytics ).toBe( false );
		expect( typeof window.consentful.registerAdapter ).toBe( 'function' );
	} );
} );

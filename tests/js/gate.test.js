import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { init } from '../../assets/gate.js';
import { reset as resetGoogle } from '../../assets/adapters/google.js';
import { reset as resetScript } from '../../assets/adapters/script.js';
import {
	parseConsent,
	validateConsent,
} from '../../assets/lib/cookie.js';
import { readCookie } from '../../assets/lib/cookie.js';
import { makeConfig, resetGlobals, setGpc, seedCookie } from './helpers.js';

function bootGate( config = makeConfig() ) {
	return init( config, { win: window, doc: document } );
}

function storedDecision() {
	return validateConsent( parseConsent( readCookie( 'consentful', document ) ), {
		schemaVersion: 1,
		policyVersion: 1,
		maxAgeMs: 1000 * 60 * 60 * 24 * 180,
	} );
}

describe( 'gate', () => {
	beforeEach( () => {
		resetGlobals();
		resetGoogle();
		resetScript();
	} );

	it( 'exposes the public API on window.consentful', () => {
		const api = bootGate();
		for ( const fn of [
			'get',
			'hasDecision',
			'gpc',
			'purposes',
			'jurisdiction',
			'policy',
			'setConsent',
			'acceptAll',
			'rejectAll',
			'onChange',
			'registerAdapter',
		] ) {
			expect( typeof api[ fn ] ).toBe( 'function' );
		}
		expect( api.jurisdiction() ).toBe( '*' );
		expect( api.policy().type ).toBe( 'opt_in' );
		expect( api.purposes().length ).toBe( 5 );
	} );

	it( 'recomputes without the decider _init', () => {
		// No decider ran; gate alone must compute opt-in deny-all.
		const api = bootGate();
		expect( api.get() ).toEqual( {
			necessary: true,
			functional: false,
			analytics: false,
			marketing: false,
			personalization: false,
		} );
		expect( api.hasDecision() ).toBe( false );
	} );

	it( 'setConsent writes a valid v1 cookie with g as 0/1', () => {
		const api = bootGate();
		api.setConsent( { analytics: true, marketing: false } );

		const raw = parseConsent( readCookie( 'consentful', document ) );
		expect( raw.v ).toBe( 1 );
		expect( raw.p ).toBe( 1 );
		expect( raw.j ).toBe( '*' );
		expect( raw.g.analytics ).toBe( 1 );
		expect( raw.g.marketing ).toBe( 0 );
		expect( raw.g.necessary ).toBe( 1 );
		expect( raw.t ).toBeGreaterThan( 0 );
		expect( storedDecision() ).not.toBeNull();
		expect( api.hasDecision() ).toBe( true );
	} );

	it( 'forces always-on true and drops unknown keys', () => {
		const api = bootGate();
		const grants = api.setConsent( { necessary: false, analytics: true, bogus: true } );
		expect( grants.necessary ).toBe( true );
		expect( 'bogus' in grants ).toBe( false );
	} );

	it( 'acceptAll grants every purpose; rejectAll denies non-essential', () => {
		const api = bootGate();
		expect( api.acceptAll() ).toEqual( {
			necessary: true,
			functional: true,
			analytics: true,
			marketing: true,
			personalization: true,
		} );
		expect( api.rejectAll() ).toEqual( {
			necessary: true,
			functional: false,
			analytics: false,
			marketing: false,
			personalization: false,
		} );
	} );

	it( 'GPC forces non-essential false even on acceptAll', () => {
		setGpc( true );
		const api = bootGate();
		expect( api.gpc() ).toBe( true );
		expect( api.acceptAll() ).toEqual( {
			necessary: true,
			functional: false,
			analytics: false,
			marketing: false,
			personalization: false,
		} );
	} );

	it( 'onChange fires with grants and unsubscribe stops it', () => {
		const api = bootGate();
		const seen = [];
		const off = api.onChange( ( g ) => seen.push( g ) );
		api.setConsent( { analytics: true } );
		off();
		api.setConsent( { marketing: true } );
		expect( seen.length ).toBe( 1 );
		expect( seen[ 0 ].analytics ).toBe( true );
	} );

	it( 'dispatches a consentful:change CustomEvent', () => {
		const api = bootGate();
		let detail = null;
		document.addEventListener( 'consentful:change', ( e ) => {
			detail = e.detail;
		} );
		api.setConsent( { analytics: true } );
		expect( detail ).not.toBeNull();
		expect( detail.analytics ).toBe( true );
	} );

	it( 'reads an existing valid stored decision on boot', () => {
		seedCookie( { v: 1, p: 1, j: '*', g: { analytics: 1 }, t: Date.now() } );
		const api = bootGate();
		expect( api.hasDecision() ).toBe( true );
		expect( api.get().analytics ).toBe( true );
	} );

	it( 'resolves several instances of one handler by the adapter handler field', () => {
		// Two custom snippets, distinct adapter ids, both mapped to the `script` handler.
		// The old lookup keyed by adapter id would miss both; the handler-field lookup fires them.
		const cfg = makeConfig( {
			tags: [
				{ id: 'a', purposes: [ 'necessary' ], delivery: 'direct', adapter: 'cnf-a' },
				{ id: 'b', purposes: [ 'necessary' ], delivery: 'direct', adapter: 'cnf-b' },
			],
			adapters: {
				'cnf-a': { handler: 'script', code: '<script src="https://example.test/a.js"></script>' },
				'cnf-b': { handler: 'script', code: '<script>window.__cnfB = 1;</script>' },
			},
		} );
		bootGate( cfg );

		const scripts = [ ...document.head.querySelectorAll( 'script' ) ];
		expect( scripts.some( ( s ) => s.getAttribute( 'src' ) === 'https://example.test/a.js' ) ).toBe( true );
		expect( scripts.some( ( s ) => s.textContent === 'window.__cnfB = 1;' ) ).toBe( true );
	} );

	it( 'drains the decider _adapterQueue', () => {
		const applied = [];
		window.consentful = {
			_adapterQueue: [ [ 'custom', { apply: ( ctx ) => applied.push( ctx.tag.id ) } ] ],
		};
		const cfg = makeConfig( {
			tags: [
				{ id: 'snip', purposes: [ 'necessary' ], delivery: 'direct', adapter: 'custom' },
			],
			adapters: { custom: { handler: 'custom' } },
		} );
		bootGate( cfg );
		expect( applied ).toEqual( [ 'snip' ] );
	} );

	it( 'resolves the jurisdiction/policy from the geo cookie synchronously', () => {
		document.cookie = 'cnf_geo=US; path=/';
		const cfg = makeConfig();
		cfg.geo.cookie = 'cnf_geo';
		const api = bootGate( cfg );
		expect( api.jurisdiction() ).toBe( 'US' );
		expect( api.policy().type ).toBe( 'opt_out' );
		expect( api.get().analytics ).toBe( true );
	} );
} );

/** Pull consent-update calls out of the dataLayer. */
function consentUpdates() {
	return ( window.dataLayer || [] )
		.map( ( a ) => Array.from( a ) )
		.filter( ( a ) => a[ 0 ] === 'consent' && a[ 1 ] === 'update' );
}

/** Resolve after the chained fetch microtasks settle. */
function flush() {
	return Promise.resolve().then( () => Promise.resolve() ).then( () => Promise.resolve() );
}

function geoConfig() {
	const cfg = makeConfig();
	cfg.geo.endpoint = 'https://example.test/wp-json/consentful/v1/geo';
	return cfg;
}

describe( 'gate async geo fallback', () => {
	beforeEach( () => {
		document.body.innerHTML = '';
		resetGlobals();
		resetGoogle();
		resetScript();
	} );

	afterEach( () => {
		delete window.fetch;
	} );

	it( 'adapts grants/policy + fires a consent update on geo resolution', async () => {
		window.fetch = vi.fn( () =>
			Promise.resolve( { ok: true, json: () => Promise.resolve( { region: 'US' } ) } )
		);
		const api = bootGate( geoConfig() );
		expect( api.jurisdiction() ).toBe( '*' );
		expect( api.get().analytics ).toBe( false );

		await flush();

		expect( window.fetch ).toHaveBeenCalledTimes( 1 );
		expect( api.jurisdiction() ).toBe( 'US' );
		expect( api.policy().type ).toBe( 'opt_out' );
		expect( api.get().analytics ).toBe( true );
		// google.apply diffs the state JSON, so the post-geo apply re-emits an update.
		expect( consentUpdates().length ).toBeGreaterThanOrEqual( 1 );
		// US is opt_out (Increment B): the prior opt-in `*` banner is torn down and replaced
		// by exactly one non-blocking Do-Not-Sell notice (no duplicates).
		expect( document.querySelectorAll( '.cnf-banner' ).length ).toBe( 1 );
		expect( document.querySelector( '.cnf-banner' ).classList.contains( 'cnf-banner--optout' ) ).toBe(
			true
		);
	} );

	it( 're-renders the banner without duplicating it on an opt-in geo change', async () => {
		window.fetch = vi.fn( () =>
			Promise.resolve( { ok: true, json: () => Promise.resolve( { region: 'CA-QC' } ) } )
		);
		const api = bootGate( geoConfig() );
		expect( api.jurisdiction() ).toBe( '*' );
		expect( document.querySelectorAll( '.cnf-banner' ).length ).toBe( 1 );

		await flush();

		expect( api.jurisdiction() ).toBe( 'QC' );
		expect( api.policy().type ).toBe( 'opt_in' );
		// Exactly one banner — the old node was destroyed before the re-render.
		expect( document.querySelectorAll( '.cnf-banner' ).length ).toBe( 1 );
		expect( document.querySelectorAll( '.cnf-reopen' ).length ).toBe( 1 );
	} );

	it( 'does not fetch when a stored decision already exists', async () => {
		seedCookie( { v: 1, p: 1, j: '*', g: { analytics: 1 }, t: Date.now() } );
		window.fetch = vi.fn();
		bootGate( geoConfig() );
		await flush();
		expect( window.fetch ).not.toHaveBeenCalled();
	} );

	it( 'does not fetch when the sync resolution already placed the visitor', async () => {
		document.cookie = 'cnf_geo=US; path=/';
		const cfg = geoConfig();
		cfg.geo.cookie = 'cnf_geo';
		window.fetch = vi.fn();
		const api = bootGate( cfg );
		expect( api.jurisdiction() ).toBe( 'US' );
		await flush();
		expect( window.fetch ).not.toHaveBeenCalled();
	} );

	it( 'does not fetch when no endpoint is configured', async () => {
		window.fetch = vi.fn();
		bootGate( makeConfig() );
		await flush();
		expect( window.fetch ).not.toHaveBeenCalled();
	} );

	it( 'stays on the fallback when fetch rejects', async () => {
		window.fetch = vi.fn( () => Promise.reject( new Error( 'network' ) ) );
		const api = bootGate( geoConfig() );
		await flush();
		expect( api.jurisdiction() ).toBe( '*' );
		expect( api.get().analytics ).toBe( false );
	} );
} );

/** Inject a navigator.sendBeacon mock (absent in jsdom); returns the spy. */
function mockBeacon( impl = () => true ) {
	const spy = vi.fn( impl );
	Object.defineProperty( navigator, 'sendBeacon', { value: spy, configurable: true } );
	return spy;
}

describe( 'gate proof of consent', () => {
	beforeEach( () => {
		resetGlobals();
		resetGoogle();
		resetScript();
	} );

	afterEach( () => {
		if ( 'sendBeacon' in navigator ) {
			delete navigator.sendBeacon;
		}
		delete window.fetch;
	} );

	it( 'sends exactly one proof record per decision with the §2 payload', () => {
		const beacon = mockBeacon();
		const api = bootGate();
		api.setConsent( { analytics: true, marketing: false } );

		expect( beacon ).toHaveBeenCalledTimes( 1 );
		const [ url, blob ] = beacon.mock.calls[ 0 ];
		expect( url ).toBe( 'https://example.test/wp-json/consentful/v1/consent' );
		expect( blob ).toBeInstanceOf( Blob );
		return blob.text().then( ( text ) => {
			const body = JSON.parse( text );
			expect( typeof body.cid ).toBe( 'string' );
			expect( body.cid.length ).toBeGreaterThan( 0 );
			// grants = the normalized decision (necessary forced on, marketing denied).
			expect( body.grants ).toEqual( api.get() );
			expect( body.grants.necessary ).toBe( true );
			expect( body.grants.analytics ).toBe( true );
			expect( body.grants.marketing ).toBe( false );
			expect( body.jurisdiction ).toBe( '*' );
			expect( body.policyVersion ).toBe( 1 );
			expect( body.schemaVersion ).toBe( 1 );
			expect( body.bannerVersion ).toBe( 1 );
			expect( typeof body.timestamp ).toBe( 'number' );
		} );
	} );

	it( 'sends the resolved jurisdiction id, not the default', () => {
		const beacon = mockBeacon();
		document.cookie = 'cnf_geo=US; path=/';
		const cfg = makeConfig();
		cfg.geo.cookie = 'cnf_geo';
		const api = bootGate( cfg );
		api.acceptAll();
		expect( beacon ).toHaveBeenCalledTimes( 1 );
		expect( api.jurisdiction() ).toBe( 'US' );
		return beacon.mock.calls[ 0 ][ 1 ].text().then( ( text ) => {
			expect( JSON.parse( text ).jurisdiction ).toBe( 'US' );
		} );
	} );

	it( 'sends once for acceptAll and once for rejectAll (funneled through setConsent)', () => {
		const beacon = mockBeacon();
		const api = bootGate();
		api.acceptAll();
		api.rejectAll();
		expect( beacon ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'does NOT send on the passive initial load', () => {
		const beacon = mockBeacon();
		bootGate();
		expect( beacon ).not.toHaveBeenCalled();
	} );

	it( 'does NOT send when a stored decision boots the gate (no new decision)', () => {
		seedCookie( { v: 1, p: 1, j: '*', g: { analytics: 1 }, t: Date.now() } );
		const beacon = mockBeacon();
		bootGate();
		expect( beacon ).not.toHaveBeenCalled();
	} );

	it( 'does NOT send when proof.enabled is false', () => {
		const beacon = mockBeacon();
		const cfg = makeConfig();
		cfg.proof.enabled = false;
		const api = bootGate( cfg );
		api.setConsent( { analytics: true } );
		expect( beacon ).not.toHaveBeenCalled();
	} );

	it( 'does NOT send when proof.endpoint is empty', () => {
		const beacon = mockBeacon();
		const cfg = makeConfig();
		cfg.proof.endpoint = '';
		const api = bootGate( cfg );
		api.setConsent( { analytics: true } );
		expect( beacon ).not.toHaveBeenCalled();
	} );

	it( 'falls back to fetch keepalive when sendBeacon is unavailable', () => {
		window.fetch = vi.fn( () => Promise.resolve() );
		const api = bootGate();
		api.setConsent( { analytics: true } );
		expect( window.fetch ).toHaveBeenCalledTimes( 1 );
		const [ url, opts ] = window.fetch.mock.calls[ 0 ];
		expect( url ).toBe( 'https://example.test/wp-json/consentful/v1/consent' );
		expect( opts.method ).toBe( 'POST' );
		expect( opts.keepalive ).toBe( true );
		expect( JSON.parse( opts.body ).grants.analytics ).toBe( true );
	} );

	it( 'a proof transport failure never breaks the decision', () => {
		mockBeacon( () => {
			throw new Error( 'beacon down' );
		} );
		const api = bootGate();
		expect( () => api.setConsent( { analytics: true } ) ).not.toThrow();
		// The decision still applied and persisted despite the proof failure.
		expect( api.get().analytics ).toBe( true );
		expect( api.hasDecision() ).toBe( true );
	} );
} );

import { describe, it, expect, beforeEach } from 'vitest';
import {
	readCookie,
	writeCookie,
	parseConsent,
	serializeConsent,
	validateConsent,
} from '../../assets/lib/cookie.js';

const EXPECT = { schemaVersion: 1, policyVersion: 1, maxAgeMs: 1000 * 60 * 60 * 24 * 180 };

describe( 'lib/cookie serialize/parse', () => {
	it( 'serializes the v1 shape with g as 0/1 ints', () => {
		const payload = serializeConsent( {
			schemaVersion: 1,
			policyVersion: 1,
			jurisdiction: '*',
			grants: { necessary: true, analytics: false, marketing: true },
			timestamp: 1700000000000,
		} );
		expect( payload ).toEqual( {
			v: 1,
			p: 1,
			j: '*',
			g: { necessary: 1, analytics: 0, marketing: 1 },
			t: 1700000000000,
		} );
	} );

	it( 'round-trips through validateConsent', () => {
		const now = 1700000000000;
		const payload = serializeConsent( {
			schemaVersion: 1,
			policyVersion: 1,
			jurisdiction: '*',
			grants: { analytics: true, marketing: false },
			timestamp: now,
		} );
		const decision = validateConsent( payload, { ...EXPECT, now: now + 1000 } );
		expect( decision.grants ).toEqual( { analytics: true, marketing: false } );
		expect( decision.jurisdiction ).toBe( '*' );
		expect( decision.timestamp ).toBe( now );
	} );

	it( 'parseConsent rejects malformed JSON and non-objects', () => {
		expect( parseConsent( 'not json' ) ).toBeNull();
		expect( parseConsent( '[1,2]' ) ).toBeNull();
		expect( parseConsent( '' ) ).toBeNull();
		expect( parseConsent( null ) ).toBeNull();
		expect( parseConsent( '{"v":1}' ) ).toEqual( { v: 1 } );
	} );
} );

describe( 'lib/cookie validateConsent', () => {
	const base = () =>
		serializeConsent( {
			schemaVersion: 1,
			policyVersion: 1,
			jurisdiction: '*',
			grants: { analytics: true },
			timestamp: 1700000000000,
		} );

	it( 'rejects a wrong schema version', () => {
		expect( validateConsent( { ...base(), v: 2 }, { ...EXPECT, now: 1700000001000 } ) ).toBeNull();
	} );

	it( 'rejects a wrong policy version', () => {
		expect( validateConsent( { ...base(), p: 9 }, { ...EXPECT, now: 1700000001000 } ) ).toBeNull();
	} );

	it( 'rejects t <= 0', () => {
		expect( validateConsent( { ...base(), t: 0 }, { ...EXPECT, now: 1700000001000 } ) ).toBeNull();
		expect( validateConsent( { ...base(), t: -5 }, { ...EXPECT, now: 1700000001000 } ) ).toBeNull();
	} );

	it( 'rejects an expired decision', () => {
		const old = base();
		const now = old.t + EXPECT.maxAgeMs + 1;
		expect( validateConsent( old, { ...EXPECT, now } ) ).toBeNull();
	} );

	it( 'accepts a decision exactly at the window edge', () => {
		const old = base();
		const now = old.t + EXPECT.maxAgeMs;
		expect( validateConsent( old, { ...EXPECT, now } ) ).not.toBeNull();
	} );

	it( 'rejects a missing or non-object g', () => {
		const p = base();
		delete p.g;
		expect( validateConsent( p, { ...EXPECT, now: 1700000001000 } ) ).toBeNull();
		expect(
			validateConsent( { ...base(), g: [ 1, 2 ] }, { ...EXPECT, now: 1700000001000 } )
		).toBeNull();
	} );

	it( 'rejects null payload', () => {
		expect( validateConsent( null, EXPECT ) ).toBeNull();
	} );

	it( 'reads grants as booleans from the 0/1 ints', () => {
		const payload = { ...base(), g: { analytics: 1, marketing: 0 } };
		const decision = validateConsent( payload, { ...EXPECT, now: 1700000001000 } );
		expect( decision.grants ).toEqual( { analytics: true, marketing: false } );
	} );
} );

describe( 'lib/cookie read/write against jsdom', () => {
	beforeEach( () => {
		document.cookie.split( ';' ).forEach( ( c ) => {
			const name = c.split( '=' )[ 0 ].trim();
			if ( name ) {
				document.cookie = name + '=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';
			}
		} );
	} );

	it( 'writes and reads back a payload', () => {
		const payload = serializeConsent( {
			schemaVersion: 1,
			policyVersion: 1,
			jurisdiction: '*',
			grants: { analytics: true },
			timestamp: Date.now(),
		} );
		writeCookie( 'consentful', payload, { maxAgeMs: EXPECT.maxAgeMs }, document );
		const raw = readCookie( 'consentful', document );
		expect( parseConsent( raw ) ).toEqual( payload );
	} );

	it( 'returns null for an absent cookie', () => {
		expect( readCookie( 'missing', document ) ).toBeNull();
	} );
} );

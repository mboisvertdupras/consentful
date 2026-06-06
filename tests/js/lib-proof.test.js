import { describe, it, expect, vi } from 'vitest';
import { newConsentId, sendConsentRecord } from '../../assets/lib/proof.js';

describe( 'lib/proof newConsentId', () => {
	it( 'uses crypto.randomUUID when present', () => {
		const win = { crypto: { randomUUID: () => 'uuid-1234' } };
		expect( newConsentId( win ) ).toBe( 'uuid-1234' );
	} );

	it( 'falls back to a unique c- id when randomUUID is absent', () => {
		const win = {};
		const a = newConsentId( win );
		const b = newConsentId( win );
		expect( a ).toMatch( /^c-[a-z0-9]+-[a-z0-9]+$/ );
		expect( a ).not.toBe( b );
	} );

	it( 'tolerates a missing window', () => {
		expect( newConsentId( undefined ) ).toMatch( /^c-/ );
	} );
} );

describe( 'lib/proof sendConsentRecord', () => {
	const payload = { cid: 'x', grants: { analytics: true } };

	it( 'returns false and sends nothing without an endpoint', () => {
		const win = { navigator: { sendBeacon: vi.fn() }, fetch: vi.fn() };
		expect( sendConsentRecord( '', payload, win ) ).toBe( false );
		expect( win.navigator.sendBeacon ).not.toHaveBeenCalled();
		expect( win.fetch ).not.toHaveBeenCalled();
	} );

	it( 'prefers sendBeacon with a JSON Blob', () => {
		const sendBeacon = vi.fn( () => true );
		const fetch = vi.fn();
		const win = { navigator: { sendBeacon }, fetch };
		expect( sendConsentRecord( '/ep', payload, win ) ).toBe( true );
		expect( sendBeacon ).toHaveBeenCalledTimes( 1 );
		expect( fetch ).not.toHaveBeenCalled();
		const [ url, blob ] = sendBeacon.mock.calls[ 0 ];
		expect( url ).toBe( '/ep' );
		expect( blob ).toBeInstanceOf( Blob );
		expect( blob.type ).toBe( 'application/json' );
	} );

	it( 'falls back to fetch keepalive when sendBeacon is absent', () => {
		const fetch = vi.fn( () => Promise.resolve() );
		const win = { navigator: {}, fetch };
		expect( sendConsentRecord( '/ep', payload, win ) ).toBe( true );
		expect( fetch ).toHaveBeenCalledTimes( 1 );
		const [ url, opts ] = fetch.mock.calls[ 0 ];
		expect( url ).toBe( '/ep' );
		expect( opts.method ).toBe( 'POST' );
		expect( opts.keepalive ).toBe( true );
		expect( opts.credentials ).toBe( 'omit' );
		expect( opts.headers ).toEqual( { 'Content-Type': 'application/json' } );
		expect( JSON.parse( opts.body ) ).toEqual( payload );
	} );

	it( 'falls back to fetch when sendBeacon returns false', () => {
		const sendBeacon = vi.fn( () => false );
		const fetch = vi.fn( () => Promise.resolve() );
		const win = { navigator: { sendBeacon }, fetch };
		expect( sendConsentRecord( '/ep', payload, win ) ).toBe( true );
		expect( sendBeacon ).toHaveBeenCalledTimes( 1 );
		expect( fetch ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'swallows a rejected fetch without throwing', () => {
		const fetch = vi.fn( () => Promise.reject( new Error( 'network' ) ) );
		const win = { navigator: {}, fetch };
		expect( () => sendConsentRecord( '/ep', payload, win ) ).not.toThrow();
		expect( sendConsentRecord( '/ep', payload, win ) ).toBe( true );
	} );

	it( 'returns false and does not throw when both transports are absent', () => {
		const win = { navigator: {} };
		expect( () => sendConsentRecord( '/ep', payload, win ) ).not.toThrow();
		expect( sendConsentRecord( '/ep', payload, win ) ).toBe( false );
	} );

	it( 'does not throw when a sendBeacon transport itself throws', () => {
		const sendBeacon = vi.fn( () => {
			throw new Error( 'boom' );
		} );
		const win = { navigator: { sendBeacon } };
		expect( () => sendConsentRecord( '/ep', payload, win ) ).not.toThrow();
	} );
} );

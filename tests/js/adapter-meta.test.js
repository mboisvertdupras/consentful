import { describe, it, expect, beforeEach } from 'vitest';
import { meta, reset } from '../../assets/adapters/meta.js';
import { resetGlobals } from './helpers.js';

function ctx( { granted = true, pixelIds = [ '123456' ] } = {} ) {
	return {
		tag: { id: 'meta-pixel', purposes: [ 'marketing' ] },
		adapterConfig: { handler: 'meta', pixelIds },
		grants: {},
		granted,
		win: window,
		doc: document,
	};
}

const pixelScripts = () =>
	document.querySelectorAll( 'script[src="https://connect.facebook.net/en_US/fbevents.js"]' );

const fbqCalls = () => ( window.fbq ? window.fbq.queue.map( ( a ) => Array.from( a ) ) : [] );

describe( 'adapters/meta', () => {
	beforeEach( () => {
		resetGlobals();
		reset();
		delete window.fbq;
		delete window._fbq;
		pixelScripts().forEach( ( s ) => s.remove() );
	} );

	it( 'injects nothing and installs no fbq when not granted', () => {
		meta.apply( ctx( { granted: false } ) );
		expect( pixelScripts().length ).toBe( 0 );
		expect( window.fbq ).toBeUndefined();
	} );

	it( 'installs the fbq stub, loads fbevents.js once and fires init + PageView', () => {
		meta.apply( ctx() );
		expect( typeof window.fbq ).toBe( 'function' );
		expect( window._fbq ).toBe( window.fbq );
		expect( window.fbq.loaded ).toBe( true );
		expect( window.fbq.version ).toBe( '2.0' );
		expect( pixelScripts().length ).toBe( 1 );
		expect( pixelScripts()[ 0 ].async ).toBe( true );
		expect( fbqCalls() ).toEqual( [
			[ 'init', '123456' ],
			[ 'trackSingle', '123456', 'PageView' ],
		] );
	} );

	it( 'is idempotent on re-apply', () => {
		meta.apply( ctx() );
		meta.apply( ctx() );
		expect( pixelScripts().length ).toBe( 1 );
		expect( fbqCalls() ).toEqual( [
			[ 'init', '123456' ],
			[ 'trackSingle', '123456', 'PageView' ],
		] );
	} );

	it( 'inits each pixel id once and scopes PageView per pixel', () => {
		meta.apply( ctx( { pixelIds: [ 123, '456' ] } ) );
		meta.apply( ctx( { pixelIds: [ 123, '456', '789' ] } ) );
		expect( fbqCalls() ).toEqual( [
			[ 'init', '123' ],
			[ 'trackSingle', '123', 'PageView' ],
			[ 'init', '456' ],
			[ 'trackSingle', '456', 'PageView' ],
			[ 'init', '789' ],
			[ 'trackSingle', '789', 'PageView' ],
		] );
	} );

	it( 'skips empty pixel ids', () => {
		meta.apply( ctx( { pixelIds: [ '' ] } ) );
		expect( fbqCalls() ).toEqual( [] );
	} );

	it( 'reuses an existing fbq without injecting a second fbevents.js', () => {
		const calls = [];
		window.fbq = ( ...a ) => calls.push( a );
		meta.apply( ctx() );
		expect( calls ).toEqual( [
			[ 'init', '123456' ],
			[ 'trackSingle', '123456', 'PageView' ],
		] );
		expect( pixelScripts().length ).toBe( 0 );
	} );

	it( 'never uses document.write', () => {
		const original = document.write;
		let called = false;
		document.write = () => {
			called = true;
		};
		meta.apply( ctx() );
		document.write = original;
		expect( called ).toBe( false );
	} );
} );

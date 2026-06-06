import { describe, it, expect, beforeEach } from 'vitest';
import { script, reset } from '../../assets/adapters/script.js';
import { resetGlobals } from './helpers.js';

function ctx( { id = 'snip', granted = true, adapterConfig } ) {
	return {
		tag: { id, purposes: [ 'functional' ] },
		adapterConfig,
		granted,
		grants: {},
		gtag: () => {},
		win: window,
		doc: document,
	};
}

describe( 'adapters/script', () => {
	beforeEach( () => {
		resetGlobals();
		reset();
		document.querySelectorAll( 'script[data-test-script]' ).forEach( ( s ) => s.remove() );
		document.querySelectorAll( 'script[src*="vendor.example"]' ).forEach( ( s ) => s.remove() );
	} );

	it( 'injects nothing when not granted', () => {
		script.apply( ctx( { granted: false, adapterConfig: { src: 'https://vendor.example/x.js' } } ) );
		expect( document.querySelectorAll( 'script[src*="vendor.example"]' ).length ).toBe( 0 );
	} );

	it( 'injects an async src script when granted', () => {
		script.apply(
			ctx( { adapterConfig: { src: 'https://vendor.example/x.js', attributes: { 'data-test-script': '1' } } } )
		);
		const el = document.querySelector( 'script[src*="vendor.example"]' );
		expect( el ).toBeTruthy();
		expect( el.async ).toBe( true );
		expect( el.getAttribute( 'data-test-script' ) ).toBe( '1' );
	} );

	it( 'injects inline code when given code', () => {
		script.apply(
			ctx( { id: 'inline', adapterConfig: { code: 'window.__snip=1', attributes: { 'data-test-script': 'inline' } } } )
		);
		const el = document.querySelector( 'script[data-test-script="inline"]' );
		expect( el.textContent ).toBe( 'window.__snip=1' );
		expect( el.src ).toBe( '' );
	} );

	it( 'is idempotent (injects once per tag)', () => {
		const cfg = { src: 'https://vendor.example/x.js' };
		script.apply( ctx( { adapterConfig: cfg } ) );
		script.apply( ctx( { adapterConfig: cfg } ) );
		expect( document.querySelectorAll( 'script[src*="vendor.example"]' ).length ).toBe( 1 );
	} );

	it( 'never uses document.write', () => {
		const original = document.write;
		let called = false;
		document.write = () => {
			called = true;
		};
		script.apply( ctx( { adapterConfig: { src: 'https://vendor.example/x.js' } } ) );
		document.write = original;
		expect( called ).toBe( false );
	} );
} );

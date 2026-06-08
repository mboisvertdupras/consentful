import { describe, it, expect, beforeEach } from 'vitest';
import { script, reset } from '../../assets/adapters/script.js';
import { resetGlobals } from './helpers.js';

function ctx( { id = 'snip', granted = true, adapterConfig } ) {
	return {
		tag: { id, purposes: [ 'functional' ] },
		adapterConfig,
		granted,
		grants: {},
		win: window,
		doc: document,
	};
}

describe( 'adapters/script', () => {
	beforeEach( () => {
		resetGlobals();
		reset();
		document.head.querySelectorAll( '[data-cnf-test]' ).forEach( ( n ) => n.remove() );
		document.head.querySelectorAll( 'script[src*="vendor.example"]' ).forEach( ( s ) => s.remove() );
		document.body.innerHTML = '';
	} );

	it( 'injects nothing when not granted', () => {
		script.apply( ctx( { granted: false, adapterConfig: { code: '<script src="https://vendor.example/x.js"></script>' } } ) );
		expect( document.querySelectorAll( 'script[src*="vendor.example"]' ).length ).toBe( 0 );
	} );

	it( 'injects an inline script into the head by default', () => {
		script.apply( ctx( { id: 'inline', adapterConfig: { code: '<script data-cnf-test="1">window.__snip=1;</script>' } } ) );
		const el = document.head.querySelector( 'script[data-cnf-test="1"]' );
		expect( el ).toBeTruthy();
		expect( el.textContent ).toBe( 'window.__snip=1;' );
		expect( el.src ).toBe( '' );
	} );

	it( 'injects an external script by its src', () => {
		script.apply( ctx( { adapterConfig: { code: '<script src="https://vendor.example/x.js"></script>' } } ) );
		const el = document.querySelector( 'script[src*="vendor.example"]' );
		expect( el ).toBeTruthy();
		expect( el.getAttribute( 'src' ) ).toBe( 'https://vendor.example/x.js' );
	} );

	it( 'injects multiple tags from one snippet', () => {
		script.apply(
			ctx( {
				id: 'multi',
				adapterConfig: {
					code:
						'<script data-cnf-test="a">1</script>' +
						'<noscript data-cnf-test="n"><img src="https://vendor.example/p.gif"></noscript>' +
						'<script data-cnf-test="b">2</script>',
				},
			} )
		);
		expect( document.head.querySelectorAll( 'script[data-cnf-test]' ).length ).toBe( 2 );
		expect( document.head.querySelector( 'noscript[data-cnf-test="n"]' ) ).toBeTruthy();
	} );

	it( 'injects at the end of the body when location=footer', () => {
		script.apply( ctx( { id: 'foot', adapterConfig: { code: '<script data-cnf-test="f">1</script>', location: 'footer' } } ) );
		expect( document.body.querySelector( 'script[data-cnf-test="f"]' ) ).toBeTruthy();
	} );

	it( 'injects at the top of the body when location=body', () => {
		document.body.innerHTML = '<p id="first">x</p>';
		script.apply( ctx( { id: 'top', adapterConfig: { code: '<div data-cnf-test="t"></div>', location: 'body' } } ) );
		expect( document.body.firstChild.getAttribute( 'data-cnf-test' ) ).toBe( 't' );
	} );

	it( 'is idempotent (injects once per tag)', () => {
		const cfg = { code: '<script src="https://vendor.example/x.js"></script>' };
		script.apply( ctx( { adapterConfig: cfg } ) );
		script.apply( ctx( { adapterConfig: cfg } ) );
		expect( document.querySelectorAll( 'script[src*="vendor.example"]' ).length ).toBe( 1 );
	} );

	it( 'injects nothing for an empty snippet', () => {
		script.apply( ctx( { id: 'empty', adapterConfig: { code: '' } } ) );
		expect( document.head.querySelectorAll( '[data-cnf-test]' ).length ).toBe( 0 );
	} );

	it( 'never uses document.write', () => {
		const original = document.write;
		let called = false;
		document.write = () => {
			called = true;
		};
		script.apply( ctx( { adapterConfig: { code: '<script src="https://vendor.example/x.js"></script>' } } ) );
		document.write = original;
		expect( called ).toBe( false );
	} );
} );

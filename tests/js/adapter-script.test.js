import { describe, it, expect, beforeEach } from 'vitest';
import { script, reset } from '../../assets/adapters/script.js';
import { resetGlobals } from './helpers.js';

function ctx( { id = 'snip', granted = true, fragments } ) {
	return {
		tag: { id, purposes: [ 'functional' ] },
		adapterConfig: { handler: 'script', fragments },
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
		script.apply( ctx( { granted: false, fragments: [ { code: '<script src="https://vendor.example/x.js"></script>', location: 'head' } ] } ) );
		expect( document.querySelectorAll( 'script[src*="vendor.example"]' ).length ).toBe( 0 );
	} );

	it( 'injects an inline script into the head by default', () => {
		script.apply( ctx( { id: 'inline', fragments: [ { code: '<script data-cnf-test="1">window.__snip=1;</script>' } ] } ) );
		const el = document.head.querySelector( 'script[data-cnf-test="1"]' );
		expect( el ).toBeTruthy();
		expect( el.textContent ).toBe( 'window.__snip=1;' );
		expect( el.src ).toBe( '' );
	} );

	it( 'injects an external script by its src', () => {
		script.apply( ctx( { fragments: [ { code: '<script src="https://vendor.example/x.js"></script>', location: 'head' } ] } ) );
		const el = document.querySelector( 'script[src*="vendor.example"]' );
		expect( el ).toBeTruthy();
		expect( el.getAttribute( 'src' ) ).toBe( 'https://vendor.example/x.js' );
	} );

	it( 'injects multiple tags from one fragment', () => {
		script.apply(
			ctx( {
				id: 'multi',
				fragments: [
					{
						code:
							'<script data-cnf-test="a">1</script>' +
							'<noscript data-cnf-test="n"><img src="https://vendor.example/p.gif"></noscript>' +
							'<script data-cnf-test="b">2</script>',
						location: 'head',
					},
				],
			} )
		);
		expect( document.head.querySelectorAll( 'script[data-cnf-test]' ).length ).toBe( 2 );
		expect( document.head.querySelector( 'noscript[data-cnf-test="n"]' ) ).toBeTruthy();
	} );

	it( 'injects each fragment at its own location', () => {
		script.apply(
			ctx( {
				id: 'split',
				fragments: [
					{ code: '<script data-cnf-test="h">1</script>', location: 'head' },
					{ code: '<script data-cnf-test="f">2</script>', location: 'footer' },
				],
			} )
		);
		expect( document.head.querySelector( 'script[data-cnf-test="h"]' ) ).toBeTruthy();
		expect( document.body.querySelector( 'script[data-cnf-test="f"]' ) ).toBeTruthy();
	} );

	it( 'injects at the top of the body when location=body', () => {
		document.body.innerHTML = '<p id="first">x</p>';
		script.apply( ctx( { id: 'top', fragments: [ { code: '<div data-cnf-test="t"></div>', location: 'body' } ] } ) );
		expect( document.body.firstChild.getAttribute( 'data-cnf-test' ) ).toBe( 't' );
	} );

	it( 'is idempotent (injects the whole tag once)', () => {
		const fragments = [ { code: '<script src="https://vendor.example/x.js"></script>', location: 'head' } ];
		script.apply( ctx( { fragments } ) );
		script.apply( ctx( { fragments } ) );
		expect( document.querySelectorAll( 'script[src*="vendor.example"]' ).length ).toBe( 1 );
	} );

	it( 'injects nothing for empty / missing fragments', () => {
		script.apply( ctx( { id: 'none', fragments: [] } ) );
		script.apply( ctx( { id: 'blank', fragments: [ { code: '', location: 'head' } ] } ) );
		expect( document.head.querySelectorAll( '[data-cnf-test]' ).length ).toBe( 0 );
	} );

	it( 'never uses document.write', () => {
		const original = document.write;
		let called = false;
		document.write = () => {
			called = true;
		};
		script.apply( ctx( { fragments: [ { code: '<script src="https://vendor.example/x.js"></script>', location: 'head' } ] } ) );
		document.write = original;
		expect( called ).toBe( false );
	} );
} );

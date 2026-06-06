import { describe, it, expect, beforeEach, vi } from 'vitest';
import { initBanner } from '../../assets/banner.js';

/**
 * Builds a fake gate API with spy decision methods over a mutable grants object, so the
 * banner can be driven without the real gate. `over` patches the defaults per-test.
 */
function makeApi( over = {} ) {
	const state = {
		grants: { necessary: true, functional: false, analytics: false, marketing: false },
		decision: false,
		gpc: false,
		policyType: 'opt_in',
		purposes: [
			{ key: 'necessary', alwaysOn: true },
			{ key: 'functional', alwaysOn: false },
			{ key: 'analytics', alwaysOn: false },
			{ key: 'marketing', alwaysOn: false },
		],
		...over,
	};
	return {
		state,
		get: vi.fn( () => ( { ...state.grants } ) ),
		hasDecision: vi.fn( () => state.decision ),
		gpc: vi.fn( () => state.gpc ),
		purposes: vi.fn( () => state.purposes.map( ( p ) => ( { ...p } ) ) ),
		policy: vi.fn( () => ( { type: state.policyType } ) ),
		setConsent: vi.fn(),
		acceptAll: vi.fn(),
		rejectAll: vi.fn(),
	};
}

const baseCfg = {
	enabled: true,
	position: 'bar',
	theme: 'auto',
	primaryColor: '#2563eb',
	radius: 8,
	version: 1,
	privacyUrl: '',
	copy: {},
	purposes: {
		necessary: { label: 'Strictly necessary', description: 'Essential.' },
		analytics: { label: 'Analytics', description: 'Measure usage.' },
	},
};

function boot( cfg = baseCfg, api = makeApi() ) {
	initBanner( api, cfg, { win: window, doc: document } );
	return api;
}

function bootHandle( cfg = baseCfg, api = makeApi() ) {
	return initBanner( api, cfg, { win: window, doc: document } );
}

const root = () => document.querySelector( '.cnf-banner' );
const pill = () => document.querySelector( '.cnf-reopen' );

describe( 'banner', () => {
	beforeEach( () => {
		document.body.innerHTML = '';
		document.body.removeAttribute( 'inert' );
	} );

	it( 'renders the panel for opt_in with no decision', () => {
		boot();
		expect( root() ).not.toBeNull();
		expect( root().hidden ).toBe( false );
		expect( pill().hidden ).toBe( true );
	} );

	it( 'hides the panel and shows the pill when a decision exists', () => {
		boot( baseCfg, makeApi( { decision: true } ) );
		expect( root().hidden ).toBe( true );
		expect( pill().hidden ).toBe( false );
	} );

	it( 'renders nothing under GPC', () => {
		boot( baseCfg, makeApi( { gpc: true } ) );
		expect( root() ).toBeNull();
		expect( pill() ).toBeNull();
	} );

	it( 'renders nothing when disabled', () => {
		boot( { ...baseCfg, enabled: false } );
		expect( root() ).toBeNull();
	} );

	it( 'renders nothing when the policy is not opt_in', () => {
		boot( baseCfg, makeApi( { policyType: 'opt_out' } ) );
		expect( root() ).toBeNull();
	} );

	it( 'defaults enabled true when the flag is absent', () => {
		const { enabled, ...withoutEnabled } = baseCfg;
		void enabled;
		boot( withoutEnabled );
		expect( root() ).not.toBeNull();
	} );

	it( 'applies position, theme, color and radius from config', () => {
		boot( { ...baseCfg, position: 'modal', theme: 'dark', primaryColor: '#abc123', radius: 12 } );
		const node = root();
		expect( node.classList.contains( 'cnf-banner--modal' ) ).toBe( true );
		expect( node.classList.contains( 'cnf-banner--theme-dark' ) ).toBe( true );
		expect( node.style.getPropertyValue( '--cnf-primary' ) ).toBe( '#abc123' );
		expect( node.style.getPropertyValue( '--cnf-radius' ) ).toBe( '12px' );
	} );

	it( 'renders one row per purpose with necessary checked + disabled', () => {
		boot();
		const inputs = root().querySelectorAll( '.cnf-purpose__input' );
		expect( inputs.length ).toBe( 4 );
		const necessary = root().querySelector( '.cnf-purpose__input[value="necessary"]' );
		expect( necessary.checked ).toBe( true );
		expect( necessary.disabled ).toBe( true );
		const analytics = root().querySelector( '.cnf-purpose__input[value="analytics"]' );
		expect( analytics.disabled ).toBe( false );
	} );

	it( 'falls back to a humanized label for purposes without copy', () => {
		boot();
		const functional = root().querySelector( '.cnf-purpose__input[value="functional"]' );
		const label = functional.closest( '.cnf-purpose' ).querySelector( '.cnf-purpose__label' );
		expect( label.textContent ).toBe( 'Functional' );
	} );

	it( 'renders the privacy link only when privacyUrl is set', () => {
		boot();
		expect( root().querySelector( '.cnf-banner__link' ) ).toBeNull();
		document.body.innerHTML = '';
		boot( { ...baseCfg, privacyUrl: 'https://example.com/privacy' } );
		const link = root().querySelector( '.cnf-banner__link' );
		expect( link ).not.toBeNull();
		expect( link.getAttribute( 'href' ) ).toBe( 'https://example.com/privacy' );
	} );

	it( 'Accept all calls api.acceptAll then shows the pill', () => {
		const api = boot();
		root().querySelector( '.cnf-btn--primary' ).click();
		expect( api.acceptAll ).toHaveBeenCalledTimes( 1 );
		expect( root().hidden ).toBe( true );
		expect( pill().hidden ).toBe( false );
	} );

	it( 'Reject all calls api.rejectAll then shows the pill', () => {
		const api = boot();
		root().querySelector( '.cnf-btn--reject' ).click();
		expect( api.rejectAll ).toHaveBeenCalledTimes( 1 );
		expect( root().hidden ).toBe( true );
	} );

	it( 'Customize reveals prefs and prefills from api.get', () => {
		const api = makeApi( {
			grants: { necessary: true, functional: false, analytics: true, marketing: false },
		} );
		boot( baseCfg, api );
		const prefs = root().querySelector( '.cnf-prefs' );
		const customize = root().querySelector( '.cnf-btn--ghost' );
		expect( prefs.hidden ).toBe( true );
		customize.click();
		expect( prefs.hidden ).toBe( false );
		expect( root().querySelector( '.cnf-btn--save' ).hidden ).toBe( false );
		expect( customize.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
		expect( root().querySelector( '.cnf-purpose__input[value="analytics"]' ).checked ).toBe(
			true
		);
	} );

	it( 'Save reads checkbox state and calls api.setConsent with the grants', () => {
		const api = boot();
		root().querySelector( '.cnf-btn--ghost' ).click(); // reveal prefs
		root().querySelector( '.cnf-purpose__input[value="marketing"]' ).checked = true;
		root().querySelector( '.cnf-btn--save' ).click();
		expect( api.setConsent ).toHaveBeenCalledTimes( 1 );
		expect( api.setConsent.mock.calls[ 0 ][ 0 ] ).toEqual( {
			necessary: true,
			functional: false,
			analytics: false,
			marketing: true,
		} );
		expect( root().hidden ).toBe( true );
	} );

	it( 'returns a destroy handle that removes the banner and pill', () => {
		const handle = bootHandle();
		expect( typeof handle.destroy ).toBe( 'function' );
		expect( root() ).not.toBeNull();
		expect( pill() ).not.toBeNull();
		handle.destroy();
		expect( root() ).toBeNull();
		expect( pill() ).toBeNull();
	} );

	it( 'returns a no-op destroy handle on early-return paths', () => {
		const disabled = bootHandle( { ...baseCfg, enabled: false } );
		expect( typeof disabled.destroy ).toBe( 'function' );
		expect( () => disabled.destroy() ).not.toThrow();
		const gpc = bootHandle( baseCfg, makeApi( { gpc: true } ) );
		expect( () => gpc.destroy() ).not.toThrow();
	} );

	it( 'destroy tears down the modal keydown trap and background-inert', () => {
		const sibling = document.createElement( 'div' );
		document.body.appendChild( sibling );
		const api = makeApi( { decision: false } );
		const handle = bootHandle( { ...baseCfg, position: 'modal' }, api );
		expect( sibling.getAttribute( 'inert' ) ).toBe( '' );
		handle.destroy();
		expect( sibling.hasAttribute( 'inert' ) ).toBe( false );
		expect( sibling.hasAttribute( 'aria-hidden' ) ).toBe( false );
		// The keydown trap is gone — re-init renders exactly one banner.
		bootHandle( { ...baseCfg, position: 'modal' }, api );
		expect( document.querySelectorAll( '.cnf-banner' ).length ).toBe( 1 );
	} );

	it( 'the pill re-opens the panel with prefs revealed and prefilled', () => {
		const api = makeApi( {
			decision: true,
			grants: { necessary: true, functional: false, analytics: true, marketing: false },
		} );
		boot( baseCfg, api );
		expect( root().hidden ).toBe( true );
		pill().click();
		expect( root().hidden ).toBe( false );
		expect( pill().hidden ).toBe( true );
		expect( root().querySelector( '.cnf-prefs' ).hidden ).toBe( false );
		expect( root().querySelector( '.cnf-purpose__input[value="analytics"]' ).checked ).toBe(
			true
		);
	} );
} );

describe( 'banner a11y', () => {
	beforeEach( () => {
		document.body.innerHTML = '';
		document.body.removeAttribute( 'inert' );
	} );

	const modalCfg = { ...baseCfg, position: 'modal' };

	it( 'sets dialog role and aria wiring in modal position', () => {
		boot( modalCfg );
		const node = root();
		expect( node.getAttribute( 'role' ) ).toBe( 'dialog' );
		expect( node.getAttribute( 'aria-modal' ) ).toBe( 'true' );
		const labelId = node.getAttribute( 'aria-labelledby' );
		const descId = node.getAttribute( 'aria-describedby' );
		expect( node.querySelector( '#' + labelId ).classList.contains( 'cnf-banner__title' ) ).toBe(
			true
		);
		expect( node.querySelector( '#' + descId ).classList.contains( 'cnf-banner__desc' ) ).toBe(
			true
		);
	} );

	it( 'uses a labeled region in bar position', () => {
		boot( baseCfg );
		expect( root().getAttribute( 'role' ) ).toBe( 'region' );
		expect( root().getAttribute( 'aria-label' ) ).toBeTruthy();
	} );

	it( 'makes background siblings inert while a modal is open and restores on close', () => {
		const sibling = document.createElement( 'div' );
		document.body.appendChild( sibling );
		const api = makeApi( { decision: true } );
		boot( modalCfg, api );
		expect( root().hidden ).toBe( true );
		expect( sibling.hasAttribute( 'inert' ) ).toBe( false );

		pill().click(); // open modal
		expect( sibling.getAttribute( 'inert' ) ).toBe( '' );
		expect( sibling.getAttribute( 'aria-hidden' ) ).toBe( 'true' );

		root().querySelector( '.cnf-btn--primary' ).click(); // decide => close
		expect( sibling.hasAttribute( 'inert' ) ).toBe( false );
		expect( sibling.hasAttribute( 'aria-hidden' ) ).toBe( false );
	} );

	it( 'preserves a sibling the site already hid (does not re-expose on close)', () => {
		const sibling = document.createElement( 'div' );
		sibling.setAttribute( 'aria-hidden', 'true' );
		document.body.appendChild( sibling );
		const api = makeApi( { decision: true } );
		boot( modalCfg, api );

		pill().click(); // open modal
		root().querySelector( '.cnf-btn--primary' ).click(); // decide => close
		// We never set it, so we must not strip the site's own aria-hidden.
		expect( sibling.getAttribute( 'aria-hidden' ) ).toBe( 'true' );
	} );

	it( 'Esc does not dismiss the first-visit modal gate (no implied consent)', () => {
		boot( modalCfg, makeApi( { decision: false } ) );
		expect( root().hidden ).toBe( false );
		document.dispatchEvent( new window.KeyboardEvent( 'keydown', { key: 'Escape' } ) );
		expect( root().hidden ).toBe( false );
	} );

	it( 'Esc closes the modal once a decision exists', () => {
		const api = makeApi( { decision: true } );
		boot( modalCfg, api );
		pill().click();
		expect( root().hidden ).toBe( false );
		document.dispatchEvent( new window.KeyboardEvent( 'keydown', { key: 'Escape' } ) );
		expect( root().hidden ).toBe( true );
	} );
} );

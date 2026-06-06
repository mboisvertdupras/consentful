import { describe, it, expect } from 'vitest';
import { computeGrants, isTagGranted } from '../../assets/lib/grants.js';

const purposes = [
	{ key: 'necessary', alwaysOn: true },
	{ key: 'functional', alwaysOn: false },
	{ key: 'analytics', alwaysOn: false },
	{ key: 'marketing', alwaysOn: false },
];

describe( 'lib/grants computeGrants (§6)', () => {
	it( 'always-on purposes are granted regardless', () => {
		const g = computeGrants( {
			purposes,
			policy: { defaultGranted: [] },
			stored: null,
			gpc: true,
		} );
		expect( g.necessary ).toBe( true );
	} );

	it( 'opt-in fallback (no decision, empty defaultGranted) denies all non-essential', () => {
		const g = computeGrants( {
			purposes,
			policy: { defaultGranted: [] },
			stored: null,
			gpc: false,
		} );
		expect( g ).toEqual( {
			necessary: true,
			functional: false,
			analytics: false,
			marketing: false,
		} );
	} );

	it( 'default-granted purposes are granted pre-decision', () => {
		const g = computeGrants( {
			purposes,
			policy: { defaultGranted: [ 'analytics' ] },
			stored: null,
			gpc: false,
		} );
		expect( g.analytics ).toBe( true );
		expect( g.marketing ).toBe( false );
	} );

	it( 'reflects a valid stored decision (missing key ⇒ false)', () => {
		const g = computeGrants( {
			purposes,
			policy: { defaultGranted: [] },
			stored: { grants: { analytics: true } },
			gpc: false,
		} );
		expect( g.analytics ).toBe( true );
		expect( g.marketing ).toBe( false );
	} );

	it( 'GPC overrides a stored grant to deny', () => {
		const g = computeGrants( {
			purposes,
			policy: { defaultGranted: [ 'analytics' ] },
			stored: { grants: { analytics: true, marketing: true } },
			gpc: true,
		} );
		expect( g.analytics ).toBe( false );
		expect( g.marketing ).toBe( false );
		expect( g.necessary ).toBe( true );
	} );
} );

describe( 'lib/grants isTagGranted', () => {
	const grants = { analytics: true, marketing: false };

	it( 'empty purposes ⇒ false (fail-closed)', () => {
		expect( isTagGranted( { purposes: [] }, grants ) ).toBe( false );
		expect( isTagGranted( {}, grants ) ).toBe( false );
	} );

	it( 'every purpose must be granted (AND)', () => {
		expect( isTagGranted( { purposes: [ 'analytics' ] }, grants ) ).toBe( true );
		expect( isTagGranted( { purposes: [ 'analytics', 'marketing' ] }, grants ) ).toBe( false );
	} );
} );

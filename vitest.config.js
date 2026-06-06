import { defineConfig } from 'vitest/config';

/**
 * The client gate (decider + engine) is the riskiest, compliance-critical code, so it
 * carries a first-class JS test layer. jsdom gives the tests a DOM, cookie jar and
 * navigator to drive the gate against.
 */
export default defineConfig( {
	test: {
		environment: 'jsdom',
		include: [ 'tests/js/**/*.test.js' ],
		passWithNoTests: true,
	},
} );

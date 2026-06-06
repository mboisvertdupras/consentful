/**
 * ESLint flat config (ESLint 9+).
 *
 * The client gate is authored as modern ES modules under assets/ and bundled by Vite
 * to a framework-free es2015 IIFE, so it is linted as modern ESM — not the legacy
 * WordPress es5 standard the pre-rewrite banner used.
 */
import globals from 'globals';

export default [
	{
		ignores: [ 'build/**', 'node_modules/**', 'vendor/**', 'languages/**' ],
	},

	{
		files: [ 'assets/**/*.js' ],
		languageOptions: {
			ecmaVersion: 2022,
			sourceType: 'module',
			globals: {
				...globals.browser,
			},
		},
		rules: {
			'no-undef': 'error',
			'no-unused-vars': 'error',
		},
	},

	{
		files: [ 'tests/js/**/*.js' ],
		languageOptions: {
			ecmaVersion: 2022,
			sourceType: 'module',
			globals: {
				...globals.browser,
				...globals.node,
			},
		},
		rules: {
			'no-undef': 'error',
			'no-unused-vars': 'error',
		},
	},
];

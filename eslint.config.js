/**
 * ESLint flat config (ESLint 9+).
 *
 * @wordpress/eslint-plugin still ships legacy (eslintrc) shareable configs, so
 * we bridge its `es5` config — the WordPress JS coding standard for vanilla,
 * non-bundled scripts — into flat config via FlatCompat.
 */
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { FlatCompat } from '@eslint/eslintrc';
import globals from 'globals';

const __dirname = path.dirname( fileURLToPath( import.meta.url ) );
const compat = new FlatCompat( { baseDirectory: __dirname } );

export default [
	{
		ignores: [ 'build/**', 'node_modules/**', 'vendor/**', 'languages/**' ],
	},

	// The consent banner is a hand-tuned ES5 IIFE that must run in old browsers
	// before any framework loads. Lint it against the WordPress ES5 standard.
	...compat.extends( 'plugin:@wordpress/eslint-plugin/es5' ).map( ( config ) => ( {
		...config,
		files: [ 'assets/**/*.js' ],
	} ) ),
	{
		files: [ 'assets/**/*.js' ],
		languageOptions: {
			ecmaVersion: 5,
			sourceType: 'script',
			globals: {
				...globals.browser,
				// Legacy banner global, pending the gate rewrite.
				CMV2_CONSENT: 'readonly',
			},
		},
		rules: {
			// The banner declares each var at its point of use, after guard
			// clauses, so the declaration stays next to its explanatory comment.
			'vars-on-top': 'off',
			// ES5 try/catch requires a binding even when the error is
			// deliberately swallowed (cookie-parse / focus() failures).
			'no-unused-vars': [
				'error',
				{ ignoreRestSiblings: true, caughtErrors: 'none' },
			],
		},
	},
];

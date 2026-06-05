import { defineConfig } from 'vite';

/**
 * Build config for a classic (non-SPA) WordPress plugin.
 *
 * The banner CSS is enqueued in <head> (with server-injected theming vars) and
 * the banner JS in the footer, so they are kept as two independent entries
 * rather than letting Vite inline the CSS into the JS bundle. PHP reads
 * build/.vite/manifest.json to resolve the content-hashed filenames.
 */
export default defineConfig( {
	root: __dirname,
	build: {
		outDir: 'build',
		emptyOutDir: true,
		manifest: true,
		// No index.html entry — list the source files explicitly.
		rollupOptions: {
			input: {
				consent: 'src/consent.js',
				'consent-style': 'src/consent.css',
			},
			output: {
				entryFileNames: 'assets/[name].[hash].js',
				chunkFileNames: 'assets/[name].[hash].js',
				assetFileNames: 'assets/[name].[hash][extname]',
			},
		},
		// The banner script is a hand-tuned ES5 IIFE that must run in old browsers
		// before any framework — keep the output close to the source.
		target: 'es2015',
	},
} );

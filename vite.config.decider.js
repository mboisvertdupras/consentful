import { defineConfig } from 'vite';

/**
 * Decider build. The decider is inlined into <head> by PHP and must run before any tag
 * (to set the Consent Mode default), so it cannot be a deferred module — it builds to a
 * single self-contained IIFE at a fixed path (build/decider.js) that PHP reads and
 * inlines. emptyOutDir is false so it does not wipe the gate build's output; run after
 * the gate build.
 */
export default defineConfig( {
	root: __dirname,
	build: {
		outDir: 'build',
		emptyOutDir: false,
		manifest: false,
		target: 'es2015',
		rollupOptions: {
			input: { decider: 'assets/decider.js' },
			output: {
				format: 'iife',
				entryFileNames: 'decider.js',
				assetFileNames: '[name][extname]',
			},
		},
	},
} );

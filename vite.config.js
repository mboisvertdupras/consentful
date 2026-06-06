import { defineConfig } from 'vite';

/**
 * Gate-bundle build. The gate is the engine, enqueued in the footer as a classic
 * (non-module) script, so it builds to a single self-contained IIFE — content-hashed
 * with a manifest PHP reads (build/.vite/manifest.json) to resolve the filename. The
 * inline-head decider is a separate build (vite.config.decider.js).
 */
export default defineConfig( {
	root: __dirname,
	build: {
		outDir: 'build',
		emptyOutDir: true,
		manifest: true,
		// Framework-free output that still runs in the browsers we support (no IE11).
		target: 'es2015',
		rollupOptions: {
			input: { gate: 'assets/gate.js' },
			output: {
				format: 'iife',
				entryFileNames: 'assets/[name].[hash].js',
				assetFileNames: 'assets/[name].[hash][extname]',
			},
		},
	},
} );

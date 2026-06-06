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
		// A single-entry IIFE can't code-split, so Vite would otherwise inline the
		// banner CSS into the JS and inject a runtime <style> (needs CSP
		// 'unsafe-inline'). Extract one real stylesheet instead — CSP-clean and
		// separately cacheable. Vite keys the aggregate as `style.css` in the manifest.
		cssCodeSplit: false,
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

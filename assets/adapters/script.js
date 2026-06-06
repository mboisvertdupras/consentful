/**
 * Generic script handler (Direct) — proves vendor-neutrality. Injects a <script> by
 * `src` (async) or inline `code` when its tag is granted; applies any attributes.
 * Idempotent (injects once per tag); never uses document.write.
 */

const injected = new Set();

/** Reset module state (test seam). */
export function reset() {
	injected.clear();
}

export const script = {
	/**
	 * @param {object}  ctx
	 * @param {object}  ctx.tag           The gated tag (its id keys idempotency).
	 * @param {object}  ctx.adapterConfig { src?, code?, attributes? }.
	 * @param {boolean} ctx.granted
	 * @param {Document} ctx.doc
	 */
	apply( ctx ) {
		const { tag, adapterConfig, granted, doc } = ctx;
		if ( ! granted ) {
			return;
		}
		const key = tag && tag.id ? tag.id : '';
		if ( injected.has( key ) ) {
			return;
		}
		injected.add( key );

		const cfg = adapterConfig || {};
		const el = doc.createElement( 'script' );
		if ( cfg.src ) {
			el.async = true;
			el.src = String( cfg.src );
		} else if ( cfg.code ) {
			el.textContent = String( cfg.code );
		}
		const attrs = cfg.attributes && typeof cfg.attributes === 'object' ? cfg.attributes : {};
		for ( const name of Object.keys( attrs ) ) {
			el.setAttribute( name, String( attrs[ name ] ) );
		}
		( doc.head || doc.documentElement ).appendChild( el );
	},
};

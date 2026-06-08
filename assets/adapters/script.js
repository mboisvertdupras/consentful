/**
 * Generic script handler (Direct) — proves vendor-neutrality. Injects a snippet's HTML
 * (one or more tags) at a chosen location (head/body/footer) when its tag is granted.
 * The HTML is parsed in an inert <template>, then each <script> is re-created so it
 * executes (a parsed script never runs on its own) and other elements are imported as-is.
 * Idempotent (injects once per tag); never uses document.write.
 */

const injected = new Set();

/** Reset module state (test seam). */
export function reset() {
	injected.clear();
}

/** The { parent, before } insertion point for a location ('head' by default). */
function target( location, doc ) {
	const body = doc.body;
	if ( 'body' === location ) {
		return { parent: body, before: body ? body.firstChild : null };
	}
	if ( 'footer' === location ) {
		return { parent: body, before: null };
	}
	return { parent: doc.head || doc.documentElement, before: null };
}

/** A fresh, executable copy of a parsed <script> (attributes + inline code preserved). */
function recreateScript( node, doc ) {
	const el = doc.createElement( 'script' );
	for ( const attr of Array.prototype.slice.call( node.attributes ) ) {
		el.setAttribute( attr.name, attr.value );
	}
	if ( node.textContent ) {
		el.textContent = node.textContent;
	}
	return el;
}

export const script = {
	/**
	 * @param {object}   ctx
	 * @param {object}   ctx.tag           The gated tag (its id keys idempotency).
	 * @param {object}   ctx.adapterConfig { code?, location? }.
	 * @param {boolean}  ctx.granted
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
		const code = cfg.code ? String( cfg.code ) : '';
		if ( ! code ) {
			return;
		}

		const { parent, before } = target( cfg.location, doc );
		if ( ! parent ) {
			return;
		}

		const tpl = doc.createElement( 'template' );
		tpl.innerHTML = code;

		const frag = doc.createDocumentFragment();
		for ( const node of Array.prototype.slice.call( tpl.content.childNodes ) ) {
			if ( node.nodeType !== 1 ) {
				continue;
			}
			frag.appendChild(
				'SCRIPT' === node.nodeName ? recreateScript( node, doc ) : doc.importNode( node, true )
			);
		}

		if ( before ) {
			parent.insertBefore( frag, before );
		} else {
			parent.appendChild( frag );
		}
	},
};

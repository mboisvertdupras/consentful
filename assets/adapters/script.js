const injected = new Set();

export function reset() {
	injected.clear();
}

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

function injectFragment( fragment, doc ) {
	const code = fragment && fragment.code ? String( fragment.code ) : '';
	if ( ! code ) {
		return;
	}
	const { parent, before } = target( fragment && fragment.location, doc );
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
}

export const script = {
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
		const fragments = Array.isArray( cfg.fragments ) ? cfg.fragments : [];
		for ( const fragment of fragments ) {
			injectFragment( fragment, doc );
		}
	},
};

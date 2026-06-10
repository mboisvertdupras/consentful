let scriptLoaded = false;
const inited = new Set();

/** Reset module state (test seam). */
export function reset() {
	scriptLoaded = false;
	inited.clear();
}

const ensureFbq = ( win ) => {
	if ( win.fbq ) {
		return win.fbq;
	}
	const fbq = function () {
		if ( fbq.callMethod ) {
			fbq.callMethod.apply( fbq, arguments );
		} else {
			fbq.queue.push( arguments );
		}
	};
	if ( ! win._fbq ) {
		win._fbq = fbq;
	}
	fbq.push = fbq;
	fbq.loaded = true;
	fbq.version = '2.0';
	fbq.queue = [];
	win.fbq = fbq;
	return fbq;
};

const loadScript = ( doc ) => {
	if ( scriptLoaded ) {
		return;
	}
	scriptLoaded = true;
	const script = doc.createElement( 'script' );
	script.async = true;
	script.src = 'https://connect.facebook.net/en_US/fbevents.js';
	const first = doc.getElementsByTagName( 'script' )[ 0 ];
	if ( first && first.parentNode ) {
		first.parentNode.insertBefore( script, first );
	} else {
		( doc.head || doc.documentElement ).appendChild( script );
	}
};

export const meta = {
	/**
	 * @param {object}   ctx
	 * @param {object}   ctx.adapterConfig { pixelIds }.
	 * @param {boolean}  ctx.granted
	 * @param {Window}   ctx.win
	 * @param {Document} ctx.doc
	 */
	apply( ctx ) {
		const { adapterConfig, granted, win, doc } = ctx;
		if ( ! granted ) {
			return;
		}
		// Canonical base-code semantics: a pre-existing fbq means another integration
		// already loads fbevents.js — reuse it, never inject a second copy.
		const hadFbq = !! win.fbq;
		const fbq = ensureFbq( win );
		if ( ! hadFbq ) {
			loadScript( doc );
		}
		const ids = Array.isArray( adapterConfig && adapterConfig.pixelIds )
			? adapterConfig.pixelIds
			: [];
		for ( const id of ids ) {
			if ( ! id || inited.has( String( id ) ) ) {
				continue;
			}
			inited.add( String( id ) );
			fbq( 'init', String( id ) );
			// trackSingle: an unscoped track would re-send PageView to every prior pixel.
			fbq( 'trackSingle', String( id ), 'PageView' );
		}
	},
};

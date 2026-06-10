import { signalState } from './google-signals.js';
import { resolveGtag } from './google-gtag.js';

let lastStateJson = null;
const loaded = new Set();

/** Reset module state (test seam). */
export function reset() {
	lastStateJson = null;
	loaded.clear();
}

const loadGtag = ( id, gtag, doc ) => {
	if ( loaded.has( id ) ) {
		return;
	}
	loaded.add( id );
	const script = doc.createElement( 'script' );
	script.async = true;
	script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent( id );
	const first = doc.getElementsByTagName( 'script' )[ 0 ];
	if ( first && first.parentNode ) {
		first.parentNode.insertBefore( script, first );
	} else {
		( doc.head || doc.documentElement ).appendChild( script );
	}
	gtag( 'js', new Date() );
	gtag( 'config', id );
};

const loadContainer = ( id, win, doc ) => {
	if ( loaded.has( id ) ) {
		return;
	}
	loaded.add( id );
	win.dataLayer = win.dataLayer || [];
	win.dataLayer.push( { 'gtm.start': new Date().getTime(), event: 'gtm.js' } );
	const script = doc.createElement( 'script' );
	script.async = true;
	script.src = 'https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent( id );
	const first = doc.getElementsByTagName( 'script' )[ 0 ];
	if ( first && first.parentNode ) {
		first.parentNode.insertBefore( script, first );
	} else {
		( doc.head || doc.documentElement ).appendChild( script );
	}
};

export const google = {
	/**
	 * @param {object}   ctx
	 * @param {object}   ctx.tag           The gated tag (its id keys its product in products).
	 * @param {object}   ctx.adapterConfig { products, purposeSignals }.
	 * @param {object}   ctx.grants        Purpose key => bool.
	 * @param {boolean}  ctx.granted
	 * @param {Window}   ctx.win
	 * @param {Document} ctx.doc
	 * @param {Function} [ctx.gtag]        Optional pre-resolved gtag (else resolved from win).
	 */
	apply( ctx ) {
		const { tag, adapterConfig, grants, granted, win, doc } = ctx;
		const gtag = ctx.gtag || resolveGtag( win );
		const purposeSignals =
			adapterConfig && adapterConfig.purposeSignals ? adapterConfig.purposeSignals : {};
		const state = signalState( grants, purposeSignals );

		const stateJson = JSON.stringify( state );
		if ( stateJson !== lastStateJson ) {
			lastStateJson = stateJson;
			gtag( 'consent', 'update', state );
		}

		if ( ! granted ) {
			return;
		}
		const product =
			( adapterConfig && adapterConfig.products && adapterConfig.products[ tag.id ] ) || {};
		const ids = Array.isArray( product.measurementIds ) ? product.measurementIds : [];
		for ( const id of ids ) {
			if ( id ) {
				loadGtag( String( id ), gtag, doc );
			}
		}

		const containerIds = Array.isArray( product.containerIds ) ? product.containerIds : [];
		for ( const id of containerIds ) {
			if ( id ) {
				loadContainer( String( id ), win, doc );
			}
		}
	},
};

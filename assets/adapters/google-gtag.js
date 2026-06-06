/**
 * Google-owned dataLayer/gtag bootstrap. The vendor-neutral gate engine must NOT know
 * gtag/dataLayer, so the Google-family handlers (google.js, gtm.js) resolve the gtag
 * function themselves — preferring one already on `win` (e.g. the decider's head shim),
 * else installing the standard dataLayer-push shim lazily on first apply.
 *
 * @param {Window} win The window to resolve/install gtag on.
 * @return {Function} The gtag function.
 */
export function resolveGtag( win ) {
	win.dataLayer = win.dataLayer || [];
	if ( typeof win.gtag !== 'function' ) {
		win.gtag = function () {
			win.dataLayer.push( arguments );
		};
	}
	return win.gtag;
}

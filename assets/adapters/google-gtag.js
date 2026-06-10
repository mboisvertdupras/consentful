/**
 * Resolve gtag on `win`, installing the standard dataLayer-push shim if absent.
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

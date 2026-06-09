export function resolveGtag( win ) {
	win.dataLayer = win.dataLayer || [];
	if ( typeof win.gtag !== 'function' ) {
		win.gtag = function () {
			win.dataLayer.push( arguments );
		};
	}
	return win.gtag;
}

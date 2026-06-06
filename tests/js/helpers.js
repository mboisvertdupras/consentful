/**
 * Shared test helpers — a §2-shaped config fixture and global-reset utilities so each
 * suite drives the entries/handlers against a clean jsdom.
 */

const optInPolicy = () => ( {
	type: 'opt_in',
	version: 1,
	denyByDefault: true,
	blocksBeforeConsent: true,
	showsBanner: true,
	defaultGranted: [],
} );

const optOutPolicy = () => ( {
	type: 'opt_out',
	version: 1,
	denyByDefault: false,
	blocksBeforeConsent: false,
	showsBanner: true,
	defaultGranted: [ 'functional', 'analytics', 'marketing', 'personalization' ],
} );

const noticeOnlyPolicy = () => ( {
	type: 'notice_only',
	version: 1,
	denyByDefault: false,
	blocksBeforeConsent: false,
	showsBanner: false,
	defaultGranted: [ 'functional', 'analytics', 'marketing', 'personalization' ],
} );

/** Policy record factories — the banner reads the resolved policy via api.policy(). */
export { optInPolicy, optOutPolicy, noticeOnlyPolicy };

export function makeConfig( overrides = {} ) {
	return {
		cookie: 'consentful',
		schemaVersion: 1,
		policyVersion: 1,
		maxAgeDays: 180,
		defaultJurisdiction: '*',
		purposes: [
			{ key: 'necessary', alwaysOn: true },
			{ key: 'functional', alwaysOn: false },
			{ key: 'analytics', alwaysOn: false },
			{ key: 'marketing', alwaysOn: false },
			{ key: 'personalization', alwaysOn: false },
		],
		jurisdictions: {
			'*': { id: '*', label: 'Default (strictest)', policy: optInPolicy() },
			QC: { id: 'QC', label: 'Québec (Loi 25)', policy: optInPolicy() },
			US: { id: 'US', label: 'United States (state opt-out)', policy: optOutPolicy() },
		},
		geo: {
			cookie: '',
			var: '',
			endpoint: '',
			map: { US: 'US', GB: 'UK', FR: 'EU', 'CA-QC': 'QC' },
		},
		proof: {
			enabled: true,
			endpoint: 'https://example.test/wp-json/consentful/v1/consent',
			bannerVersion: 1,
		},
		tags: [
			{ id: 'ga4', purposes: [ 'analytics' ], delivery: 'direct', adapter: 'google' },
		],
		adapters: {
			google: {
				handler: 'google',
				measurementIds: [ 'G-XXXX' ],
				purposeSignals: {
					necessary: [ 'security_storage' ],
					functional: [ 'functionality_storage' ],
					analytics: [ 'analytics_storage' ],
					marketing: [ 'ad_storage', 'ad_user_data', 'ad_personalization' ],
					personalization: [ 'personalization_storage' ],
				},
				adsDataRedaction: true,
				urlPassthrough: true,
				waitForUpdate: 500,
			},
		},
		...overrides,
	};
}

/** Clear cookies, dataLayer, gtag, GPC and the decider emit/registration flags. */
export function resetGlobals() {
	document.cookie.split( ';' ).forEach( ( c ) => {
		const name = c.split( '=' )[ 0 ].trim();
		if ( name ) {
			document.cookie = name + '=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';
		}
	} );
	delete window.dataLayer;
	delete window.gtag;
	delete window.consentful;
	delete window.__consentfulDefaultEmitted;
	if ( 'globalPrivacyControl' in navigator ) {
		delete navigator.globalPrivacyControl;
	}
}

/** Force navigator.globalPrivacyControl for a test. */
export function setGpc( value ) {
	Object.defineProperty( navigator, 'globalPrivacyControl', {
		value,
		configurable: true,
	} );
}

/** Write a v1 cookie payload directly (for "stored decision" tests). */
export function seedCookie( payload ) {
	document.cookie =
		'consentful=' + encodeURIComponent( JSON.stringify( payload ) ) + '; path=/';
}

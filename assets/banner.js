import { coerceBannerConfig, purposeCopy } from './banner-config.js';

/**
 * Initialize the banner against the gate API.
 *
 * @param {object}  api               The window.consentful API.
 * @param {unknown} rawBannerConfig   The raw banner config slice (untrusted — coerced here).
 * @param {object}  env               { win, doc } — only doc is needed.
 * @return {object} A handle { destroy }.
 */
export function initBanner( api, rawBannerConfig, { doc } ) {
	const cfg = coerceBannerConfig( rawBannerConfig );

	const noop = { destroy() {} };
	if ( ! cfg.enabled ) {
		return noop;
	}
	const policy = api.policy();
	if ( ! policy.showsBanner ) {
		return noop;
	}
	if ( api.gpc() ) {
		return noop;
	}

	const purposes = api.purposes();

	const el = ( tag, className, textContent ) => {
		const node = doc.createElement( tag );
		node.className = className;
		if ( textContent != null ) {
			node.textContent = textContent;
		}
		return node;
	};
	const button = ( className, label ) => {
		const b = doc.createElement( 'button' );
		b.type = 'button';
		b.className = className;
		b.textContent = label;
		return b;
	};

	function buildPrefs() {
		const prefs = el( 'div', 'cnf-prefs' );
		prefs.id = 'cnf-prefs';
		prefs.hidden = true;
		prefs.appendChild( el( 'h3', 'cnf-prefs__title', cfg.copy.prefsTitle ) );

		const inputs = {};
		for ( const purpose of purposes ) {
			const copy = purposeCopy( cfg, purpose.key );
			const row = el( 'label', 'cnf-purpose' );
			const input = doc.createElement( 'input' );
			input.type = 'checkbox';
			input.className = 'cnf-purpose__input';
			input.value = purpose.key;
			if ( purpose.alwaysOn ) {
				input.checked = true;
				input.disabled = true;
			}
			inputs[ purpose.key ] = input;

			const text = el( 'span', 'cnf-purpose__text' );
			text.appendChild( el( 'span', 'cnf-purpose__label', copy.label ) );
			text.appendChild( el( 'span', 'cnf-purpose__desc', copy.description ) );

			row.appendChild( input );
			row.appendChild( text );
			prefs.appendChild( row );
		}
		return { prefs, inputs };
	}

	function prefillToggles( inputs ) {
		const current = api.get();
		for ( const purpose of purposes ) {
			if ( purpose.alwaysOn ) {
				continue;
			}
			inputs[ purpose.key ].checked = Boolean( current[ purpose.key ] );
		}
	}

	function readToggles( inputs ) {
		const grants = {};
		for ( const purpose of purposes ) {
			grants[ purpose.key ] = Boolean( inputs[ purpose.key ].checked );
		}
		return grants;
	}

	function buildPill() {
		const pill = button( 'consentful cnf-reopen cnf-banner--theme-' + cfg.theme, cfg.copy.reopen );
		pill.style.setProperty( '--cnf-primary', cfg.primaryColor );
		pill.style.setProperty( '--cnf-radius', cfg.radius + 'px' );
		pill.hidden = true;
		return pill;
	}

	function buildToast() {
		const toast = el( 'div', 'consentful cnf-toast cnf-banner--theme-' + cfg.theme );
		toast.setAttribute( 'role', 'status' );
		toast.setAttribute( 'aria-live', 'polite' );
		toast.hidden = true;
		let hideTimer = null;
		let clearTimer = null;
		const show = () => {
			if ( hideTimer ) {
				clearTimeout( hideTimer );
			}
			if ( clearTimer ) {
				clearTimeout( clearTimer );
			}
			toast.textContent = cfg.copy.saved;
			toast.hidden = false;
			hideTimer = setTimeout( () => {
				toast.hidden = true;
				clearTimer = setTimeout( () => {
					toast.textContent = '';
				}, 250 );
				hideTimer = null;
			}, 2000 );
		};
		const destroy = () => {
			if ( hideTimer ) {
				clearTimeout( hideTimer );
			}
			if ( clearTimer ) {
				clearTimeout( clearTimer );
			}
			if ( toast.parentNode ) {
				toast.parentNode.removeChild( toast );
			}
		};
		return { toast, show, destroy };
	}

	if ( policy.type === 'opt_out' ) {
		return renderOptOut();
	}
	return renderOptIn();

	/**
	 * Opt-in (Loi 25/GDPR): deny-by-default, blocking, modal focus trap + background-inert.
	 *
	 * @return {object} { destroy }.
	 */
	function renderOptIn() {
		const isModal = cfg.position === 'modal';

		let lastFocus = null;
		let wasOpened = false;

		const root = doc.createElement( 'div' );
		root.className =
			'consentful cnf-banner cnf-banner--' + cfg.position + ' cnf-banner--theme-' + cfg.theme;
		root.style.setProperty( '--cnf-primary', cfg.primaryColor );
		root.style.setProperty( '--cnf-radius', cfg.radius + 'px' );
		root.hidden = true;

		const titleId = 'cnf-banner-title';
		const descId = 'cnf-banner-desc';
		if ( isModal ) {
			root.setAttribute( 'role', 'dialog' );
			root.setAttribute( 'aria-modal', 'true' );
			root.setAttribute( 'aria-labelledby', titleId );
			root.setAttribute( 'aria-describedby', descId );
		} else {
			root.setAttribute( 'role', 'region' );
			root.setAttribute( 'aria-label', cfg.copy.title );
		}

		const inner = el( 'div', 'cnf-banner__inner' );
		root.appendChild( inner );

		const title = el( 'h2', 'cnf-banner__title', cfg.copy.title );
		title.id = titleId;
		inner.appendChild( title );

		const desc = el( 'p', 'cnf-banner__desc', cfg.copy.description );
		desc.id = descId;
		inner.appendChild( desc );

		if ( cfg.privacyUrl ) {
			const link = el( 'a', 'cnf-banner__link', cfg.copy.privacyLabel );
			link.href = cfg.privacyUrl;
			inner.appendChild( link );
		}

		const { prefs, inputs } = buildPrefs();
		inner.appendChild( prefs );

		const actions = el( 'div', 'cnf-actions' );
		const customizeBtn = button( 'cnf-btn cnf-btn--ghost cnf-customize', cfg.copy.customize );
		customizeBtn.setAttribute( 'aria-expanded', 'false' );
		customizeBtn.setAttribute( 'aria-controls', prefs.id );
		const rejectBtn = button( 'cnf-btn cnf-btn--reject', cfg.copy.rejectAll );
		const saveBtn = button( 'cnf-btn cnf-btn--save', cfg.copy.save );
		saveBtn.hidden = true;
		const acceptBtn = button( 'cnf-btn cnf-btn--primary', cfg.copy.acceptAll );
		actions.appendChild( customizeBtn );
		actions.appendChild( rejectBtn );
		actions.appendChild( saveBtn );
		actions.appendChild( acceptBtn );
		inner.appendChild( actions );

		const pill = buildPill();
		const toast = buildToast();

		const body = doc.body || doc.documentElement;
		body.appendChild( root );
		body.appendChild( pill );
		body.appendChild( toast.toast );

		function focusables() {
			const sel = 'button, a[href], input, select, textarea, [tabindex]';
			return Array.prototype.filter.call( root.querySelectorAll( sel ), ( node ) => {
				return ! node.disabled && node.tabIndex !== -1 && node.getClientRects().length;
			} );
		}

		function trap( e ) {
			if ( e.key === 'Escape' && api.hasDecision() ) {
				e.preventDefault();
				hidePanel();
				return;
			}
			if ( e.key !== 'Tab' ) {
				return;
			}
			const f = focusables();
			if ( ! f.length ) {
				return;
			}
			const first = f[ 0 ];
			const last = f[ f.length - 1 ];
			if ( e.shiftKey && doc.activeElement === first ) {
				e.preventDefault();
				last.focus();
			} else if ( ! e.shiftKey && doc.activeElement === last ) {
				e.preventDefault();
				first.focus();
			}
		}

		let inerted = [];
		let ariaHidden = [];
		function backgroundInert( on ) {
			if ( ! isModal || ! doc.body ) {
				return;
			}
			if ( ! on ) {
				inerted.forEach( ( node ) => node.removeAttribute( 'inert' ) );
				ariaHidden.forEach( ( node ) => node.removeAttribute( 'aria-hidden' ) );
				inerted = [];
				ariaHidden = [];
				return;
			}
			const kids = doc.body.children;
			for ( let i = 0; i < kids.length; i++ ) {
				const node = kids[ i ];
				if ( node === root || node === pill ) {
					continue;
				}
				if ( ! node.hasAttribute( 'inert' ) ) {
					node.setAttribute( 'inert', '' );
					inerted.push( node );
				}
				if ( ! node.hasAttribute( 'aria-hidden' ) ) {
					node.setAttribute( 'aria-hidden', 'true' );
					ariaHidden.push( node );
				}
			}
		}

		function showPanel( moveFocus ) {
			pill.hidden = true;
			root.hidden = false;
			wasOpened = true;
			if ( isModal ) {
				backgroundInert( true );
				doc.addEventListener( 'keydown', trap );
			}
			if ( moveFocus || isModal ) {
				focusFirst();
			}
		}

		function focusFirst() {
			const target = root.querySelector( '.cnf-btn:not(.cnf-customize)' );
			if ( target ) {
				try {
					target.focus();
				} catch {}
			}
		}

		function hidePanel() {
			root.hidden = true;
			if ( isModal ) {
				doc.removeEventListener( 'keydown', trap );
				backgroundInert( false );
			}
			pill.hidden = false;
			if ( wasOpened ) {
				const restore =
					lastFocus && doc.contains( lastFocus ) && lastFocus.offsetParent !== null
						? lastFocus
						: pill;
				try {
					restore.focus();
				} catch {}
			}
			wasOpened = false;
			lastFocus = null;
		}

		function revealPrefs() {
			prefs.hidden = false;
			saveBtn.hidden = false;
			acceptBtn.hidden = true;
			customizeBtn.hidden = true;
			customizeBtn.setAttribute( 'aria-expanded', 'true' );
			prefillToggles( inputs );
			const firstToggle = purposes.find( ( p ) => ! p.alwaysOn );
			if ( firstToggle ) {
				try {
					inputs[ firstToggle.key ].focus();
				} catch {}
			}
		}

		function openManager( e ) {
			if ( e && e.preventDefault ) {
				e.preventDefault();
			}
			lastFocus = ( e && e.target ) || doc.activeElement || null;
			revealPrefs();
			showPanel( true );
		}

		rejectBtn.addEventListener( 'click', () => {
			api.rejectAll();
			toast.show();
			hidePanel();
		} );
		acceptBtn.addEventListener( 'click', () => {
			api.acceptAll();
			toast.show();
			hidePanel();
		} );
		saveBtn.addEventListener( 'click', () => {
			api.setConsent( readToggles( inputs ) );
			toast.show();
			hidePanel();
		} );
		customizeBtn.addEventListener( 'click', revealPrefs );
		pill.addEventListener( 'click', openManager );

		if ( api.hasDecision() ) {
			hidePanel();
		} else {
			showPanel( isModal );
		}

		function destroy() {
			doc.removeEventListener( 'keydown', trap );
			backgroundInert( false );
			toast.destroy();
			[ root, pill ].forEach( ( node ) => {
				if ( node.parentNode ) {
					node.parentNode.removeChild( node );
				}
			} );
		}

		return { destroy };
	}

	/**
	 * Opt-out (US state laws): allow-by-default, a non-blocking "Do Not Sell or Share" notice.
	 *
	 * @return {object} { destroy }.
	 */
	function renderOptOut() {
		const root = doc.createElement( 'div' );
		root.className =
			'consentful cnf-banner cnf-banner--' +
			cfg.position +
			' cnf-banner--theme-' +
			cfg.theme +
			' cnf-banner--optout';
		root.style.setProperty( '--cnf-primary', cfg.primaryColor );
		root.style.setProperty( '--cnf-radius', cfg.radius + 'px' );
		root.hidden = true;
		root.setAttribute( 'role', 'region' );
		root.setAttribute( 'aria-label', cfg.copy.noticeTitle );

		const inner = el( 'div', 'cnf-banner__inner' );
		root.appendChild( inner );

		inner.appendChild( el( 'h2', 'cnf-banner__title', cfg.copy.noticeTitle ) );
		inner.appendChild( el( 'p', 'cnf-banner__desc', cfg.copy.noticeDescription ) );

		if ( cfg.privacyUrl ) {
			const link = el( 'a', 'cnf-banner__link', cfg.copy.privacyLabel );
			link.href = cfg.privacyUrl;
			inner.appendChild( link );
		}

		const { prefs, inputs } = buildPrefs();
		inner.appendChild( prefs );

		const actions = el( 'div', 'cnf-actions' );
		const customizeBtn = button( 'cnf-btn cnf-btn--ghost cnf-customize', cfg.copy.customize );
		customizeBtn.setAttribute( 'aria-expanded', 'false' );
		customizeBtn.setAttribute( 'aria-controls', prefs.id );
		const dnsBtn = button( 'cnf-btn cnf-btn--optout', cfg.copy.doNotSell );
		const saveBtn = button( 'cnf-btn cnf-btn--save', cfg.copy.save );
		saveBtn.hidden = true;
		const closeBtn = button( 'cnf-btn cnf-btn--ghost', cfg.copy.close );
		actions.appendChild( customizeBtn );
		actions.appendChild( dnsBtn );
		actions.appendChild( saveBtn );
		actions.appendChild( closeBtn );
		inner.appendChild( actions );

		const pill = buildPill();
		const toast = buildToast();

		const body = doc.body || doc.documentElement;
		body.appendChild( root );
		body.appendChild( pill );
		body.appendChild( toast.toast );

		function showPanel( moveFocus ) {
			pill.hidden = true;
			root.hidden = false;
			doc.addEventListener( 'keydown', onKeydown );
			if ( moveFocus ) {
				focusFirst();
			}
		}

		function focusFirst() {
			const target = root.querySelector( '.cnf-btn:not(.cnf-customize)' );
			if ( target ) {
				try {
					target.focus();
				} catch {}
			}
		}

		function hidePanel( restoreFocus ) {
			root.hidden = true;
			doc.removeEventListener( 'keydown', onKeydown );
			prefs.hidden = true;
			saveBtn.hidden = true;
			closeBtn.hidden = false;
			customizeBtn.hidden = false;
			customizeBtn.setAttribute( 'aria-expanded', 'false' );
			pill.hidden = false;
			if ( restoreFocus ) {
				try {
					pill.focus();
				} catch {}
			}
		}

		function revealPrefs() {
			prefs.hidden = false;
			saveBtn.hidden = false;
			closeBtn.hidden = true;
			customizeBtn.hidden = true;
			customizeBtn.setAttribute( 'aria-expanded', 'true' );
			prefillToggles( inputs );
			const firstToggle = purposes.find( ( p ) => ! p.alwaysOn );
			if ( firstToggle ) {
				try {
					inputs[ firstToggle.key ].focus();
				} catch {}
			}
		}

		function onKeydown( e ) {
			if ( e.key === 'Escape' ) {
				e.preventDefault();
				acknowledge();
			}
		}

		function acknowledge() {
			api.setConsent( api.get() );
			toast.show();
			hidePanel( true );
		}

		dnsBtn.addEventListener( 'click', () => {
			api.rejectAll();
			toast.show();
			hidePanel( true );
		} );
		customizeBtn.addEventListener( 'click', revealPrefs );
		saveBtn.addEventListener( 'click', () => {
			api.setConsent( readToggles( inputs ) );
			toast.show();
			hidePanel( true );
		} );
		closeBtn.addEventListener( 'click', acknowledge );
		pill.addEventListener( 'click', ( e ) => {
			if ( e && e.preventDefault ) {
				e.preventDefault();
			}
			showPanel( true );
		} );

		if ( api.hasDecision() ) {
			hidePanel( false );
		} else {
			showPanel( false );
		}

		function destroy() {
			doc.removeEventListener( 'keydown', onKeydown );
			toast.destroy();
			[ root, pill ].forEach( ( node ) => {
				if ( node.parentNode ) {
					node.parentNode.removeChild( node );
				}
			} );
		}

		return { destroy };
	}
}

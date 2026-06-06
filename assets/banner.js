/**
 * Visitor-facing consent banner — rendered by JS at runtime from config, so every
 * visitor still receives identical HTML (cache-safe). It owns no consent state: it reads
 * everything from the gate API (purposes, policy, grants, gpc, hasDecision) and writes
 * every decision back through api.setConsent/acceptAll/rejectAll. It never touches gtag
 * or the cookie directly.
 *
 * Ported behavior from the legacy banner (focus trap, inert background, Esc/implied-
 * consent semantics, reveal-prefs, decide flow), rewired to window.consentful.
 */

import { coerceBannerConfig, purposeCopy } from './banner-config.js';

/**
 * Initialize the banner against the gate API.
 *
 * @param {object}  api               The window.consentful API.
 * @param {unknown} rawBannerConfig   The raw banner config slice (untrusted — coerced here).
 * @param {object}  env               { win, doc } — only doc is needed.
 */
export function initBanner( api, rawBannerConfig, { doc } ) {
	const cfg = coerceBannerConfig( rawBannerConfig );

	if ( ! cfg.enabled ) {
		return;
	}
	// Deferred opt-out seam: only the strict opt-in Policy renders a banner; any other
	// policy type (US Do-Not-Sell notice) is a no-op until that variant lands.
	if ( api.policy().type !== 'opt_in' ) {
		return;
	}
	// GPC is a blanket refusal honored instantly — no banner, no pill (CONTEXT.md).
	if ( api.gpc() ) {
		return;
	}

	const isModal = cfg.position === 'modal';
	const purposes = api.purposes();

	let lastFocus = null; // element that opened the manager, to restore on close
	let wasOpened = false; // true only after a real show (not the passive initial render)

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

	// Preferences — per-purpose rows, revealed by Customize or shown on re-open.
	const prefs = el( 'div', 'cnf-prefs' );
	prefs.id = 'cnf-prefs';
	prefs.hidden = true;
	const prefsTitle = el( 'h3', 'cnf-prefs__title', cfg.copy.prefsTitle );
	prefs.appendChild( prefsTitle );

	const inputs = {}; // purpose key => checkbox
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
	inner.appendChild( prefs );

	// Actions — equal-prominence Reject/Accept enforced in CSS.
	const actions = el( 'div', 'cnf-actions' );
	const rejectBtn = button( 'cnf-btn cnf-btn--reject', cfg.copy.rejectAll );
	const customizeBtn = button( 'cnf-btn cnf-btn--ghost', cfg.copy.customize );
	customizeBtn.setAttribute( 'aria-expanded', 'false' );
	customizeBtn.setAttribute( 'aria-controls', prefs.id );
	const saveBtn = button( 'cnf-btn cnf-btn--save', cfg.copy.save );
	saveBtn.hidden = true;
	const acceptBtn = button( 'cnf-btn cnf-btn--primary', cfg.copy.acceptAll );
	actions.appendChild( rejectBtn );
	actions.appendChild( customizeBtn );
	actions.appendChild( saveBtn );
	actions.appendChild( acceptBtn );
	inner.appendChild( actions );

	// Re-open / withdraw pill, lives outside the panel so it survives panel hide.
	const pill = button( 'consentful cnf-reopen cnf-banner--theme-' + cfg.theme, cfg.copy.reopen );
	pill.style.setProperty( '--cnf-primary', cfg.primaryColor );
	pill.style.setProperty( '--cnf-radius', cfg.radius + 'px' );
	pill.hidden = true;

	const body = doc.body || doc.documentElement;
	body.appendChild( root );
	body.appendChild( pill );

	// Only currently-visible, focusable controls — a static querySelectorAll would
	// include the [hidden] preference inputs and break the modal trap boundary.
	function focusables() {
		const sel = 'button, a[href], input, select, textarea, [tabindex]';
		return Array.prototype.filter.call( root.querySelectorAll( sel ), ( node ) => {
			return ! node.disabled && node.tabIndex !== -1 && node.getClientRects().length;
		} );
	}

	function trap( e ) {
		// Esc closes only once a decision exists; the first-visit gate is not Esc-
		// dismissable (no implied consent).
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

	// Make the rest of the page inert so AT/keyboard cannot reach it behind the modal.
	// Only touch attributes we add ourselves: if the site already hid/inerted an element
	// we leave it alone on open and on close, so we never re-expose content it hid.
	let inerted = []; // elements we set `inert` on
	let ariaHidden = []; // elements we set `aria-hidden` on
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
		// Steal focus only when explicitly opened or modal — never on a passive
		// bar/corner first render.
		if ( moveFocus || isModal ) {
			focusFirst();
		}
	}

	function focusFirst() {
		const target = root.querySelector( '.cnf-btn' );
		if ( target ) {
			try {
				target.focus();
			} catch {
				// jsdom or detached node — focus is best-effort.
			}
		}
	}

	function hidePanel() {
		root.hidden = true;
		if ( isModal ) {
			doc.removeEventListener( 'keydown', trap );
			backgroundInert( false );
		}
		pill.hidden = false;
		// Restore focus to the opener (APG) — but never on the initial already-decided
		// hide at load (wasOpened false), which would yank focus onto the pill.
		if ( wasOpened ) {
			const restore =
				lastFocus && doc.contains( lastFocus ) && lastFocus.offsetParent !== null
					? lastFocus
					: pill;
			try {
				restore.focus();
			} catch {
				// Focus is best-effort.
			}
		}
		wasOpened = false;
		lastFocus = null;
	}

	function revealPrefs() {
		prefs.hidden = false;
		saveBtn.hidden = false;
		customizeBtn.setAttribute( 'aria-expanded', 'true' );
		// Prefill from the live grants so re-opening reflects the current decision.
		const current = api.get();
		for ( const purpose of purposes ) {
			if ( purpose.alwaysOn ) {
				continue;
			}
			inputs[ purpose.key ].checked = Boolean( current[ purpose.key ] );
		}
		const firstToggle = purposes.find( ( p ) => ! p.alwaysOn );
		if ( firstToggle ) {
			try {
				inputs[ firstToggle.key ].focus();
			} catch {
				// Focus is best-effort.
			}
		}
	}

	function readToggles() {
		const grants = {};
		for ( const purpose of purposes ) {
			grants[ purpose.key ] = Boolean( inputs[ purpose.key ].checked );
		}
		return grants;
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
		hidePanel();
	} );
	acceptBtn.addEventListener( 'click', () => {
		api.acceptAll();
		hidePanel();
	} );
	saveBtn.addEventListener( 'click', () => {
		api.setConsent( readToggles() );
		hidePanel();
	} );
	customizeBtn.addEventListener( 'click', revealPrefs );
	pill.addEventListener( 'click', openManager );

	// Initial state: gate the panel on a prior decision; otherwise reveal the pill.
	if ( api.hasDecision() ) {
		hidePanel();
	} else {
		showPanel( isModal );
	}
}

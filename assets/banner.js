/**
 * Visitor-facing consent banner — rendered by JS at runtime from config, so every
 * visitor still receives identical HTML (cache-safe). It owns no consent state: it reads
 * everything from the gate API (purposes, policy, grants, gpc, hasDecision) and writes
 * every decision back through api.setConsent/acceptAll/rejectAll. It never touches gtag
 * or the cookie directly.
 *
 * One banner area, three Policy-driven shapes (ADR 0002): the blocking opt-in gate
 * (renderOptIn — Loi 25/GDPR), the non-blocking US "Do Not Sell/Share" notice
 * (renderOptOut), and notice_only which renders nothing.
 */

import { coerceBannerConfig, purposeCopy } from './banner-config.js';

/**
 * Initialize the banner against the gate API.
 *
 * @param {object}  api               The window.consentful API.
 * @param {unknown} rawBannerConfig   The raw banner config slice (untrusted — coerced here).
 * @param {object}  env               { win, doc } — only doc is needed.
 * @return {object} A handle { destroy } — destroy() fully tears down the banner so a
 *                  re-init after a jurisdiction change leaves no duplicate nodes/listeners.
 */
export function initBanner( api, rawBannerConfig, { doc } ) {
	const cfg = coerceBannerConfig( rawBannerConfig );

	const noop = { destroy() {} };
	if ( ! cfg.enabled ) {
		return noop;
	}
	const policy = api.policy();
	// notice_only: the site's privacy-policy link is the notice — no banner, no pill.
	if ( ! policy.showsBanner ) {
		return noop;
	}
	// GPC is honored instantly for every variant — a blanket refusal / DNS already
	// exercised, so neither the opt-in gate nor the opt-out notice is shown (CONTEXT.md).
	if ( api.gpc() ) {
		return noop;
	}

	// Shared builders for both variants. `purposes` and the prefs block are identical;
	// the variants differ only in their actions/flow and (for opt-in) the modal trap.
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

	// Build the per-purpose preferences block once; returns the prefs node + its inputs so
	// the variant can prefill, read toggles, and reveal it.
	function buildPrefs() {
		const prefs = el( 'div', 'cnf-prefs' );
		prefs.id = 'cnf-prefs';
		prefs.hidden = true;
		prefs.appendChild( el( 'h3', 'cnf-prefs__title', cfg.copy.prefsTitle ) );

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
		return { prefs, inputs };
	}

	// Prefill non-essential toggles from the live grants so re-opening (or revealing under
	// opt-out's all-on default) reflects the current decision.
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

	// Re-open / withdraw pill, lives outside the panel so it survives panel hide.
	function buildPill() {
		const pill = button( 'consentful cnf-reopen cnf-banner--theme-' + cfg.theme, cfg.copy.reopen );
		pill.style.setProperty( '--cnf-primary', cfg.primaryColor );
		pill.style.setProperty( '--cnf-radius', cfg.radius + 'px' );
		pill.hidden = true;
		return pill;
	}

	// Confirmation toast — a polite live region each variant shows on commit. Built once
	// per init; torn down in destroy().
	function buildToast() {
		const toast = el( 'div', 'consentful cnf-toast cnf-banner--theme-' + cfg.theme );
		toast.setAttribute( 'role', 'status' );
		toast.setAttribute( 'aria-live', 'polite' );
		toast.hidden = true;
		let hideTimer = null;
		let clearTimer = null;
		// Reveal immediately, fade out after a beat. The text outlives the fade-out (cleared
		// only once it finishes) so it fades WITH the box, not before it. Never steals focus.
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
	// opt_in (and any unknown type defaults to the strict variant — fail-closed).
	return renderOptIn();

	/**
	 * Opt-in (Loi 25/GDPR): deny-by-default, blocking, equal-prominence Reject/Accept,
	 * modal focus trap + background-inert. UNCHANGED behavior — only relocated into a
	 * function and wired to the shared builders above.
	 *
	 * @return {object} { destroy }.
	 */
	function renderOptIn() {
		const isModal = cfg.position === 'modal';

		let lastFocus = null; // element that opened the manager, to restore on close
		let wasOpened = false; // true only after a real show (not the passive initial render)

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

		// Preferences — per-purpose rows, revealed by the Customize link or shown on re-open.
		const { prefs, inputs } = buildPrefs();

		// Customize is a text link, not a row button — keeps the action row an equal pair.
		const customizeBtn = button( 'cnf-customize', cfg.copy.customize );
		customizeBtn.setAttribute( 'aria-expanded', 'false' );
		customizeBtn.setAttribute( 'aria-controls', prefs.id );
		inner.appendChild( customizeBtn );

		inner.appendChild( prefs );

		// Actions — equal-prominence Reject/Accept enforced in CSS.
		const actions = el( 'div', 'cnf-actions' );
		const rejectBtn = button( 'cnf-btn cnf-btn--reject', cfg.copy.rejectAll );
		const saveBtn = button( 'cnf-btn cnf-btn--save', cfg.copy.save );
		saveBtn.hidden = true;
		const acceptBtn = button( 'cnf-btn cnf-btn--primary', cfg.copy.acceptAll );
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
			// Save is the sole commit here; hide Accept so it can't discard the new toggles.
			acceptBtn.hidden = true;
			customizeBtn.setAttribute( 'aria-expanded', 'true' );
			prefillToggles( inputs );
			const firstToggle = purposes.find( ( p ) => ! p.alwaysOn );
			if ( firstToggle ) {
				try {
					inputs[ firstToggle.key ].focus();
				} catch {
					// Focus is best-effort.
				}
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

		// Initial state: gate the panel on a prior decision; otherwise reveal the pill.
		if ( api.hasDecision() ) {
			hidePanel();
		} else {
			showPanel( isModal );
		}

		// Full teardown: drop the keydown trap, restore any inert/aria-hidden we added, and
		// remove our own nodes. Idempotent.
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
	 * Opt-out (US state laws): allow-by-default, an informational notice that surfaces the
	 * "Do Not Sell or Share" right + manage-prefs. Genuinely non-blocking — role="region"
	 * (never dialog), no aria-modal, no focus trap, no background-inert, even at
	 * position 'modal'. Dismissible (no prior-consent requirement). That non-blocking
	 * property is the compliance point of the variant.
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

		// Preferences (all non-essential on by default under opt-out).
		const { prefs, inputs } = buildPrefs();

		// Customize is a text link, not a row button — keeps the action row an equal pair.
		const customizeBtn = button( 'cnf-customize', cfg.copy.customize );
		customizeBtn.setAttribute( 'aria-expanded', 'false' );
		customizeBtn.setAttribute( 'aria-controls', prefs.id );
		inner.appendChild( customizeBtn );

		inner.appendChild( prefs );

		// Actions: the conspicuous DNS control + Close; revealing prefs swaps Close for Save.
		const actions = el( 'div', 'cnf-actions' );
		const dnsBtn = button( 'cnf-btn cnf-btn--optout', cfg.copy.doNotSell );
		const saveBtn = button( 'cnf-btn cnf-btn--save', cfg.copy.save );
		saveBtn.hidden = true;
		const closeBtn = button( 'cnf-btn cnf-btn--ghost', cfg.copy.close );
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
			const target = root.querySelector( '.cnf-btn' );
			if ( target ) {
				try {
					target.focus();
				} catch {
					// jsdom or detached node — focus is best-effort.
				}
			}
		}

		// Collapse prefs so a later pill re-open starts fresh (notice + actions); restore
		// focus to the pill on an explicit dismissal, but never on the passive load-time
		// hide for a returning visitor (which would yank focus onto the pill).
		function hidePanel( restoreFocus ) {
			root.hidden = true;
			doc.removeEventListener( 'keydown', onKeydown );
			prefs.hidden = true;
			saveBtn.hidden = true;
			closeBtn.hidden = false;
			customizeBtn.setAttribute( 'aria-expanded', 'false' );
			pill.hidden = false;
			if ( restoreFocus ) {
				try {
					pill.focus();
				} catch {
					// Focus is best-effort (jsdom / detached node).
				}
			}
		}

		function revealPrefs() {
			prefs.hidden = false;
			saveBtn.hidden = false;
			// Save is the commit while prefs are open — hide Close (keeps the row two buttons).
			closeBtn.hidden = true;
			customizeBtn.setAttribute( 'aria-expanded', 'true' );
			prefillToggles( inputs );
			const firstToggle = purposes.find( ( p ) => ! p.alwaysOn );
			if ( firstToggle ) {
				try {
					inputs[ firstToggle.key ].focus();
				} catch {
					// Focus is best-effort.
				}
			}
		}

		// Esc acknowledges the notice and hides it — dismissible, since opt-out has no
		// prior-consent requirement (no focus trap; this is the only key we watch).
		function onKeydown( e ) {
			if ( e.key === 'Escape' ) {
				e.preventDefault();
				acknowledge();
			}
		}

		// Persist the current allow-by-default grants so the notice doesn't re-nag. Opt-out
		// regimes permit default-on; this is acknowledgement, not prohibited implied consent.
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
		// The pill is how a visitor exercises DNS after dismissing — re-open, move focus.
		pill.addEventListener( 'click', ( e ) => {
			if ( e && e.preventDefault ) {
				e.preventDefault();
			}
			showPanel( true );
		} );

		// Initial state: returning visitor sees the pill only; otherwise show the notice
		// passively (no focus steal — it's a non-blocking notice).
		if ( api.hasDecision() ) {
			hidePanel( false );
		} else {
			showPanel( false );
		}

		// Teardown: drop the keydown listener and remove our own nodes. No trap/inert to
		// undo (the opt-out notice never installs them). Idempotent.
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

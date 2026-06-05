/* Consent Mode v2 — banner logic. Mirrors the head-script consent mapping. */
(function () {
	'use strict';

	var CFG = window.CMV2_CONSENT || {};
	var COOKIE = CFG.cookie || 'cmv2_consent';
	// wp_localize_script stringifies values ("0" is truthy in JS) — coerce explicitly.
	var DAYS = parseInt(CFG.days, 10) > 0 ? parseInt(CFG.days, 10) : 180;
	var ADS = CFG.ads === 1 || CFG.ads === '1' || CFG.ads === true;
	var SCHEMA_V = parseInt(CFG.v, 10) > 0 ? parseInt(CFG.v, 10) : 1;
	var IS_MODAL = CFG.modal === 1 || CFG.modal === '1' || CFG.modal === true;
	var SECURE = window.location.protocol === 'https:'; // derive, never trust a stringified flag

	// gtag is defined by the head script (function declaration -> window.gtag).
	function gtagFallback() { (window.dataLayer = window.dataLayer || []).push(arguments); }
	var gtag = window.gtag || gtagFallback;

	// Returns stored consent only if it is current-schema AND not past the
	// re-consent window; otherwise null (treated as "no decision" -> banner shows).
	function read() {
		try {
			var m = document.cookie.match(new RegExp('(?:^|;\\s*)' + COOKIE + '=([^;]+)'));
			if (!m) { return null; }
			var v = JSON.parse(decodeURIComponent(m[1]));
			if (!v || !v.c) { return null; }
			if (v.v !== SCHEMA_V) { return null; }
			if (!v.t || (Date.now() - v.t) > DAYS * 86400000) { return null; }
			return v;
		} catch (e) { return null; }
	}

	function write(c) {
		var payload = { v: SCHEMA_V, c: c, t: Date.now() };
		var d = new Date();
		d.setTime(d.getTime() + DAYS * 86400000);
		document.cookie = COOKIE + '=' + encodeURIComponent(JSON.stringify(payload)) +
			'; expires=' + d.toUTCString() + '; path=/; SameSite=Lax' + (SECURE ? '; Secure' : '');
	}

	function apply(c) {
		var u = { analytics_storage: c.analytics ? 'granted' : 'denied' };
		if (ADS) {
			u.ad_storage = c.marketing ? 'granted' : 'denied';
			u.ad_user_data = c.marketing ? 'granted' : 'denied';
			u.ad_personalization = c.marketing ? 'granted' : 'denied';
			u.personalization_storage = c.marketing ? 'granted' : 'denied';
		}
		gtag('consent', 'update', u);
	}

	function init() {
		var banner = document.getElementById('cmv2-consent');
		if (!banner) { return; } // markup missing — nothing to wire
		var prefs = document.getElementById('cmv2-consent-prefs');
		var openBtn = document.getElementById('cmv2-consent-open');
		var saveBtn = banner.querySelector('[data-cmv2="save"]');
		var customizeBtn = banner.querySelector('[data-cmv2="customize"]');
		var catA = document.getElementById('cmv2-cat-analytics');
		var catM = document.getElementById('cmv2-cat-marketing');

		var lastFocus = null;   // element that opened the manager, to restore on close
		var wasOpened = false;  // true only after a real show (not the initial bail)

		// Only currently-visible, focusable controls — querySelectorAll is static and
		// would otherwise include the display:none category inputs (parent is [hidden]),
		// breaking the modal focus-trap boundary.
		function focusables() {
			var sel = 'button, a[href], input, select, textarea, [tabindex]';
			return Array.prototype.filter.call(banner.querySelectorAll(sel), function (el) {
				return !el.disabled && el.tabIndex !== -1 && el.getClientRects().length;
			});
		}
		function trap(e) {
			// Esc closes only the RE-OPENED manager (a prior decision exists); the
			// first-visit gate must not be Esc-dismissable (no implied consent).
			if (e.key === 'Escape' && read()) { e.preventDefault(); hideBanner(); return; }
			if (e.key !== 'Tab') { return; }
			var f = focusables();
			if (!f.length) { return; }
			var first = f[0], last = f[f.length - 1];
			if (e.shiftKey && document.activeElement === first) {
				e.preventDefault(); last.focus();
			} else if (!e.shiftKey && document.activeElement === last) {
				e.preventDefault(); first.focus();
			}
		}

		// Modal must make the rest of the page inert so AT/keyboard cannot reach it.
		function backgroundInert(on) {
			if (!IS_MODAL || !document.body) { return; }
			var kids = document.body.children;
			for (var i = 0; i < kids.length; i++) {
				if (kids[i] === banner) { continue; }
				if (on) { kids[i].setAttribute('inert', ''); kids[i].setAttribute('aria-hidden', 'true'); }
				else { kids[i].removeAttribute('inert'); kids[i].removeAttribute('aria-hidden'); }
			}
		}

		function showBanner(moveFocus) {
			if (openBtn) { openBtn.hidden = true; }
			banner.hidden = false;
			wasOpened = true;
			if (IS_MODAL) {
				backgroundInert(true);
				document.addEventListener('keydown', trap);
			}
			// Only steal focus when explicitly opened or when modal (a modal is
			// meant to take focus) — never on a passive bar/corner first render.
			if (moveFocus || IS_MODAL) {
				var f = banner.querySelector('.cmv2__btn');
				if (f) { try { f.focus(); } catch (e) {} }
			}
		}
		function hideBanner() {
			banner.hidden = true;
			if (IS_MODAL) {
				document.removeEventListener('keydown', trap);
				backgroundInert(false);
			}
			if (openBtn) { openBtn.hidden = false; }
			// Restore focus to the opener (APG) — but never on the initial "already
			// consented" hide at load (wasOpened false), which would yank focus to the pill.
			if (wasOpened) {
				if (lastFocus && document.contains(lastFocus) && lastFocus.offsetParent !== null) {
					try { lastFocus.focus(); } catch (e) {}
				} else if (openBtn) {
					try { openBtn.focus(); } catch (e) {}
				}
			}
			wasOpened = false;
			lastFocus = null;
		}
		function decide(c) {
			write(c);
			apply(c);
			// Basic consent mode: load the Google tag only once a purpose is granted.
			if (c.analytics || (ADS && c.marketing)) {
				if (typeof window.cmv2LoadTag === 'function') { window.cmv2LoadTag(); }
			}
			hideBanner();
		}

		function revealPrefs() {
			if (prefs) { prefs.hidden = false; }
			if (saveBtn) { saveBtn.hidden = false; }
			if (customizeBtn) { customizeBtn.setAttribute('aria-expanded', 'true'); }
			var stored = read();
			if (stored) {
				if (catA) { catA.checked = !!stored.c.analytics; }
				if (catM) { catM.checked = !!stored.c.marketing; }
			}
			if (catA) { try { catA.focus(); } catch (e) {} } // move focus into the revealed group
		}

		banner.addEventListener('click', function (e) {
			var btn = e.target.closest ? e.target.closest('[data-cmv2]') : null;
			if (!btn) { return; }
			switch (btn.getAttribute('data-cmv2')) {
				case 'accept':
					decide({ necessary: true, analytics: true, marketing: true });
					break;
				case 'reject':
					decide({ necessary: true, analytics: false, marketing: false });
					break;
				case 'customize':
					revealPrefs();
					break;
				case 'save':
					decide({
						necessary: true,
						analytics: catA ? !!catA.checked : false,
						marketing: catM ? !!catM.checked : false
					});
					break;
			}
		});

		function openManager(e) {
			if (e && e.preventDefault) { e.preventDefault(); }
			lastFocus = (e && e.target) || document.activeElement || null;
			revealPrefs();
			showBanner(true);
		}
		if (openBtn) { openBtn.addEventListener('click', openManager); }
		// CSP-friendly menu integration: any element with [data-cmv2-open] re-opens
		// the manager. e.g. <a href="#" data-cmv2-open>Manage cookies</a>
		var openers = document.querySelectorAll('[data-cmv2-open]');
		for (var i = 0; i < openers.length; i++) {
			openers[i].addEventListener('click', openManager);
		}
		window.cmv2Consent = { open: function () { openManager(); } };

		if (read()) { hideBanner(); } else { showBanner(IS_MODAL); }
	}

	// Run after the banner markup exists, regardless of footer script print order.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();

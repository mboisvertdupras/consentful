=== Consent Mode v2 — Loi 25 & RGPD ===
Contributors: tamarak
Tags: consent mode, gdpr, loi 25, ga4, cookie banner, google analytics, consent
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Google Consent Mode v2 with a block-before-consent GA4 tag and a customizable banner that meets Québec Loi 25 / GDPR.

== Description ==

A single, theme-independent plugin that owns the site's Google tag and only loads it AFTER the visitor makes a choice — what makes "prior consent" true under Loi 25 and GDPR.

* **Block before consent (basic Consent Mode v2).** No `gtag.js`, no `config`, and no cookieless ping fire until the visitor consents. A returning visitor with a valid, unexpired consent gets the tag on the first hit.
* **Consent Mode v2 signals.** Sets all four required signals (`ad_storage`, `ad_user_data`, `ad_personalization`, `analytics_storage`) plus `functionality_storage`, `personalization_storage`, `security_storage`.
* **Loi 25 friendly banner.** "Reject all" is as easy as "Accept all" — same screen, one click, identical size and weight — plus granular per-category preferences, easy withdrawal, no pre-ticked boxes, and a re-consent window.
* **Fully customizable.** Primary color, light / dark / auto theme, position (bottom bar, floating corner card, centered modal), button corner radius, custom heading & copy, optional floating re-open button.
* **Translation-ready.** Ships English (source) and French (Loi 25) strings; `.pot` template included. Banner language follows the site locale or can be pinned to FR/EN.
* **Accessible.** Keyboard-operable, focus management, focus trap in modal mode, 44px touch targets, respects `prefers-reduced-motion`.

== Important: one tag only ==

This plugin is the SINGLE source of the site's Google tag. Do NOT also inject `gtag.js` via Insert Headers & Footers, Site Kit, GTM, Google Listings & Ads, etc. — that would double-count and bypass consent. Admin warnings appear if a conflicting tag-emitting plugin is active.

== Installation ==

1. Upload the `consent-mode-v2` folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Go to **Settings → Consent Mode v2** and enter your GA4 measurement ID (`G-XXXXXXXXXX`).
4. (Optional) Enable advertising signals if the site runs Google Ads.
5. Customize color, theme, position, and copy.

== Frequently Asked Questions ==

= Does the tag load before consent? =
No. In basic mode the tag is not added to the page until the visitor grants at least one purpose.

= Can I re-open the consent manager from a menu? =
Yes. Either keep the floating re-open button, or add any link/button with the `data-cmv2-open` attribute (e.g. in the footer menu). `window.cmv2Consent.open()` also works.

= Does it handle the Meta pixel or other non-Google tags? =
No. Consent Mode v2 governs Google tags only. Wire other tags (e.g. the Meta pixel) to your consent signals separately.

== Changelog ==

= 2.0.0 =
* Generalized from a single-site plugin into a reusable, white-label plugin.
* Added theming: primary color, light/dark/auto, position, button radius, custom copy, optional re-open button.
* Added translation-ready i18n with bundled FR + EN.

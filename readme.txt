=== Consentful ===
Contributors: tamarak
Tags: consent, gdpr, loi 25, cookie banner, consent mode
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A universal consent layer: gate non-essential tags behind visitor consent, adapt to each visitor's jurisdiction, and keep proof — no code needed.

== Description ==

Consentful is an open-source, general-purpose universal consent layer that anyone can install and configure from the WordPress admin — no code or prior consent-management knowledge required. It gates ALL non-essential third-party tags behind visitor consent, adapts to the visitor's jurisdiction, and keeps demonstrable proof of consent — so the SITE (not merely one vendor's tag) meets Québec Loi 25, the GDPR, and US opt-out laws. Google Consent Mode is the first integration, not the boundary.

* **No code, compliant by default.** Install, activate, and you have a working compliant baseline; add tags, edit purpose copy, and style the banner from the admin UI — no developer required.
* **Gate every non-essential tag.** Each tag is assigned to one or more purposes and fires only when all are granted — either Direct (a Consentful adapter injects it) or Delegated (an external tag manager fires it, gated via a consent push to the dataLayer).
* **Geo-adaptive, multi-jurisdiction.** A Policy is Opt-in (deny by default, banner, block-before-consent — Loi 25/GDPR), Opt-out (allow by default, notice + Do Not Sell/Share + honor GPC — US), or Notice/None. Until the region is known, the strictest policy applies (fail-closed); GPC is honored instantly.
* **Cache-safe by design.** Every visitor receives identical HTML; an inline `<head>` decider plus per-adapter JS reads the consent cookie at runtime and injects only granted tags — correct behind full-page caches / CDNs.
* **Google Consent Mode v2.** Google is just a rich adapter that additionally emits Consent Mode v2 signals (default-deny, `wait_for_update`, cookieless pings, `ads_data_redaction`, `url_passthrough`) to preserve conversion modeling.
* **Proof of consent.** Each decision is recorded (consent id, timestamp, purposes, jurisdiction, policy/schema/banner version) to a built-in consent log, exportable for an auditor; a Sink interface lets developers redirect records to their own store.
* **Translation-ready.** Ships English (source) and French (fr_CA / fr_FR) strings; `.pot` template included. Language (locale) is a separate axis from jurisdiction (geo).

== What's in 1.0.0 ==

Consentful 1.0.0 is a complete, self-serve consent layer: the cache-safe client gate (an inline `<head>` decider plus a footer gate bundle), the geo-adaptive multi-jurisdiction policy engine (opt-in / opt-out / notice, strictest-until-known, GPC honored), the built-in tag catalog (Google Analytics 4, Google Ads, Google Tag Manager, Meta Pixel) plus multi-tag custom snippets, the Google Consent Mode v2 adapter, the proof-of-consent log with CSV export and retention purge, and the native admin settings UI — all configurable without code.

== Installation ==

1. Upload the `consentful` folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Open Settings -> Consentful, add your tags (GA4, Google Tag Manager, Meta Pixel, or a custom snippet), and review the banner. A compliant baseline is active from activation; configuration just refines it.

== Frequently Asked Questions ==

= Is this only for Google tags? =
No. Consentful is a universal consent layer that gates any non-essential third-party tag. Google Consent Mode is the first adapter, not the boundary.

= Does it work behind a full-page cache or CDN? =
Yes. Every visitor receives identical HTML; the client-side decider reads the consent cookie at runtime and injects only granted tags, so caching stays intact.

= Who is the audience? =
Anyone running a WordPress site. Tags, purposes, jurisdiction policy, and the banner are configured from the admin UI — no code or consent-management expertise required. Developers can optionally extend it (custom integrations, a custom record store) via hooks.

== Changelog ==

= 1.0.0 =
* Initial release.
* Self-serve admin UI: configure tags, purpose copy, banner appearance, and jurisdiction posture with no code.
* Cache-safe client gate: identical HTML for every visitor; the consent cookie is read at runtime and only granted tags are injected.
* Built-in tag catalog (GA4, Google Ads, Google Tag Manager, Meta Pixel) plus multi-tag custom snippets with a head / body / footer injection location.
* Google Consent Mode v2 signals (default-deny, wait_for_update, ads_data_redaction, url_passthrough); GTM containers are loaded behind consent.
* Geo-adaptive, multi-jurisdiction policy engine (Loi 25 / GDPR opt-in, US opt-out, notice-only) with fail-closed strictest-until-known resolution and instant GPC.
* Proof-of-consent log with CSV export, a daily retention purge, and a Sink hook for a custom record store.
* English source plus bundled French (fr_CA / fr_FR).

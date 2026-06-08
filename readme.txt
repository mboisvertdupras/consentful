=== Consentful ===
Contributors: tamarak
Tags: consent, gdpr, loi 25, cookie banner, consent mode
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A general-purpose WordPress universal consent layer that gates non-essential third-party tags behind visitor consent and adapts to the visitor's jurisdiction (Loi 25 / GDPR / US opt-out) — no code required.

== Description ==

Consentful is an open-source, general-purpose universal consent layer that anyone can install and configure from the WordPress admin — no code or prior consent-management knowledge required. It gates ALL non-essential third-party tags behind visitor consent, adapts to the visitor's jurisdiction, and keeps demonstrable proof of consent — so the SITE (not merely one vendor's tag) meets Québec Loi 25, the GDPR, and US opt-out laws. Google Consent Mode is the first integration, not the boundary.

* **No code, compliant by default.** Install, activate, and you have a working compliant baseline; add tags, edit purpose copy, and style the banner from the admin UI — no developer required.
* **Gate every non-essential tag.** Each tag is assigned to one or more purposes and fires only when all are granted — either Direct (a Consentful adapter injects it) or Delegated (an external tag manager fires it, gated via a consent push to the dataLayer).
* **Geo-adaptive, multi-jurisdiction.** A Policy is Opt-in (deny by default, banner, block-before-consent — Loi 25/GDPR), Opt-out (allow by default, notice + Do Not Sell/Share + honor GPC — US), or Notice/None. Until the region is known, the strictest policy applies (fail-closed); GPC is honored instantly.
* **Cache-safe by design.** Every visitor receives identical HTML; an inline `<head>` decider plus per-adapter JS reads the consent cookie at runtime and injects only granted tags — correct behind full-page caches / CDNs.
* **Google Consent Mode v2.** Google is just a rich adapter that additionally emits Consent Mode v2 signals (default-deny, `wait_for_update`, cookieless pings, `ads_data_redaction`, `url_passthrough`) to preserve conversion modeling.
* **Proof of consent.** Each decision is recorded (consent id, timestamp, purposes, jurisdiction, policy/schema/banner version) to a built-in consent log, exportable for an auditor; a Sink interface lets developers redirect records to their own store.
* **Translation-ready.** Ships English (source) and French (fr_CA / fr_FR) strings; `.pot` template included. Language (locale) is a separate axis from jurisdiction (geo).

== Foundation release ==

This 1.0.0 is the first increment of a ground-up rewrite: it stands up the PSR-4 OOP domain core (container, Purpose model, Signal, Consent, Tag, Adapter, Jurisdiction/Policy registries) and the rebranded build/packaging surface. The front-end gate, the Google adapter, jurisdiction geo-resolution, the consent log, and the admin UI land in later increments.

== Installation ==

1. Upload the `consentful` folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Open Settings -> Consentful, add your tags (GA4, Meta Pixel, or a custom snippet), and review the banner. A compliant baseline is active from activation; configuration just refines it.

== Frequently Asked Questions ==

= Is this only for Google tags? =
No. Consentful is a universal consent layer that gates any non-essential third-party tag. Google Consent Mode is the first adapter, not the boundary.

= Does it work behind a full-page cache or CDN? =
Yes. Every visitor receives identical HTML; the client-side decider reads the consent cookie at runtime and injects only granted tags, so caching stays intact.

= Who is the audience? =
Anyone running a WordPress site. Tags, purposes, jurisdiction policy, and the banner are configured from the admin UI — no code or consent-management expertise required. Developers can optionally extend it (custom integrations, a custom record store) via hooks.

== Changelog ==

= 1.0.0 =
* Foundation release of the Consentful rewrite.
* PSR-4 OOP domain core: container, Purpose model, Signal, Consent value object, Tag/Adapter/Jurisdiction/Policy and their registries, and the Plugin bootstrap.
* Rebranded the build, packaging, and tooling surface to the `consentful` slug.

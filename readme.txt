=== Consentful ===
Contributors: tamarak
Tags: consent, gdpr, loi 25, cookie banner, consent mode
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A white-label WordPress universal consent layer that gates non-essential third-party tags behind visitor consent and adapts to the visitor's jurisdiction (Loi 25 / GDPR / US opt-out).

== Description ==

Consentful is a white-label, open-source universal consent layer. It gates ALL non-essential third-party tags behind visitor consent, adapts to the visitor's jurisdiction, and keeps demonstrable proof of consent — so the SITE (not merely one vendor's tag) meets Québec Loi 25, the GDPR, and US opt-out laws. Google Consent Mode is the first integration, not the boundary.

* **Gate every non-essential tag.** Each tag is assigned to one or more purposes and fires only when all are granted — either Direct (a Consentful adapter injects it) or Delegated (an external tag manager fires it, gated via a consent push to the dataLayer).
* **Geo-adaptive, multi-jurisdiction.** A Policy is Opt-in (deny by default, banner, block-before-consent — Loi 25/GDPR), Opt-out (allow by default, notice + Do Not Sell/Share + honor GPC — US), or Notice/None. Until the region is known, the strictest policy applies (fail-closed); GPC is honored instantly.
* **Cache-safe by design.** Every visitor receives identical HTML; an inline `<head>` decider plus per-adapter JS reads the consent cookie at runtime and injects only granted tags — correct behind full-page caches / CDNs.
* **Google Consent Mode v2.** Google is just a rich adapter that additionally emits Consent Mode v2 signals (default-deny, `wait_for_update`, cookieless pings, `ads_data_redaction`, `url_passthrough`) to preserve conversion modeling.
* **Proof of consent.** Each decision is recorded (consent id, timestamp, purposes, jurisdiction, policy/schema/banner version) to a built-in consent log, exportable for an auditor; a Sink interface lets integrators redirect records to their own store.
* **Translation-ready.** Ships English (source) and French (fr_CA / fr_FR) strings; `.pot` template included. Language (locale) is a separate axis from jurisdiction (geo).

== Foundation release ==

This 1.0.0 is the first increment of a ground-up rewrite: it stands up the PSR-4 OOP domain core (container, Purpose model, Signal, Consent, Tag, Adapter, Jurisdiction/Policy registries) and the rebranded build/packaging surface. The front-end gate, the Google adapter, jurisdiction geo-resolution, the consent log, and the admin UI land in later increments.

== Installation ==

1. Upload the `consentful` folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Integrators declare adapters, tags, purpose mappings, and jurisdiction policy in code/config — the source of truth.

== Frequently Asked Questions ==

= Is this only for Google tags? =
No. Consentful is a universal consent layer that gates any non-essential third-party tag. Google Consent Mode is the first adapter, not the boundary.

= Does it work behind a full-page cache or CDN? =
Yes. Every visitor receives identical HTML; the client-side decider reads the consent cookie at runtime and injects only granted tags, so caching stays intact.

= Who is the audience? =
Integrators (agencies and developers). Adapters, tags, purpose mappings, and jurisdiction policy are declared in code/config, and any setting can be locked; the site owner gets a deliberately constrained admin UI.

== Changelog ==

= 1.0.0 =
* Foundation release of the Consentful rewrite.
* PSR-4 OOP domain core: container, Purpose model, Signal, Consent value object, Tag/Adapter/Jurisdiction/Policy and their registries, and the Plugin bootstrap.
* Rebranded the build, packaging, and tooling surface to the `consentful` slug.

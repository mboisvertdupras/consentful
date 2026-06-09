# Consentful

A general-purpose, open-source WordPress plugin (built for the WordPress.org
directory) that acts as a universal consent layer: it gates **all** non-essential
third-party tags on a site behind visitor consent, adapts to the visitor's
Jurisdiction, and keeps demonstrable proof of consent — so the *site* (not merely one
vendor's tag) meets Québec Loi 25 / GDPR. Anyone can install and configure it from the
admin UI, with no code or prior consent-management knowledge. Google Consent Mode is
the first first-class integration, not the boundary of the product.

> Name: **Consentful** — "full of consent". Chosen during the universal-layer pivot
> to replace "Consent Mode v2" (a Google term that no longer describes the scope).
> The pre-rewrite code is gone; the old name survives only in the repo identity
> (the `consent-mode-v2` directory/remote name) until the rename to
> `mboisvertdupras/consentful`.

## Language

### Core model

**Purpose**:
A category of data use a visitor consents to (Analytics, Marketing, …). The legal
unit of consent — under Loi 25/GDPR consent is specific to each purpose. Visitors
toggle purposes; everything else is downstream of a purpose grant. The default set is
**fixed** (compliance guardrails): default labels/descriptions ship as translated
gettext, the Administrator may optionally **override** them in the UI and toggle
optional Personalization, but does not add or remove categories — a Developer may add
purposes via an optional hook.
_Avoid_: Category (the code says Purpose everywhere; only visitor-facing English
prose uses the word "category"), Service.

**Necessary**:
The always-on purpose for strictly essential operation; cannot be toggled off and
gates nothing the visitor can refuse. Never carries a third-party Tag that tracks.
_Avoid_: Essential, required, functional (functional is a *separate* purpose).

**Tag**:
A concrete third-party thing that gets gated — GA4, Google Ads, the Meta Pixel, a
pasted `<script>` snippet. Each tag is assigned to one or more purposes and only
fires when all are granted. A tag is either **Direct** (a Consentful adapter
injects it) or **Delegated** (an external tag manager fires it; Consentful gates it
via a Consent push instead of injecting).
_Avoid_: Pixel, script, integration (when you mean the gated thing itself).

**Adapter**:
The mechanism that knows *how* to load or signal a particular tag — the Google
adapter emits Consent Mode signals, a Meta adapter loads `fbq`, a generic adapter
injects a raw snippet. One adapter can serve several tags.
_Avoid_: Driver, handler, provider, plugin (overloaded with WordPress "plugin").

**Signal**:
A Google Consent Mode v2 key (`analytics_storage`, `ad_storage`, `ad_user_data`,
`ad_personalization`, …) set to `granted`/`denied`. A Google-adapter-specific
concept, **not** a universal one — most adapters just load-or-don't.
_Avoid_: Flag, permission.

**Consent push**:
Emitting consent state to the `dataLayer` (Consent Mode signals + consent events) so
a Delegated tag manager honors the visitor's choice — the gating mechanism for
Delegated tags, as opposed to injecting a Direct tag. (The built-in GTM integration is
Direct: Consentful loads the container behind consent. Consent push stays available for
developer-registered Delegated tags.)
_Avoid_: Sync, broadcast, event (too generic).

### Jurisdiction & legal posture

**Block before consent**:
The guarantee that a tag's code is not loaded at all — no script, no cookieless
ping — until the visitor grants its purpose(s). The basis of "prior consent". This
is specifically the **Opt-in Policy's** guarantee; under an Opt-out Policy tags may
load before the visitor acts. Stronger than Google's "denied-but-still-pings" mode.
_Avoid_: Opt-in (name the Policy instead), cookie blocking.

**Jurisdiction**:
A legal region whose rules govern how consent must be collected (Québec/Loi 25,
EU+UK/GDPR, US states/CPRA, …). Resolved per Visitor by geo; selects a Policy.
_Avoid_: Region, country, locale (locale is language, a different axis).

**Policy**:
The consent strategy a Jurisdiction maps to. Three shapes: **Opt-in** (deny by
default, banner, block before consent), **Opt-out** (allow by default, notice +
Do Not Sell/Share + honor GPC), and **Notice/None**. A Policy fixes the default
consent state and the banner's behavior.
_Avoid_: Mode, profile, ruleset.

**GPC (Global Privacy Control)**:
A browser-sent signal expressing a blanket refusal. Consentful treats it as an
automatic denial everywhere, and as the exercised "Do Not Sell/Share" right under
an Opt-out Policy. When present, the banner is suppressed.
_Avoid_: DNT (Do Not Track is the older, weaker, separate signal we also respect).

**Do Not Sell/Share**:
The US opt-out right an Opt-out Policy must surface (a link/control) and honor —
satisfied automatically when GPC is present.
_Avoid_: Reject, withdraw (those are the Opt-in Policy's actions).

### Consent & proof

**Consent**:
A visitor's decision across all purposes, with schema + policy version and a
timestamp. The **cookie** is the runtime source of truth (cache-safe, fast);
expires after a re-consent window, after which the visitor is re-prompted. A
**client-runtime** concept: the JS lib is its sole owner — `assets/lib/cookie.js`
reads/writes the cookie, `grants.js` decides gating. PHP carries no Consent
runtime (it emits the config the client gates on, and records proof via the
separate **Consent record**); don't re-add a PHP Consent twin for symmetry.
_Avoid_: Preferences (use for the in-banner UI state only), choice.

**Consent record**:
A pseudonymous, durable entry written server-side when a Visitor decides — consent
id, timestamp, purposes, jurisdiction, policy + schema + banner version (optionally
a hashed IP/UA, retention-limited). The demonstrable proof the cookie can't be.
_Avoid_: Receipt, log entry (the *collection* is the Consent log).

**Consent log**:
The store of Consent records — a custom table by default — exportable for an
auditor.
_Avoid_: Audit trail, history.

**Sink**:
The destination a Consent record is written to: the built-in store by default (needs
no code), or a **Developer**-supplied implementation (their DB, a warehouse, an
external CMP) registered via the optional sink hook.
_Avoid_: Store, backend, driver.

### People

**Administrator**:
The WordPress admin who installs Consentful (typically from WordPress.org) and
configures it entirely through the admin UI — tags, purpose copy, jurisdiction policy,
banner appearance. The primary and only required operator; not expected to write code
or know consent law. Install + activate gives a compliant baseline; the **UI is the
source of truth**.
_Avoid_: Integrator (the rejected code-is-canonical actor — see ADR 0004), Site owner
(implied a separate constrained tier — there isn't one), user (means the WP user/role).

**Developer** (optional):
Anyone extending Consentful in code — registering a custom Adapter, adding a Purpose,
or redirecting Consent records to their own Sink — via documented hooks. A power-user
convenience: never required, and never overrides the Administrator's UI settings.
_Avoid_: Integrator (implies code is the source of truth — it isn't), agency.

**Visitor**:
The person browsing the site whose consent is being collected. Distinct from the
Administrator (and any Developer) — never the same actor whose consent is gated.
_Avoid_: User, end user.

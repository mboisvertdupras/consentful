# Consentful

A white-label WordPress plugin that acts as a universal consent layer: it gates
**all** non-essential third-party tags on a site behind visitor consent, adapts to
the visitor's Jurisdiction, and keeps demonstrable proof of consent — so the *site*
(not merely one vendor's tag) meets Québec Loi 25 / GDPR. Google Consent Mode is
the first first-class integration, not the boundary of the product.

> Name: **Consentful** — "full of consent". Chosen during the universal-layer pivot
> to replace "Consent Mode v2" (a Google term that no longer describes the scope).
> The old name survives only in pre-rewrite code.

## Language

### Core model

**Purpose**:
A category of data use a visitor consents to (Analytics, Marketing, …). The legal
unit of consent — under Loi 25/GDPR consent is specific to each purpose. Visitors
toggle purposes; everything else is downstream of a purpose grant.
_Avoid_: Category (the current code term, being migrated), Service.

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
a Delegated tag manager (GTM) honors the visitor's choice — the gating mechanism for
Delegated tags, as opposed to injecting a Direct tag.
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
expires after a re-consent window, after which the visitor is re-prompted.
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
The destination a Consent record is written to: the built-in store by default, or
an Integrator-supplied implementation (their DB, a warehouse, an external CMP).
_Avoid_: Store, backend, driver.

### People

**Integrator**:
The developer/agency who installs the plugin and declares adapters, tags,
purpose mappings and compliance policy in code/config — the source of truth. Can
lock any setting against client changes.
_Avoid_: Developer (too generic), admin (ambiguous with WP admin role).

**Site owner**:
The end client who operates a site the Integrator set up. Gets a deliberately
constrained admin UI (color, copy, toggling pre-approved tags) and cannot alter
anything the Integrator locked. Not expected to write code.
_Avoid_: Client (overloaded), user (means the WordPress user/role), customer.

**Visitor**:
The person browsing the site whose consent is being collected. Distinct from the
Site owner and the Integrator — the three are never the same actor in our model.
_Avoid_: User, end user.

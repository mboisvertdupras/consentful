# CLAUDE.md

Guidance for Claude Code when working in this repository.

> **Read these first for *direction*:** [`CONTEXT.md`](CONTEXT.md) (the glossary —
> Purpose, Tag, Adapter, Policy, …) and [`docs/adr/`](docs/adr/) (the decisions and
> their rejected alternatives). This file describes the **target** architecture;
> those define the language and the *why*.

## What this is

**Consentful** — a white-label, open-source WordPress *universal consent layer*. It
gates **all** non-essential third-party tags behind visitor consent, adapts to the
visitor's jurisdiction, and keeps demonstrable proof of consent, so the **site**
(not merely one vendor's tag) meets Québec Loi 25 / GDPR / US opt-out laws. Google
Consent Mode is the first integration, not the boundary.

## ⚠️ Repo status: mid-pivot

The files currently on disk are **legacy v2.0.0** — a single-file, Google-only,
PHP-7.4 plugin (`consent-mode-v2.php`, `src/consent.{js,css}`). It is being rebuilt
into the architecture below. **Treat the legacy code as throwaway:**

- Build *toward* the target architecture; do **not** deepen the single-file /
  procedural / 7.4 / Google-hardcoded design.
- **No backwards compatibility** is owed (no installs to protect). The rebrand from
  `cmv2_`/"Consent Mode v2" to `consentful_`/"Consentful" is a clean break — no
  shim.
- Where this file and the legacy code disagree, **this file wins**; the code is
  what's being replaced.

## Target architecture

### Domain model — `Purpose ↔ Tag ↔ Adapter`

- **Purpose** — what the visitor consents to (the legal unit). A default set
  (necessary, functional, analytics, marketing, +optional personalization),
  **integrator-extensible** in code.
- **Tag** — the concrete thing gated (GA4, Google Ads, Meta Pixel, a snippet).
  Assigned to one or more purposes; fires only when all are granted. Either
  **Direct** (a Consentful adapter injects it) or **Delegated** (an external tag
  manager fires it; Consentful gates it via a *consent push* to the dataLayer).
- **Adapter** — knows *how* to load/signal a tag. The core hard-codes nothing
  Google. **Google is just a rich adapter** that additionally emits Consent Mode v2
  signals (default-deny, `wait_for_update`, cookieless pings, `ads_data_redaction`,
  `url_passthrough`) to preserve conversion modeling. Third parties register
  adapters against an explicit interface.

### Gating is client-side and cache-safe (sacrosanct)

Every visitor receives **identical HTML** — this is non-negotiable; it's what keeps
the plugin correct behind full-page caches / CDNs. An inline `<head>` decider plus
per-adapter JS reads the consent cookie at runtime and injects only granted tags.
Custom snippets are **stored server-side and injected by JS**, never printed as a
literal `<script>` (so they can't use `document.write`). **Never** add a code path
that varies server-rendered HTML per visitor cookie/geo.

The inline decider and the adapter-load path must run **before any framework, in old
browsers** — keep them framework-free and **ES5-safe** (Vite targets `es2015`;
ESLint enforces the WordPress es5 standard on that code). Values injected via
`wp_localize_script` arrive as strings — coerce explicitly.

### Jurisdiction & consent (see ADR 0002)

- **Geo-adaptive, multi-jurisdiction from day one.** A **Policy** is Opt-in (deny by
  default, banner, block-before-consent — Loi 25/GDPR), Opt-out (allow by default,
  notice + Do Not Sell/Share + honor GPC — US), or Notice/None. A **Jurisdiction**
  is resolved per visitor and selects a Policy.
- **Geo is client/edge-resolved and fail-closed.** Until the region is known, apply
  the **strictest** Policy (opt-in, deny-all, banner shown). A pluggable resolver
  prefers an edge-set signal (CDN geo header → JS-readable cookie/var). **GPC is
  honored instantly**, regardless of region, and suppresses the banner.
- **Cookie = runtime source of truth**, carrying per-purpose grants + jurisdiction +
  policy/schema version + timestamp; expires after the re-consent window. A change to
  the purpose **schema** or the **policy** version triggers re-consent.
- **Proof of consent.** On each decision, async-POST a pseudonymous **Consent
  record** (consent id, timestamp, purposes, jurisdiction, policy/schema/banner
  version; optional hashed IP/UA, retention-limited) to a built-in **Consent log**
  table, exportable for an auditor. A **Sink** interface lets the integrator redirect
  records to their own store. The separate, non-cached endpoint keeps caching intact.

### Operator model — two-tier

The **Integrator** (agency/dev) declares adapters, tags, purpose→purpose mappings,
jurisdiction policy and banner defaults in **code/config — the source of truth — and
can lock any setting**. The **Site owner** gets a deliberately constrained admin UI
(color, copy, toggling pre-approved tags). Admin-pasted snippets are an integrator
(trusted) concern, not a site-owner one.

## Tech stack & conventions

- **PHP 8.1+**, **OOP / PSR-4 / DI container.** Enums (Purpose, Signal), readonly
  value objects, explicit adapter interface. (Legacy code is 7.4 procedural — replace
  it, don't extend it.)
- **Bundled Composer deps must be prefixed/scoped** (Strauss / PHP-Scoper) or they
  collide with other plugins loading a different version. This is a hard requirement
  of shipping a container in a WP plugin.
- **First-class tests.** PHPUnit (core, consent decisioning, cookie/record schema,
  each adapter against the interface) **and** a JS layer for the decider/gate — the
  cache-safe client gate is the riskiest, compliance-critical code. **"Verify" =
  lint + phpstan + tests green + build.**
- **Prefix everything** `consentful_` / `Consentful\` / text domain `consentful` /
  option `consentful_settings` / cookie `consentful` / CSS root `.consentful` (`.cnf-`
  elements) — enforced by phpcs `PrefixAllGlobals`.
- **Distribution:** open-source (GPL), via **GitHub + Composer/Packagist** for devs
  **and** a built, scoped zip for normal installs. Audience is integrators, so the UI
  stays small; don't grow a mass-market no-code UI.
- **Version must agree across:** the plugin header `Version:`, the version constant,
  `Stable tag:` in `readme.txt`, and `version` in `package.json` — the release
  workflow fails if they diverge.
- **i18n:** gettext under `languages/` (`.po` source of truth → `.mo`; `.pot`
  template), regenerated by the build via WP-CLI. English is source; French
  (`fr_CA`/`fr_FR`) bundled. Language (locale) is a **separate axis** from
  jurisdiction (geo) — don't conflate them.
- **`readme.txt` vs `README.md`:** `readme.txt` is the shipped WP.org-format user
  readme (its changelog becomes the GitHub Release body); `README.md` is dev docs,
  excluded from the zip. `.distignore` controls zip contents (not `.gitignore`).

## Commands

> These wrap the **legacy** pipeline and will evolve with the rewrite (a PHP build
> step, dependency scoping, and `tests/` are coming). Until then:

```sh
npm run build        # Vite assets + regenerate i18n (.pot/.po/.mo)
npm run dev          # vite build --watch
npm run lint         # js + css + php
npm run analyze      # composer run analyze → phpstan
npm run package      # build + produce the distributable zip
```

`i18n:*` and `package` need **WP-CLI** (`wp`) on PATH (`dist-archive` 3.x wants
WP-CLI ≥ 2.13 — `wp cli update --nightly` locally). Symlink the repo into a WP
install for local dev, then activate from the Plugins screen.

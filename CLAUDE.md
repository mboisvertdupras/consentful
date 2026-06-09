# CLAUDE.md

Guidance for Claude Code when working in this repository.

> **Read these first for *direction*:** [`CONTEXT.md`](CONTEXT.md) (the glossary —
> Purpose, Tag, Adapter, Policy, …) and [`docs/adr/`](docs/adr/) (the decisions and
> their rejected alternatives). This file describes the **target** architecture;
> those define the language and the *why*.

## What this is

**Consentful** — an open-source, **general-purpose** WordPress *universal consent
layer* built for the WordPress.org plugin directory. **Anyone can install it and be
compliant straight from the admin UI — no code, no prior consent-management
knowledge.** It gates **all** non-essential third-party tags behind visitor consent,
adapts to the visitor's jurisdiction, and keeps demonstrable proof of consent, so the
**site** (not merely one vendor's tag) meets Québec Loi 25 / GDPR / US opt-out laws.
Google Consent Mode is the first integration, not the boundary.

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

- **Purpose** — what the visitor consents to (the legal unit). A **fixed** default
  set (necessary, functional, analytics, marketing, +optional personalization).
  Default labels/descriptions ship as **translated gettext** (EN/FR); the admin may
  optionally **override** a purpose's label/description in the UI and toggle
  personalization, but does not add/remove categories (compliance guardrails).
  Developers may register extra purposes via an optional hook.
- **Tag** — the concrete thing gated (GA4, Google Ads, Meta Pixel, a snippet).
  Assigned to one or more purposes; fires only when all are granted. Either
  **Direct** (a Consentful adapter injects it) or **Delegated** (an external tag
  manager fires it; Consentful gates it via a *consent push* to the dataLayer). The
  admin adds tags **in the UI** — from a built-in catalog (GA4, GTM, Meta Pixel, …
  via simple ID fields) or as a pasted **custom HTML/script** snippet.
- **Adapter** — knows *how* to load/signal a tag. The plugin ships a **curated
  catalog** the admin picks from; the core hard-codes nothing Google. **Google is
  just a rich adapter** that additionally emits Consent Mode v2 signals (default-deny,
  `wait_for_update`, `ads_data_redaction`, `url_passthrough`; denied signals degrade a
  loaded, granted Tag to cookieless pings — never pre-consent loading) to
  preserve conversion modeling. Developers may register additional adapters against an
  explicit interface — an optional extension point, not a requirement.

### Gating is client-side and cache-safe (sacrosanct)

Every visitor receives **identical HTML** — this is non-negotiable; it's what keeps
the plugin correct behind full-page caches / CDNs. An inline `<head>` decider plus
per-adapter JS reads the consent cookie at runtime and injects only granted tags.
Custom snippets are **stored server-side and injected by JS**, never printed as a
literal `<script>` (so they can't use `document.write`). **Never** add a code path
that varies server-rendered HTML per visitor cookie/geo.

The inline decider and the adapter-load path must run **before any framework, in old
browsers** — keep them **framework-free**. Author them as **modern ES modules** under
`assets/` (shared pure helpers in `assets/lib/`) and let **Vite bundle each entry to a
self-contained, dependency-free script targeting `es2015`** (broad reach, no IE11):
the **decider** builds to a single inlined IIFE (it sets the Consent Mode default
before any tag, so it can't be a deferred `type=module`); the **gate** to a hashed,
enqueued classic script. ESLint lints `assets/` as modern ESM; Vitest unit-tests the
`lib/` helpers directly. Config injected via the inline blob / `wp_localize_script` may
arrive as strings — coerce explicitly.

**Exactly two front-end bundles, by necessity — don't add more.** A self-contained
classic IIFE can't be code-split, so a single Vite build can't emit two of them; the
only reason there are two builds is the unavoidable *critical-inline* (head decider)
vs *cacheable-external* (footer gate) split. **Every other front-end feature rides in
the gate bundle** — the banner UI and its CSS are `import`ed by `assets/gate.js`, Vite
extracts the CSS into the gate's manifest entry, and the gate enqueues it. Toggle such
features with config flags (e.g. `banner.enabled`), not a separate build/enqueue. Do
**not** spawn a per-feature Vite config.

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
  table, exportable for an auditor. A **Sink** interface lets a developer redirect
  records to their own store. The separate, non-cached endpoint keeps caching intact.

### Operator model — self-serve admin, code optional (see ADR 0004)

The **Administrator** (the WordPress admin who installs from WordPress.org) configures
**everything from the admin UI** — tags (built-in catalog + custom snippets), purpose
labels/copy, jurisdiction policy, banner appearance (its **text** stays gettext-only —
translated via `.po`/`.mo` or a plugin like Loco Translate / WPML / Polylang, never an
admin field) — with **no code and no prior consent-management knowledge required**. Install + activate yields a **compliant
baseline** (geo-adaptive defaults, strictest-until-known, banner shown); the **UI is
the source of truth**, persisted to `consentful_settings`. Pasting a custom snippet is
a normal admin capability, gated by the same `unfiltered_html` / `manage_options` trust
WordPress already grants admins (the Custom HTML block, theme/plugin editing) — not a
separate "trusted integrator" tier.

**Developers** are an *optional* second audience: documented hooks let them register a
custom adapter, add a purpose, or redirect Consent records to their own `Sink`. Code is
**never required and never overrides the UI** as the source of truth. Do **not**
reintroduce a code-is-canonical / lock-the-admin tier — that was the rejected pre-1.0
model (ADR 0004 supersedes it).

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
- **Distribution:** open-source (GPL), primarily via the **WordPress.org plugin
  directory** for normal installs (built, scoped zip), plus **GitHub +
  Composer/Packagist** for developers. The audience is **every WordPress site owner**,
  so the admin UI is the primary product surface — make it self-explanatory and
  complete (without becoming a sprawling page builder). Keep the *developer* extension
  surface small; the no-code UI is not.
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

## 1. Think Before Coding

**Don't assume. Don't hide confusion. Surface tradeoffs.**

Before implementing:
- State your assumptions explicitly. If uncertain, ask.
- If multiple interpretations exist, present them - don't pick silently.
- If a simpler approach exists, say so. Push back when warranted.
- If something is unclear, stop. Name what's confusing. Ask.

## 2. Simplicity First

**Minimum code that solves the problem. Nothing speculative.**

- No features beyond what was asked.
- No abstractions for single-use code.
- No "flexibility" or "configurability" that wasn't requested.
- No error handling for impossible scenarios.
- If you write 200 lines and it could be 50, rewrite it.

Ask yourself: "Would a senior engineer say this is overcomplicated?" If yes, simplify.

## 3. Surgical Changes

**Touch only what you must. Clean up only your own mess.**

When editing existing code:
- Don't "improve" adjacent code, comments, or formatting.
- Don't refactor things that aren't broken.
- Match existing style, even if you'd do it differently.
- If you notice unrelated dead code, mention it - don't delete it.

When your changes create orphans:
- Remove imports/variables/functions that YOUR changes made unused.
- Don't remove pre-existing dead code unless asked.

The test: Every changed line should trace directly to the user's request.

## 4. Goal-Driven Execution

**Define success criteria. Loop until verified.**

Transform tasks into verifiable goals:
- "Add validation" → "Write tests for invalid inputs, then make them pass"
- "Fix the bug" → "Write a test that reproduces it, then make it pass"
- "Refactor X" → "Ensure tests pass before and after"

For multi-step tasks, state a brief plan:
```
1. [Step] → verify: [check]
2. [Step] → verify: [check]
3. [Step] → verify: [check]
```

Strong success criteria let you loop independently. Weak criteria ("make it work") require constant clarification.

---

**These guidelines are working if:** fewer unnecessary changes in diffs, fewer rewrites due to overcomplication, and clarifying questions come before implementation rather than after mistakes.

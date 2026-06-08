# Re-architecture: from a Google-tag gate to a universal consent layer

## Status

accepted — supersedes the original v2.0.0 single-file design. **The "Operator model:
two-tier" paragraph below is superseded by ADR 0004** (self-serve admin UI; the
integrator-as-source-of-truth tier is dropped). Everything else stands.

## Context

v2.0.0 is a single-file, procedural plugin (PHP 7.4, no classes, no tests) whose
entire design assumes it is the *sole source of the one Google tag*. That makes
the **Google tag** compliant, but not the **site**: any other tag (Meta Pixel,
TikTok, a pasted snippet) bypasses the gate entirely — the admin notices even
apologize for this. Loi 25/GDPR obligations are site-wide, so the narrow framing
under-delivers on the actual promise.

## Decision

Re-architect into a universal, **vendor-neutral consent layer**. One combined
decision with three load-bearing parts:

1. **Domain model — Purpose ↔ Tag ↔ Adapter.** Visitors consent to *Purposes*
   (the legal unit). *Tags* (GA4, Google Ads, Meta Pixel, custom snippets) are
   assigned to purposes. *Adapters* know how to load/signal a tag. The core knows
   only "purpose granted → adapter loads"; nothing in the core hard-codes
   `gtag`/`dataLayer`. **Google is just a richer adapter** that additionally emits
   Consent Mode v2 signals (default-deny, `wait_for_update`, cookieless pings,
   `ads_data_redaction`, `url_passthrough`) to preserve conversion modeling.
   Rejected: a Google-privileged core, and dropping Consent Mode signaling
   altogether (would lose the modeling the product is named for).

2. **Client-side, cache-safe gating.** Every visitor receives identical HTML. An
   inline `<head>` decider + per-adapter JS reads the consent cookie at runtime and
   injects only granted tags; custom snippets are stored server-side and injected
   by JS, **never printed as a literal `<script>`**. Rejected: server-side gating
   (per-visitor tag printing poisons full-page caches/CDNs and leaks tags to the
   wrong visitor) and a server/client hybrid (two gate paths that must agree — the
   same dual-logic trap the v2 head-script/banner split already suffers).
   Consequence: snippets cannot use `document.write`.

3. **OOP / PSR-4 / DI container on PHP 8.1+, with first-class tests.** Replaces
   single-file procedural. Enums for Purpose/Signal, readonly value objects, an
   explicit adapter interface third parties register against. PHP floor raised
   7.4 → 8.1 (7.4 is EOL; breaking change accepted — the project carries no
   backwards-compat obligation yet). PHPUnit (PHP core + each adapter) **and** a JS
   test layer for the decider/gate, since the cache-safe client gate is the
   riskiest, compliance-critical, least-visible code. "Verify" becomes
   lint + phpstan + tests green + build. Rejected: lean-modular-with-closures (kept
   the contract implicit) and full ceremony beyond a container.

Operator model: **two-tier**. The *Integrator* (agency) declares adapters, tags,
purpose mappings and policy in code/config (the source of truth, can lock any
setting); the *Site owner* gets a deliberately constrained admin UI. This keeps
admin-pasted-snippet risk with a trusted, technical actor.

## Consequences

- Bundled Composer deps (e.g. the container) **must be prefixed/scoped**
  (Strauss / PHP-Scoper) or they will collide with other plugins loading a
  different version of the same library.
- `CMV2_SCHEMA_V` / the cookie payload must be redesigned around purposes
  generally, not the fixed `analytics`/`marketing` pair.
- The product is renamed **Consentful** (vendor-neutral), replacing the
  Google-specific "Consent Mode v2". The rewrite migrates every identifier in one
  pass — namespace `Consentful\`, prefix `consentful_`/`CONSENTFUL_`, text domain
  `consentful`, option `consentful_settings`, cookie `consentful`, CSS root
  `.consentful` with a `.cnf-` element prefix — with no backwards-compat shim for
  `cmv2_` (no installs to protect yet).

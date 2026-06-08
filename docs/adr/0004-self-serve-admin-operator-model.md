# Operator model: self-serve admin UI; code extension optional

## Status

accepted — supersedes the operator-model decisions of ADR 0001 (the "Operator model:
two-tier" paragraph) and ADR 0003 (decision 1, "Integrator registration is the DI
container"). The vendor-neutral core, cache-safe gating, jurisdiction model and
proof-of-consent decisions of ADR 0001/0002 stand unchanged, as do ADR 0003's
decisions 2–3 (Personalization as an opt-in default-set member; Notice/None's explicit
`default_granted`).

## Context

The pre-1.0 ADRs framed the audience as technical **integrators** (agencies/devs) who
declare adapters, tags, purpose mappings and jurisdiction policy in **code/config as
the source of truth**, with the right to **lock** any setting; the site owner got a
"deliberately constrained admin UI". ADR 0003 went further and made the public
configuration API *be* the DI container, justified by "the audience is technical
integrators".

That model is unusable by the people WordPress.org actually serves. Consentful is meant
to be a **general-purpose public plugin anyone can install** — a non-technical site
owner cannot and will not write a `consentful_register` PHP block, hand a DI container
around, or reason about a purpose→signal map. Requiring code to reach a compliant state
blocks the product's core goal at the front door. There are no published installs and
no backwards-compat obligation, so this is a clean break.

## Decision

**The admin UI is the primary, sufficient, and canonical configuration surface.** A
non-technical **Administrator** configures everything — tags, purpose copy, jurisdiction
policy, banner appearance — from the WordPress admin, persisted to `consentful_settings`.
No code and no consent-management expertise are required.

1. **Compliant by default (zero-config).** Install + activate yields a working,
   compliant baseline: shipped default purposes, a geo→policy mapping (opt-in for
   Loi 25/GDPR regions, opt-out for US, strictest-until-known, banner shown).
   Configuration *refines* the baseline; it is never a prerequisite for compliance.

2. **Tags via a built-in catalog + custom snippet.** The Administrator picks from
   curated built-in integrations (GA4, GTM, Google Ads, Meta Pixel, …) with simple ID
   fields and purpose assignment, or pastes a generic **custom HTML/script** snippet
   (stored server-side, injected by JS per the cache-safe gate of ADR 0001). Pasting a
   snippet is a normal `unfiltered_html` / `manage_options` admin capability — the same
   trust WordPress already grants admins via the Custom HTML block and theme/plugin
   editing — **not** a separate "trusted integrator" tier.

3. **Fixed purpose set; purpose copy overridable, banner copy gettext-only.** Ship
   Necessary / Functional / Analytics / Marketing (+ optional Personalization). All
   user-facing copy ships as **translated gettext** (English source, EN/FR bundled).
   The UI exposes optional per-**purpose** label/description **overrides** on top (an
   admin override is not auto-translated and falls back to the gettext default when
   blank). The **banner's own strings** (headline, body, button labels) stay
   **gettext-only** — edited via `.po`/`.mo` or a translation plugin (Loco Translate,
   Poedit, WPML, Polylang), never an admin text field (an in-admin copy builder is an
   anti-pattern and produces untranslatable strings). The Administrator toggles
   Personalization but **cannot add or remove categories from the UI** (compliance
   guardrails). Developers may add purposes via a hook.

4. **Code extension is an optional power-user layer, never the source of truth.**
   Documented hooks let a **Developer** register a custom Adapter, add a Purpose, or
   redirect Consent records to their own `Sink`. The UI is unambiguously canonical; code
   never overrides stored settings.

## Rejected alternatives

- **Pure UI, no code API.** Simplest surface, but a site needing an unsupported
  integration would be stuck waiting for a built-in; the hook layer is cheap and serves
  the open-source / Packagist developer audience the project also ships to.
- **Co-equal code + UI surfaces (status quo + UI).** Doubles the documented and
  maintained surface and reintroduces a "which one is canonical?" ambiguity. The UI must
  be the single source of truth.
- **Fully user-editable purpose taxonomy in the UI.** Weakens compliance guardrails and
  bloats the UI for a need the fixed set + developer hook already cover.
- **Keep `consentful_register` / the container as the documented registration surface
  (ADR 0003 decision 1).** Superseded: the container is now an internal wiring detail,
  not a public API. The integrator-as-source-of-truth framing and the
  `consentful_locked_settings` lock-the-admin mechanism are removed — there is no second
  tier to lock against.

## Consequences

- **Personas collapse to Administrator (+ optional Developer).** CONTEXT.md drops the
  Integrator / Site-owner split.
- **Settings move from code to the admin UI.** The front-end `ClientConfig` is derived
  from stored `consentful_settings`, not from code registration. `Plugin::consentful_register`,
  `Tag(site_toggleable:)`, the `consentful_locked_settings` filter, and the
  container-as-public-API are slated for removal/rework in the path-forward increment.
- **The admin UI grows from "deliberately constrained" to the primary product surface.**
  It must expose the tag catalog, custom-snippet management, purpose-copy editing,
  policy/geo configuration, and banner appearance. CLAUDE.md's earlier "don't grow a
  mass-market no-code UI" guidance is reversed.
- **"White-label" is dropped from positioning** (it implied agency-rebrand/resell);
  neutral, themeable branding remains a feature.

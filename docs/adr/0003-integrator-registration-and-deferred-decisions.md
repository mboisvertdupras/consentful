# Integrator registration surface and resolved deferred decisions

## Status

accepted — refines ADR 0001 / 0002 after the resolver, banner-variant, proof-of-consent
and admin-UI increments shipped

## Context

ADRs 0001 and 0002 set the architecture; building it out left a handful of design
follow-ups deliberately deferred until the surrounding code existed. This ADR records the
decisions, with their rejected alternatives, now that the increments are in place.

## Decision

**Integrator registration is the DI container (no facade).** `consentful_register` hands
the integrator the container; they register adapters/tags on the registries, rebind the
config value objects (`BannerConfig`, `GeoConfig`, `ProofConfig`, the `Sink`), and declare
locks via the `consentful_locked_settings` filter. Rejected: a narrowing `Registration`
facade. The audience is technical integrators, ADR 0001 already frames the surface as the
container, and a facade is additive API surface to maintain for marginal ergonomic gain —
against the "keep the surface small" constraint. The container stays the single, documented
surface; README carries a worked registration example.

**Personalization is an opt-in default-set member.** The shipped default Purpose set is the
four universal categories — Necessary, Functional, Analytics, Marketing. `Personalization`
remains a `DefaultPurpose` case (with a stable key and banner copy) but is NOT seeded by
`PurposeRegistry::with_defaults()`; an integrator opts in by adding it. Rejected:
shipping it on by default — it adds a banner toggle and a Consent Mode signal most sites
never use, and ADR 0002 already phrased it as "(+ optional Personalization)". The US
opt-out default-grant set is derived from `DefaultPurpose::defaults()`, so it stays
consistent with whatever is actually shipped.

**Notice/None grants its explicit `default_granted` set.** A `NoticeOnly` Policy performs no
gating: no banner, no block-before-consent. Its `default_granted` is exactly what loads
without a decision — typically every non-essential Purpose (since nothing collects consent);
an empty list loads only always-on Purposes. The integrator chooses the set explicitly; this
is documented on `Policy::notice_only()` rather than inferred.

## Consequences

- The integrator API is the container; there is no second registration abstraction to keep
  in sync. A future facade remains possible but is out of scope.
- Sites wanting a Personalization purpose add one line in `consentful_register`; the banner,
  signals and proof records pick it up automatically.
- `uninstall.php` removes the settings, the per-site record salt, the DB-version marker and
  drops the consent-log table — per site on multisite — completing the data lifecycle ADR
  0002 introduced (server-side storage now has a full teardown).

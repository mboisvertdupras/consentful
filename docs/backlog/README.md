# Backlog — structural clusters deferred past 1.0.0

Verified findings from the 2026-06-09 architecture audit that the release-first triage
deferred: real friction, no release-blocking bugs. Each file is self-contained —
problem, audit-time evidence, direction (with the adversarial verifier's corrections),
and severity. File:line references describe the tree at audit time and may have
drifted; re-verify before acting.

- [`catalog-entry-ownership.md`](catalog-entry-ownership.md) — adding a catalog
  integration today means synchronized edits to three modules, with two silent-failure
  modes on the product's main growth axis; CatalogEntry should own its own contract.
- [`consent-log-collapse.md`](consent-log-collapse.md) — five modules and two
  export-row definitions for one table, and a CSV export that buffers the whole
  Consent log exactly when an auditor asks for it; merge first, then stream.
- [`banner-ownership.md`](banner-ownership.md) — banner vocabulary, defaults, chrome,
  and copy each have two-to-four owners that have already drifted; includes the
  unresolved NoticeOnly-vs-ADR-0002 tension.
- [`client-state-resolver.md`](client-state-resolver.md) — the decider/gate grants
  agreement (the central compliance invariant) is maintained by hand-copied
  orchestration; a shared resolver makes it structural and becomes the single owner of
  the strictest fallback.
- [`registry-pruning.md`](registry-pruning.md) — PHP-side deletions left after the 1.0
  wire trim: test-only registry lookup methods, untranslated jurisdiction labels,
  write-only `Tag::$label`.
- [`contract-fixture.md`](contract-fixture.md) — the PHP→JS config shape is tested as
  two (really three) hand-written twins; one checked-in fixture makes the seam
  mechanical. Build after the wire trim lands.

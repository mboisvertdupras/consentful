# Registry pruning: delete the test-only read surface and the write-only labels

Deferred from the 1.0.0 gate (release-first triage) — 2026-06-09 architecture audit.
File:line references describe the tree at audit time and may have drifted.

> **Scope note (post-gate):** the wire side of these findings (`jurisdiction.label`,
> `tag.delivery`, the dead policy fields) is being trimmed in the 1.0 gate. What
> remains here is PHP-side pruning — the registry lookup methods, the label
> properties, `Tag::$label`, `Tag::$delivery` with the `Delivery` enum and
> `CatalogEntry::delivery()` (write-only since the wire trim), and Policy's unread
> flag readers.

## Registries are write-then-iterate dictionaries carrying a mostly-dead read surface

**Problem.** Production uses every registry as add()-then-all(): the lookup surface
exists only for its own unit tests, so the interfaces are nearly as wide as the
implementations while the depth (consent decisioning) lives client-side by design.
Dead methods on internal modules invite future callers and cost test maintenance for
behavior nothing needs.

**Evidence.** Zero production callers (verified by grep over src/, consentful.php,
uninstall.php, assets/): `TagRegistry::has/get/for_adapter` (TagRegistry.php:15-41),
`AdapterRegistry::has/get` (AdapterRegistry.php:15-21), `PurposeRegistry::has` and
`::with_defaults` (PurposeRegistry.php:18-28 — SettingsHydrator.php:97 constructs
`new PurposeRegistry( $purposes )` directly), `JurisdictionRegistry::get`
(JurisdictionRegistry.php:23-25 — jurisdiction lookup happens in
assets/lib/jurisdiction.js), `Settings::all` (Settings.php:79-81). Each is exercised
only in tests/Unit/. By contrast `JurisdictionRegistry::with_defaults/fallback` and
PurposeRegistry's key-dedup are live (ClientConfig.php:42, SettingsHydrator.php:267)
and earn their keep.

**Direction.** Prune to the lived contract: add() + all() (plus fallback() /
with_defaults() on JurisdictionRegistry), deleting the orphaned methods and their
tests. Optionally go further and let TagRegistry/AdapterRegistry die into plain
`array<string, Tag|Adapter>` maps inside SettingsHydrator/ClientConfig — the deletion
test says their only earned behavior is one-line id-dedup with two users total.

**Severity.** low

**Verifier notes.** Independently re-verified every claim by grep. No ADR protects the
lookup surface — ADR 0004 explicitly demoted the ADR-0003 registries-as-public-
registration model to "internal wiring detail, not a public API", and the Developer
seam is now filter-returned arrays (Gate.php:91-110), so these methods are leftovers
of a superseded design, not Developer surface. No runtime or compliance impact.

## Jurisdiction labels: untranslated strings serialized into every page for no client consumer

**Problem.** Jurisdiction labels are hard-coded English (not gettext, unlike every
other user-facing string in the codebase) and were serialized into the inline head
blob on every page — yet nothing in the client reads them. `Tag::$label` has the
inverse problem: computed server-side, never serialized at all. The wire half is being
trimmed in the 1.0 gate; the PHP-side residue (the untranslated label properties, the
hydrator's tag_label work) is this backlog item.

**Evidence.** JurisdictionRegistry.php:38-56 hard-codes `'Default (strictest)'`,
`'Québec (Loi 25)'`, `'European Union (GDPR)'`, `'United Kingdom (UK GDPR)'`,
`'United States (state opt-out)'` with no `__()` (contrast SettingsHydrator.php:284
which wraps `'All visitors'`). ClientConfig.php:57-60 serialized each label into the
blob; assets/lib/config.js:41 parsed it but no module read `.label` on a jurisdiction
afterward. Tag side: `ClientConfig::tags_array` (ClientConfig.php:113-121) emits
id/purposes/delivery/adapter — never label — so `SettingsHydrator::tag_label`
(SettingsHydrator.php:373-376) and the translated `__( 'Google', 'consentful' )` at
SettingsHydrator.php:182 produce data with no consumer.

**Direction.** With the blob field gone, keep labels server-side only if the admin UI
ever lists jurisdictions — gettext-wrapped at that point; otherwise delete the label
properties. Remove `Tag::$label` and the hydrator's tag_label work — it exists only to
satisfy the README's hook example signature (or serialize it, if a consumer ever
appears; today none does).

**Severity.** low

**Verifier notes.** Every cited line verified; exhaustive grep of assets/ confirmed
only the parser touched jurisdiction `.label`, and `Tag::$label` is write-only (built
at SettingsHydrator.php:129/:181, never emitted by tags_array, zero `->label` readers
in src/ or tests/), with the README:83-90 hook example its only surface. Pure
deletion; dead payload plus a latent i18n inconsistency, no functional or compliance
risk.

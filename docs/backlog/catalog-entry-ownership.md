# Catalog entry ownership: one module per integration

Deferred from the 1.0.0 gate (release-first triage) — 2026-06-09 architecture audit.
File:line references describe the tree at audit time and may have drifted.

> **Scope note (post-gate):** the 1.0 gate replaced `meta_pixel_code()` with a
> data-driven `meta` handler config, so the future entry-owned recipe is smaller than
> the finding below describes — the largest per-entry code branch (the Meta Pixel
> bootstrap string) no longer exists in the hydrator.

## Give CatalogEntry the depth it pretends to have: one module per catalog integration

**Problem.** What a catalog entry *is* — its field whitelist and how its fields become
adapter client config — is fragmented across three modules with no locality. Adding one
new integration (the product's main growth axis per the "curated catalog" promise)
requires synchronized edits to Catalog, Settings, and SettingsHydrator; forgetting the
Settings whitelist silently discards the admin's field input on save.

**Evidence.** Settings.php:28-34 re-declares every entry's fields by hand:
`private const CATALOG_FIELDS = array( 'ga4' => array( 'measurementId' ), 'google-ads'
=> array( 'conversionId' ), ... )` — duplicating what `CatalogEntry::fields()`
(CatalogEntry.php:45-47) already knows. SettingsHydrator string-switches on entry
identity: `if ( 'gtm' === $entry->key() )` (SettingsHydrator.php:158),
`if ( 'meta-pixel' === $entry->key() )` (SettingsHydrator.php:211),
`foreach ( array( 'measurementId', 'conversionId' ) as $field )`
(SettingsHydrator.php:196), `if ( 'google' === $entry->handler() )`
(SettingsHydrator.php:119). CatalogEntry itself is a pure data bag (6 getters, zero
behavior) — its semantics are enforced elsewhere.

**Direction.** Make CatalogEntry the single owner of an entry's contract: derive
Settings' sanitize whitelist from `array_keys( $entry->fields() )` via
`Catalog::with_defaults()` (deleting `CATALOG_FIELDS`), and move the
fields→adapter-config recipe onto the entry (a `client_config( array $fields ): array`
method or template; the per-entry Google ID field name becomes entry data instead of
hydrator branches). The google-handler aggregation into one GoogleAdapter legitimately
stays a hydrator concern. Target shape: a new entry is one CatalogEntry in
`Catalog::with_defaults()` carrying fields + its client-config recipe;
`Settings::sanitize_fields()` asks the Catalog for allowed keys;
`SettingsHydrator::adapter_config()` collapses to `$entry->client_config( $fields )`.
No module count change — Settings and SettingsHydrator shrink, CatalogEntry deepens
from data bag to the owning module.

**Severity.** medium

**Verifier notes.** Every cited line verified accurate. The finding *understates* the
trap: Admin.php:227 renders new entries dynamically from the Catalog, but
Settings.php:278 drops the whole tag on save if `CATALOG_FIELDS` lacks the key, and a
missed `adapter_config` branch yields a silently inert adapter via empty
`custom_fragments` — two silent failures on the product's stated growth axis. No ADR
decides entry-shape ownership; the deletion test passes. Caveat: the `custom` entry
stays special-cased either way (its Catalog fields don't match the stored fragments
shape — see the next finding).

## Custom-snippet fields shape has no owning module; the Catalog's descriptors for it are stale and dead

**Problem.** The stored shape is `fields.fragments[{code, location}]`, but the
Catalog's `custom` entry declares flat code/location descriptors that nothing reads —
the only `fields()` consumer (Admin.php:227) sits in a path that explicitly skips
custom (Admin.php:198-199). The real fragments shape is then privately re-stated in
three modules (Settings sanitizer, Admin repeater renderer, SettingsHydrator), so a
shape change touches all three while the canonical-looking Catalog descriptor stays
wrong.

**Evidence.** Catalog.php:85-96 declares `code`/`location` field descriptors; grep
shows `fields()` consumed only at Admin.php:227; the fragments shape is independently
encoded at Settings.php:344-367 (`sanitize_custom_fields`), Admin.php:318-328
(`render_fragment_row` name attributes), and SettingsHydrator.php:233-253
(`custom_fragments`).

**Direction.** Delete the dead code/location descriptors from the `custom`
CatalogEntry (deletion test passes — Settings.php:33 already special-cases custom with
an empty allowlist). If the fragments shape grows another field, give it one owner
then; today a deletion suffices.

**Severity.** low

**Verifier notes.** Every cited line verified. The finding understates it:
CatalogTest.php:68-78 pins the stale descriptors, and `render_tag_field` has no
textarea/select path so they could never render anyway — real drift from commit
7e0db48, not hypothetical. No runtime or compliance impact.

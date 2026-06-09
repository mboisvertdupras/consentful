# Banner ownership: one owner for vocabulary, defaults, chrome, and copy

Deferred from the 1.0.0 gate (release-first triage) — 2026-06-09 architecture audit.
File:line references describe the tree at audit time and may have drifted.

> **Unresolved tension (revisit with this cluster):** `PolicyType::NoticeOnly`
> currently maps to `shows_banner() === false` (Policy.php:27-29), while ADR 0002's
> consequences expect the banner to render a notice variant. The verifier flagged this
> as an unflagged ADR-vs-code tension; it is not decided either way. Resolve it when
> the banner shell is deduplicated — a third Notice variant is exactly what the shared
> shell makes cheap, or the ADR consequence gets amended.

## Banner vocabulary and defaults are owned by three modules

**Problem.** The banner's allowed positions/themes and its default values are each
defined in two or three places, with the same invariant enforced twice (once at
sanitize-on-save, again at hydrate-on-read). A maintainer changing a default or adding
a position must find all copies or the admin UI, the stored defaults, and the front
end silently disagree.

**Evidence.** POSITIONS/THEMES constants duplicated verbatim: BannerConfig.php:8-12
and Settings.php:10-14. Default values duplicated: `Settings::defaults()` banner
section (`'bar','auto','#2563eb',8`) at Settings.php:54-61 vs
`BannerConfig::defaults()` at BannerConfig.php:126-133. Admin re-enumerates the same
vocabularies a third time as choice lists (Admin.php:622-639
position_choices/theme_choices). `BannerConfig::with_overrides` re-validates
`self::in_list( ...self::POSITIONS... )` (BannerConfig.php:53-54) against data
`Settings::sanitize_banner` already validated (Settings.php:213-219) — double
enforcement of one invariant on self-authored data. Snippet locations repeat the
pattern: Settings.php:25, Admin.php:655-666, SettingsHydrator.php:249 all hard-code
head/body/footer.

**Direction.** Make BannerConfig the single owner: expose POSITIONS/THEMES publicly
(or as small enums) and make `Settings::defaults()['banner']` derive from
`BannerConfig::defaults()`; Admin builds its choice lists over the same constants
(gettext labels stay in Admin). Then drop the re-validation in `with_overrides` —
sanitize is the one enforcement point for UI-authored data. Same treatment for snippet
locations (one constant, likely on the `custom` catalog entry).

**Severity.** medium

**Verifier notes.** Every cited line verified; the with_overrides re-validation
provably double-enforces sanitize output — Gate.php:74 feeds it
`Settings::from_wp()->effective()` through its only call site
(SettingsHydrator.php:296) with no filter able to inject unsanitized data. The drift
is silent: the overlapping BannerConfig defaults are dead at runtime (Settings' copy
always wins via `effective()`), and SettingsTest.php:36 / BannerConfigTest.php:15 pin
each copy independently without pinning them together, so divergence keeps all 250
tests green. Minor correction: SettingsHydrator.php:249 hard-codes only the `head`
fallback, not the full location list, but Admin.php:665 carries a second full inline
copy, so the count stands; assets/banner-config.js:14 is a fourth copy across the
PHP/JS seam.

## Deduplicate the banner shell shared verbatim by renderOptIn and renderOptOut

**Problem.** banner.js (537 lines, the largest module in the repo) is not a god
module — its interface to the gate is clean (`initBanner( api, rawSlice, env )` →
`{ destroy }`, talking back only through the public `window.consentful` api) — but its
two Policy variants duplicate the entire banner chrome verbatim. Every fix to the
panel chrome, focus handling, or prefs reveal (the exact area the host-theme-isolation
work keeps touching) must be made twice and verified twice, and a third Notice variant
would copy it a third time.

**Evidence.** Verbatim or near-verbatim duplication between the variants: root
construction + CSS custom props + pill/toast + body appends (banner.js:155-216 vs
388-438); focusFirst (banner.js:292-299 vs 449-456, identical); revealPrefs
(banner.js:321-333 vs 473-485, differs only in which sibling button hides); destroy
(banner.js:368-377 vs 525-533); the privacy-link and customize-button blocks
(banner.js:185-196 vs 407-418). The genuinely variant behavior is small: the modal
focus trap + background inert (225-277, opt-in only), the action sets
(reject/save/accept vs DNS/save/close), and Escape semantics (hide vs acknowledge).

**Direction.** Extract one shared shell builder inside banner.js —
`buildShell({ variantClass, aria, actionButtons })` returning
`{ root, inner, prefs, inputs, customizeBtn, pill, toast, focusFirst, revealPrefs,
destroyChrome }` — and reduce each variant to its behavioral wiring (trap/inert +
decision handlers for opt-in; acknowledge/DNS for opt-out). No new file, no change to
the gate-facing interface or the banner.test.js surface. Rough shape: buildShell ~90
lines owned once, renderOptIn ~70, renderOptOut ~45.

**Severity.** medium

**Verifier notes.** Every cited line range accurate: focusFirst is
character-identical, the chrome verbatim. The friction is demonstrated by history, not
speculation — commit 1cc2f99 (toast + a11y) made mirrored edits in both renderers, and
itself already extracted buildToast/buildPill/buildPrefs, so buildShell completes an
in-progress direction. Caveat: the "third Notice variant" argument is overstated —
`PolicyType::NoticeOnly` currently maps to `shows_banner() === false` so no notice
renderer is pending (see the tension note above); the two-variant dedup case stands on
its own.

## banner-config.js split-brain: re-declared coercion primitives and English copy defaults that already drifted from PHP gettext

**Problem.** Two split-brains in one module. (1) banner-config.js re-declares the
toBool/toStr/toInt/toObject coercion primitives verbatim from lib/config.js, so the
gate bundle carries two copies whose semantics must agree by luck. (2) COPY_DEFAULTS
duplicates 14 banner strings whose canonical owner is PHP gettext
(`BannerConfig::defaults()`; per ADR 0004 banner copy is gettext-only) — production
never serves the JS strings because PHP always emits the full copy map, yet
banner.test.js passes `copy: {}` and asserts against them, so the test surface
exercises strings no Visitor ever sees. The two sources have already drifted.

**Evidence.** banner-config.js:1-12 duplicates lib/config.js:1-14
(toBool/toStr/toInt/toObject, character-identical). COPY_DEFAULTS at
banner-config.js:20-36 mirrors BannerConfig.php:136-150 — except banner-config.js:22
has `description: ''` while BannerConfig.php:137 ships a full sentence (existing
drift), and `BannerConfig::with_overrides` (BannerConfig.php:48-62) never touches
copy, so PHP always emits every key. Tests lean on the phantom defaults:
banner.test.js:48 (`copy: {}`), banner.test.js:5 and :150 assert the English literals.
The `humanize()` fallback (banner-config.js:79-82) is different — it IS the live path
for Developer-added Purposes, since `BannerConfig::with_purpose_overrides`
(BannerConfig.php:85-96) only iterates the five shipped purpose keys.

**Direction.** Export the coercion primitives from lib/config.js and import them in
banner-config.js (the decider bundle already inlines them via parseConfig, so it gains
nothing); move banner-config.js under assets/lib/ alongside the other pure helpers per
CLAUDE.md. Collapse COPY_DEFAULTS to plain `''` fallbacks (parseCopy already coerces),
leaving PHP gettext as the single owner of banner copy, and have tests/js feed
PHP-shaped copy through the shared helper instead of relying on dead JS defaults. Keep
`humanize()` — it earns its keep for Developer purposes.

**Severity.** low

**Verifier notes.** Every cited line verified; the gate bundle does carry both copies
(gate.js imports lib/config.js, banner.js → banner-config.js), COPY_DEFAULTS is dead
in production (with_overrides/with_purpose_overrides/with_privacy_fallback all pass
copy through, ClientConfig.php:49 emits it) yet has already drifted, and
banner.test.js (lines 5, 48, 225, 419, 421) asserts the phantom English strings. ADR
0004 explicitly makes banner copy gettext-only with PHP as owner, so the fix
reinforces a documented decision; the humanize() live-path claim checks out.

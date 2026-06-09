# Client state resolver: one owner for the decider/gate consent pipeline

Deferred from the 1.0.0 gate (release-first triage) — 2026-06-09 architecture audit.
File:line references describe the tree at audit time and may have drifted.

## Make the decider/gate consent-state agreement structural, not by-discipline

**Problem.** The two bundles must compute identical grants from the same
cookie+geo+GPC inputs — the decider emits the Consent Mode default from them, the gate
loads tags from them. Today that invariant is maintained by both entry modules
hand-copying the same five-call pipeline; if a maintainer changes one (e.g. adds a
re-consent condition to the gate's readStored) and forgets the other, the decider
tells Google "granted" while the gate denies — a silent compliance leak in the
riskiest code.

**Evidence.** decider.js:25-44 and gate.js:52-79 duplicate the orchestration verbatim:
the same validateConsent envelope (`{ schemaVersion, policyVersion, maxAgeMs }`) is
hand-built twice (decider.js:27-31, gate.js:64-68); the GPC sniff
`win.navigator && win.navigator.globalPrivacyControl === true` is copied
(decider.js:34, gate.js:53); the resolveJurisdictionSync → activeJurisdiction →
computeGrants sequence is copied (decider.js:36-44, gate.js:55-59). The decider also
re-implements the gtag shim inline (decider.js:18-23) instead of importing the
existing resolveGtag (adapters/google-gtag.js:7-15).

**Direction.** Add one composing lib module (e.g. assets/lib/state.js exporting
`resolveConsentState( config, { win, doc } )` →
`{ stored, gpc, resolvedId, resolved, grants }`) that both entries call; it becomes
the single owner of the validation envelope, the GPC sniff, and the one strictest
fallback (see below). The gate keeps its local mutation: `recompute()` still calls
computeGrants with its mutated stored/policy. Decider also imports resolveGtag instead
of re-shimming. The interface is the test surface: one Vitest file pins the
cross-bundle invariant instead of two entry tests pinning it independently. Two
callers across the two mandated bundles = the real seam created by the sacrosanct
inline/footer split.

**Severity.** medium

**Verifier notes.** Every cited line verified; the gate never reads the decider's
`_init` (agreement is purely by-discipline), and the proposed lib module passes the
deletion test and conflicts with no ADR (CLAUDE.md sanctions shared assets/lib/
helpers). One evidence correction, recorded below as its own item: the finding
reversed which fallback Policy literal is dead.

## Contradictory strictest-fallback literals in lib/config.js vs lib/jurisdiction.js

**Problem.** The two "no usable config" fallback policies disagree with each other:
lib/config.js:45-48 synthesizes a `*` policy via `parsePolicy( {} )` with
`showsBanner: false`, while lib/jurisdiction.js:78-89 hard-codes an opt-in fallback
with `showsBanner: true` — only the latter matches ADR 0002's "strictest = banner
shown". Two modules each carry a fallback Policy literal; they contradict each other,
and one of them is dead code.

**Evidence.** lib/config.js:45-48 (parseJurisdictions' lenient synthesis);
lib/jurisdiction.js:78-89 (activeJurisdiction's strict literal). Verifier traced
reachability: through parseConfig, the config.js synthesis is the live branch and the
jurisdiction.js strict literal is dead — and both are unreachable with server-emitted
config, since SettingsHydrator.php:267 always supplies a `*` jurisdiction.

**Direction.** The shared resolver (above) becomes the single fallback owner: exactly
one strictest-fallback Policy literal exists, in assets/lib/state.js, matching ADR
0002 (opt-in, deny-all, banner shown); the contradictory literals in
parseJurisdictions and activeJurisdiction are deleted.

**Severity.** low (folded into the resolver work)

**Verifier notes.** The original finding reversed which fallback is dead: it is
parseJurisdictions' lenient synthesis (config.js:45-48) that is live and
activeJurisdiction's strict literal (jurisdiction.js:78-89) that is dead through
parseConfig. Both are unreachable with server-emitted config, so this is a latent
contradiction rather than a live leak — but the remedy is identical: one owner.

## Adapter config slice escapes coercion: decider checks adsDataRedaction/urlPassthrough with strict === true

**Problem.** parseAdapters (config.js:86-93) is the only region of the blob whose
inner fields are not explicitly coerced, violating the CLAUDE.md coercion rule. The
decider coerces waitForUpdate (decider.js:55, parseInt) but tests
`adapter.adsDataRedaction === true` and `adapter.urlPassthrough === true`
(decider.js:58, :61). First-party safe (GoogleAdapter.php:53-55 emits real bools via
wp_json_encode), but the Adapter seam is real — two implementations plus the
`consentful_adapters` Developer hook (Gate.php:99-103) where `client_config()` is
`array<string, mixed>`; a Developer returning `1` or `'1'` silently loses
ads_data_redaction.

**Evidence.** decider.js:58 `if ( adapter.adsDataRedaction === true && … )` and :61
`if ( adapter.urlPassthrough === true )` vs the codebase convention toBool
(config.js:6) which accepts `'true'`/`1`/`'1'`; parseAdapters returns raw inner
objects untouched (config.js:90-91).

**Direction.** Run the two flags through toBool in the decider (or coerce known
google-handler fields in parseAdapters). A few lines; keeps the documented coercion
invariant uniform across the blob. Natural to fold into the resolver/coercion pass
above (toBool needs exporting from lib/config.js first — the banner cluster wants the
same export).

**Severity.** low

**Verifier notes.** Every cited line checks out; line 55 in the same branch coerces
waitForUpdate with parseInt, making the strict comparisons an inconsistency, not a
style choice. A non-bool flag is contract-legal (the hook's interface is
`array<string, mixed>`; README even documents `handler => 'google'`), though only
reachable by a Developer bypassing the documented GoogleAdapter recipe, whose bool
constructor params coerce truthy scalars. First-party path is type-safe end to end —
silent loss of a privacy signal, fix opportunistically.

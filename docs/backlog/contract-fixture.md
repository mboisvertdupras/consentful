# Contract fixture: one checked-in shape for the PHP→JS config seam

Deferred from the 1.0.0 gate (release-first triage) — 2026-06-09 architecture audit.
File:line references describe the tree at audit time and may have drifted.

> **Sequencing note (post-gate):** the 1.0 gate added a wp-env smoke job covering the
> integration end of this seam (real WP serves the blob and the built decider is
> inlined in the page; the built IIFE *executes* only in the Vitest smoke, against a
> hand-built config). The fixture below remains the *unit-level* parity fix — it catches
> shape drift in the fast suites, where the smoke job only catches total breakage.
> Build it AFTER the wire trim has landed: the fixture must pin the trimmed shape, not
> freeze the seven dead fields back into the contract.

## The PHP-to-JS config contract is two hand-maintained twins with no shared fixture

**Problem.** The seam between `Frontend\ClientConfig` (producer) and
assets/lib/config.js `parseConfig` (consumer) is the plugin's most important
interface, but each side is tested against its own hand-written description of the
shape. A key rename or restructure on either side keeps both suites fully green while
breaking every production page — the canonical "passing tests lie" setup, and drift
has already started.

**Evidence.** tests/js/helpers.js:30-81 hand-builds makeConfig with no `banner` key,
though `ClientConfig::to_array()` unconditionally emits one (ClientConfig.php:49), and
with 5 purposes including personalization while the PHP defaults emit 4
(ClientConfigTest.php:68-92). ClientConfigTest asserts the PHP shape key-by-key
(ClientConfigTest.php:151, :163, :181) and the JS suite asserts the parse of a
different, parallel shape (lib-config.test.js:5 "coerces the §1 shape") — nothing
mechanically ties them together.

**Direction.** Introduce a single checked-in JSON fixture (e.g.
tests/fixtures/client-config.json) as the contract: a PHP test asserts
`wp_json_encode( ClientConfig::to_array() )` for a representative settings seed equals
the fixture; the JS suites import the same file and overlay per-test deltas.
makeConfig becomes a thin spread over the fixture or dies. Per the verifier, extend
the same treatment to the banner slice: banner config bypasses parseConfig
(gate.js:224) and banner.test.js builds a third hand-rolled shape, so the gate suite
always runs `initBanner( undefined )` — a shape production never emits.

**Severity.** medium

**Verifier notes.** Every cited line verified; no fixture, snapshot, or integration
test tied producer (Gate.php:35-41) to consumers at audit time. The finding
*understates* the seam — the banner slice is a third hand-rolled twin (see Direction).
The fail-closed coercers bound most drift to strictest-policy degradation, but drift
can still silently kill all tag firing or suppress the banner under opt-in with both
suites green — a compliance-relevant gap at the plugin's riskiest interface. The
fixture passes the deletion test: a net deletion of triplicated shape knowledge, no
ADR conflict.

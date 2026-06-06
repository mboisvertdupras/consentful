# Jurisdiction-aware consent model with durable proof

## Status

accepted — builds on ADR 0001

## Context

ADR 0001 made the core vendor-neutral and client-side gated. That core still has to
decide *what consent even means* for a given visitor. The target markets (Québec
Loi 25, EU/UK GDPR) are opt-in regimes, but the product is meant to be a real CMP,
and US state laws (CPRA/CCPA…) are opt-*out*. Both Loi 25 and GDPR also require the
controller to **demonstrate** consent — which a client-only cookie cannot do.

## Decision

**Multi-jurisdiction from day one.** A `Policy` has three shapes: **Opt-in** (deny
by default, banner, block before consent — Loi 25/GDPR), **Opt-out** (allow by
default, notice + Do Not Sell/Share + honor GPC — US), and **Notice/None**. A
`Jurisdiction` is resolved per visitor and selects a Policy.

**Geo is client/edge-resolved and fail-closed.** Server-side per-request geo is
rejected (it poisons full-page caches — same reason as client-side gating). HTML is
identical for everyone; until the region is known the visitor is treated under the
**strictest** Policy (opt-in, deny-all, banner shown). A pluggable resolver prefers
an edge-set signal (CDN geo header → JS-readable cookie/var), falling back to a
lightweight geo endpoint or an integrator-supplied source. **GPC is honored
instantly**, regardless of region, and suppresses the banner. Rejected: fail-open
to a configured home jurisdiction (under-protects strict-regime visitors during the
unknown window) and blocking render on a synchronous geo lookup (perf/resilience
cost).

**Purpose taxonomy: a default set, integrator-extensible.** Ship Necessary,
Functional, Analytics, Marketing (+ optional Personalization); the Integrator may
add/rename/remove purposes in code. Tags declare the purpose(s) that gate them; the
Google adapter declares its own purpose→signal mapping (no longer hard-coded).
Rejected: a fixed non-extensible set (breaks the universal promise for unusual
vendors) and a no-defaults freeform model (no compliance guardrails).

**Durable, pseudonymous proof of consent.** The cookie stays the runtime source of
truth (with jurisdiction + policy/schema version added for re-consent logic). On
each decision an async REST call writes a pseudonymous `Consent record` (consent id,
timestamp, purposes, jurisdiction, policy/schema/banner version; optional hashed
IP/UA, retention-limited) to a built-in `Consent log` table, exportable for an
auditor. A `Sink` interface lets the Integrator redirect records to their own store.
The separate non-cached endpoint keeps caching intact. Rejected: client-cookie-only
(can't demonstrate consent — a gap on exactly the strict regimes targeted) and a
pluggable-only sink with no built-in store (silent non-compliance for sites that
never wire one). This deliberately pulls proof-of-consent in from the "compliance
suite" scope that was otherwise set aside.

## Consequences

- The product now stores visitor data server-side; retention limits and
  pseudonymization are part of the design, not an add-on.
- `CMV2_SCHEMA_V` becomes a pair: a purpose-set **schema** version and a **policy**
  version; either changing triggers re-consent.
- The banner is no longer one shape — it must render opt-in / opt-out / notice
  variants driven by the resolved Policy.
- A re-consent trigger on policy/schema change must exist, fed by the version fields
  now carried in both the cookie and each Consent record.

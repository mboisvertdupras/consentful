# Product

## Register

product

## Users

**Administrator** (primary, required) — the WordPress admin who installs Consentful
from WordPress.org. Not a developer, not a privacy lawyer: a site owner who needs the
site compliant with Québec Loi 25, GDPR, and US opt-out laws. Their context is
wp-admin; their job is "make my site compliant without writing code or learning consent
law." Install + activate must already yield a compliant baseline; the admin UI only
tunes it.

**Visitor** (the consent subject) — the person browsing the site, in any jurisdiction,
on any device. Their context is a page they came to read, interrupted by a consent
decision they did not ask to make. Their job is to understand the choice and get on with
it. They judge the product by the banner: its clarity, speed, and fairness. They never
see the admin.

**Developer** (optional) — a power user extending Consentful through documented hooks (a
custom adapter, an extra purpose, a custom Sink). Never required; never overrides the
Administrator's UI settings.

## Product Purpose

Consentful is a general-purpose, open-source WordPress universal consent layer. It gates
every non-essential third-party tag behind visitor consent, adapts to the visitor's
jurisdiction, and keeps demonstrable proof of consent, so the *site* (not one vendor's
tag) meets Loi 25 / GDPR / US opt-out law. Google Consent Mode is the first integration,
not the boundary.

Success: a non-expert installs from WordPress.org and is compliant straight from the
admin UI, with no code and no prior consent-management knowledge. The visitor gets a
clear, fair, fast consent choice. The admin can produce an auditor-ready record on
demand.

## Brand Personality

**Clear and plain-spoken** is the defining trait. No legalese, no jargon, no acronym
soup. Every option in the admin and every line in the banner is legible to someone who
has never heard of "Consent Mode" or "Loi 25." Where a regulatory term is unavoidable,
it is explained in one plain sentence. The voice is quietly competent: it has done the
legal homework so the admin does not have to, and it states the sensible default rather
than presenting a wall of choices. Calm, never alarmist; helpful, never salesy.

## Anti-references

- **Enterprise compliance dread.** OneTrust / TrustArc-style heavyweight consoles that
  read as legal software and intimidate a solo site owner. Consentful is the opposite:
  small, legible, unintimidating.
- **Nag-heavy freemium plugins.** No upsell banners, no "go Pro" interruptions, no review
  nags, no feature-locked teasers (the CookieYes / Complianz freemium feel). The admin
  surface stays clean and free of marketing.
- **Cookie-wall modals and dark patterns.** Never block the page to coerce acceptance;
  never make Reject harder, smaller, or less prominent than Accept. Equal prominence is a
  hard rule, not a preference.
- **Custom admin that fights WordPress core.** No re-skinned admin takeover. The settings
  page lives inside wp-admin conventions and looks native there.

## Design Principles

1. **Banner-first craft.** The visitor banner is where design ambition lives: it is on
   every page, for every visitor, in every jurisdiction. The admin stays deliberately
   WordPress-native (form-table, native pickers, widefat tables); the banner is the
   surface worth polishing.
2. **No expertise required.** A non-expert reaches a compliant configuration and
   understands every choice. Plain language beats precision-by-jargon; sensible defaults
   beat exhaustive options.
3. **Compliant by default.** Install + activate is already correct (geo-adaptive,
   strictest-until-known, banner shown). The UI tunes a working baseline; it never gates
   compliance behind setup.
4. **Equal prominence, no coercion.** Reject is as easy and as prominent as Accept: same
   screen, one click, identical size and weight. Honor GPC instantly. Never a cookie
   wall. Fairness is the visible promise of the banner.
5. **Cache-safe by construction.** Every visitor receives identical HTML; the banner and
   gating are client-side. No design choice may vary server-rendered output per visitor;
   the banner adapts at runtime, not on the server.

## Accessibility & Inclusion

Target: **WCAG 2.2 AA.** The banner already carries the load: a focus trap and `inert`
background on the modal variant, equal-prominence controls, 44px minimum touch targets,
focus-visible outlines drawn from the panel foreground for guaranteed contrast, and
light / dark / auto theme palettes tuned for contrast. Hold new work to the same bar:
body contrast ≥ 4.5:1, large and UI contrast ≥ 3:1, target-size minimums, visible focus,
and a `prefers-reduced-motion` alternative for any motion added. The banner must stay
operable by keyboard and screen reader in every position and every policy variant.
Language (locale) is a separate axis from jurisdiction; banner copy is translated via
gettext, never hard-coded per region.

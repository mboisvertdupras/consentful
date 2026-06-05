# Consent Mode v2 — Loi 25 & RGPD

A white-label WordPress plugin that owns the site's Google tag and only loads it
**after** the visitor consents — what makes "prior consent" true under Québec
Loi 25 and the GDPR. Reusable across client sites: no brand-specific code,
prefix `cmv2_`, fully themeable.

## What it does

- **Block before consent (basic Consent Mode v2).** No `gtag.js`, no `config`,
  no cookieless ping until the visitor chooses. A returning visitor with valid,
  unexpired consent gets the tag on the first hit.
- **All four CMv2 signals** (`ad_storage`, `ad_user_data`, `ad_personalization`,
  `analytics_storage`) plus `functionality_/personalization_/security_storage`.
- **Loi 25 friendly banner.** Reject all is as easy as Accept all — same screen,
  one click, identical size — granular per-category, easy withdrawal, no
  pre-ticked boxes, re-consent window.
- **Fully customizable.** Primary color, light/dark/auto theme, position
  (bottom bar / floating corner / centered modal), button radius, custom copy,
  optional floating re-open button.
- **Translation-ready.** English source + bundled French (fr_CA / fr_FR);
  `.pot` template included.
- **Accessible.** Keyboard-operable, focus management, modal focus-trap +
  background `inert`, 44px touch targets.

Configure under **Settings → Consent Mode v2** after entering a GA4 measurement
ID. See `readme.txt` for the full WordPress.org-format plugin readme.

## Local development (symlink into a WP install)

The canonical copy lives in this repo. Symlink it into any local WordPress so
edits stay in sync — WordPress resolves symlinked plugins via
`wp_register_plugin_realpath()`, so asset URLs and `plugin_basename()` work:

```sh
ln -s "$(pwd)" /path/to/wp-content/plugins/consent-mode-v2
```

Then activate it from the Plugins screen as usual.

## Rebuilding translations

Translations live in the `.po` files under `languages/` (the gettext source of
truth). The build is driven by npm scripts that wrap [WP-CLI](https://wp-cli.org/)
— no custom script. After changing translatable strings in the PHP:

```sh
npm run build
```

That runs three steps, which you can also run individually:

```sh
npm run i18n:pot   # regenerate languages/consent-mode-v2.pot from PHP source
npm run i18n:po    # sync the fr_CA / fr_FR .po files against the new .pot
npm run i18n:mo    # compile the .po files to .mo
```

After `i18n:po`, fill in any new/changed `msgstr` entries directly in the French
`.po` files, then run `npm run i18n:mo` to recompile. Requires WP-CLI on your
`PATH` (`wp`).

## Building a distributable zip

`npm run package` builds the assets + translations and produces
`consent-mode-v2.zip` — the installable plugin with every dev file excluded (the
exclusion list lives in `.distignore`). Pushing a `vX.Y.Z` tag runs the
[release workflow](.github/workflows/release.yml), which does the same and
attaches the zip to a GitHub Release.

Packaging uses [`wp dist-archive`](https://github.com/wp-cli/dist-archive-command)
v3, which needs WP-CLI ≥ 2.13. Until that ships as stable, point local WP-CLI at
the nightly: `wp cli update --nightly`.

## License

GPL-2.0-or-later.

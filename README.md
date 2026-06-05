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

UI strings live in `tools/build-translations.py` (English msgid → French msgstr).
After changing translatable strings:

```sh
# 1) regenerate the template + .po from PHP source (requires wp-cli)
wp i18n make-pot . languages/consent-mode-v2.pot

# 2) fill the French .po files from the dictionary
python3 tools/build-translations.py

# 3) compile .mo (requires gettext's msgfmt, or `wp i18n make-mo languages`)
msgfmt languages/consent-mode-v2-fr_CA.po -o languages/consent-mode-v2-fr_CA.mo
msgfmt languages/consent-mode-v2-fr_FR.po -o languages/consent-mode-v2-fr_FR.mo
```

## License

GPL-2.0-or-later.

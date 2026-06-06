# Consentful — universal consent layer

A white-label, open-source WordPress **universal consent layer**. It gates **all**
non-essential third-party tags behind visitor consent, adapts to the visitor's
jurisdiction, and keeps demonstrable proof of consent — so the **site** (not merely
one vendor's tag) meets Québec Loi 25 / GDPR / US opt-out laws. Reusable across
client sites: no brand-specific code, prefix `consentful_`, fully themeable. Google
Consent Mode is the first integration, not the boundary.

> **Status.** `1.0.0` implements the full architecture of ADR 0001/0002/0003: the PSR-4
> OOP domain core (container, Purpose model, Signal, Consent, Tag, Adapter,
> Jurisdiction/Policy registries), the cache-safe client gate + Google Consent Mode v2
> adapter, the geo-adaptive jurisdiction resolver (edge signal → non-cached endpoint,
> fail-closed), the opt-in / opt-out (Do Not Sell/Share) / notice banner variants,
> durable proof of consent (record + log table + Sink + REST), and the constrained
> site-owner admin UI.

## What it does

- **Gate every non-essential tag.** Each tag is assigned to one or more **purposes**
  and fires only when all are granted — either **Direct** (a Consentful adapter
  injects it) or **Delegated** (an external tag manager fires it, gated via a consent
  push to the dataLayer).
- **Geo-adaptive, multi-jurisdiction.** A **Policy** is Opt-in (deny by default,
  banner, block-before-consent — Loi 25/GDPR), Opt-out (allow by default, notice +
  Do Not Sell/Share + honor GPC — US), or Notice/None. Until the region is known, the
  strictest policy applies (fail-closed); GPC is honored instantly.
- **Cache-safe by design.** Every visitor receives identical HTML; an inline `<head>`
  decider plus per-adapter JS reads the consent cookie at runtime and injects only
  granted tags — correct behind full-page caches / CDNs.
- **Google Consent Mode v2.** Google is just a rich **adapter** that additionally
  emits Consent Mode v2 signals (default-deny, `wait_for_update`, cookieless pings,
  `ads_data_redaction`, `url_passthrough`) to preserve conversion modeling.
- **Proof of consent.** Each decision is recorded (consent id, timestamp, purposes,
  jurisdiction, policy/schema/banner version) to a built-in consent log, exportable
  for an auditor; a Sink interface lets integrators redirect records to their store.
- **Translation-ready.** English source + bundled French (fr_CA / fr_FR); `.pot`
  template included. Language (locale) is a separate axis from jurisdiction (geo).

The audience is **integrators** (agencies/devs): adapters, tags, purpose mappings,
jurisdiction policy and banner defaults are declared in code/config — the source of
truth — and any setting can be locked. The site owner gets a deliberately constrained
admin UI. See `readme.txt` for the WordPress.org-format user readme.

## Integration (in code)

Integrators wire everything through the `consentful_register` action, which hands over
the DI container — the single registration surface (ADR 0003). Register from your theme
or a companion plugin:

```php
add_action(
	'consentful_register',
	function ( \Consentful\Container\Container $c ): void {
		// 1. Register the Google adapter (Consent Mode v2) with your measurement IDs.
		$c->get( \Consentful\Adapter\AdapterRegistry::class )
			->add( new \Consentful\Adapter\GoogleAdapter( array( 'G-XXXXXXXX' ) ) );

		// 2. Gate a tag on the 'analytics' purpose; the Google adapter fires it (Direct).
		//    site_toggleable: true lets the site owner switch it off in the admin UI.
		$c->get( \Consentful\Tag\TagRegistry::class )->add(
			new \Consentful\Tag\Tag(
				id: 'ga4',
				label: 'Google Analytics 4',
				purposes: array( \Consentful\Consent\DefaultPurpose::Analytics ),
				delivery: \Consentful\Tag\Delivery::Direct,
				adapter_id: 'google',
				site_toggleable: true,
			)
		);

		// 3. Opt in to the optional Personalization purpose (off by default).
		$c->get( \Consentful\Consent\PurposeRegistry::class )
			->add( \Consentful\Consent\DefaultPurpose::Personalization );

		// 4. Override banner copy/appearance, geo resolution, or the proof Sink by
		//    rebinding the matching value object, e.g.:
		//    $c->singleton( \Consentful\Frontend\GeoConfig::class, fn() => /* your GeoConfig */ );
	}
);

// Lock fields so the site owner cannot change them in the constrained admin UI.
add_filter( 'consentful_locked_settings', fn( array $locked ) => array( 'position', 'theme' ) );
```

## Local development (symlink into a WP install)

The canonical copy lives in this repo. Symlink it into any local WordPress so edits
stay in sync — WordPress resolves symlinked plugins via `wp_register_plugin_realpath()`,
so asset URLs and `plugin_basename()` work:

```sh
ln -s "$(pwd)" /path/to/wp-content/plugins/consentful
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
npm run i18n:pot   # regenerate languages/consentful.pot from PHP source
npm run i18n:po    # sync the fr_CA / fr_FR .po files against the new .pot
npm run i18n:mo    # compile the .po files to .mo
```

After `i18n:po`, fill in any new/changed `msgstr` entries directly in the French
`.po` files, then run `npm run i18n:mo` to recompile. Requires WP-CLI on your
`PATH` (`wp`).

## Building a distributable zip

`npm run package` builds the assets + translations and produces `consentful.zip` —
the installable plugin with every dev file excluded (the exclusion list lives in
`.distignore`). Pushing a `vX.Y.Z` tag runs the
[release workflow](.github/workflows/release.yml), which does the same and attaches
the zip to a GitHub Release.

Packaging uses [`wp dist-archive`](https://github.com/wp-cli/dist-archive-command)
v3, which needs WP-CLI ≥ 2.13. Until that ships as stable, point local WP-CLI at
the nightly: `wp cli update --nightly`.

## License

GPL-2.0-or-later.

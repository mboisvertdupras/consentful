<?php
declare( strict_types = 1 );

namespace Consentful\Admin;

/**
 * The canonical `consentful_settings` option (`CONSENTFUL_OPTION`) — the single source
 * of truth the Administrator edits in the admin UI. Constructable from an array so the
 * sanitize/merge logic is pure and unit-testable; `from_wp()` reads the live option.
 *
 * `sanitize()` is the `register_setting` callback: an allowlist + coercion that drops
 * unknown keys. The typed accessors (`banner()`, `purposes()`, `tags()`, `geo()`,
 * `proof()`) return the EFFECTIVE config — stored values deep-merged over `defaults()` —
 * so an empty option yields the full compliant baseline. `all()` returns the raw stored
 * array for the admin form (stored-vs-placeholder).
 *
 * Banner *copy* is never stored here: it ships as gettext, translated in the language
 * files. Custom-snippet `code` is stored RAW (admin `unfiltered_html` trust) — it is only
 * ever injected by JS, never printed as a literal `<script>`.
 */
final class Settings {

	/** The settings-schema version (migration guard). */
	private const VERSION = 1;

	/** @var list<string> Allowed banner positions. */
	private const POSITIONS = array( 'bar', 'corner', 'modal' );

	/** @var list<string> Allowed banner themes. */
	private const THEMES = array( 'light', 'dark', 'auto' );

	/** Max corner radius the Administrator may set (px). */
	private const MAX_RADIUS = 32;

	/** @var list<string> The fixed default purpose keys (compliance guardrails). */
	private const PURPOSE_KEYS = array( 'necessary', 'functional', 'analytics', 'marketing', 'personalization' );

	/** @var list<string> Allowed global postures when geo is not adaptive. */
	private const GLOBAL_POLICIES = array( 'opt_in', 'opt_out', 'notice_only' );

	/** @var list<string> Allowed custom-snippet injection locations. */
	private const SNIPPET_LOCATIONS = array( 'head', 'body', 'footer' );

	/**
	 * @var array<string, list<string>> Catalog keys → their allowed flat field keys. `custom`
	 * is special: its fields are a `fragments` list (see `sanitize_custom_fields`), so it has
	 * no flat keys here — the empty list just registers `custom` as a known catalog.
	 */
	private const CATALOG_FIELDS = array(
		'ga4'        => array( 'measurementId' ),
		'google-ads' => array( 'conversionId' ),
		'gtm'        => array( 'containerId' ),
		'meta-pixel' => array( 'pixelId' ),
		'custom'     => array(),
	);

	/**
	 * @param array<array-key, mixed> $stored The sanitized stored option (possibly partial).
	 */
	public function __construct(
		private readonly array $stored,
	) {}

	/** Read the live option from WordPress. */
	public static function from_wp(): self {
		$stored = get_option( CONSENTFUL_OPTION, array() );
		return new self( is_array( $stored ) ? $stored : array() );
	}

	/**
	 * The full compliant default settings array — the merge base for the typed accessors
	 * and the placeholder source for the admin form.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'version'  => self::VERSION,
			'banner'   => array(
				'enabled'      => true,
				'position'     => 'bar',
				'theme'        => 'auto',
				'primaryColor' => '#2563eb',
				'radius'       => 8,
				'privacyUrl'   => '',
			),
			'purposes' => array(
				'personalization' => array( 'enabled' => false ),
			),
			'tags'     => array(),
			'geo'      => array(
				'adaptive'     => true,
				'globalPolicy' => 'opt_in',
			),
			'proof'    => array(
				'enabled' => true,
			),
		);
	}

	/**
	 * The raw sanitized stored array (for the admin form to show stored-vs-placeholder).
	 *
	 * @return array<array-key, mixed>
	 */
	public function all(): array {
		return $this->stored;
	}

	/**
	 * The EFFECTIVE merged section map (stored over defaults) the hydrator consumes.
	 *
	 * @return array<string, mixed>
	 */
	public function effective(): array {
		return array(
			'banner'   => $this->banner(),
			'purposes' => $this->purposes(),
			'tags'     => $this->tags(),
			'geo'      => $this->geo(),
			'proof'    => $this->proof(),
		);
	}

	/**
	 * Effective banner appearance: stored merged over defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function banner(): array {
		return $this->merged( 'banner' );
	}

	/**
	 * Effective per-purpose config: stored copy overrides + personalization toggle merged
	 * over defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function purposes(): array {
		/** @var array<string, mixed> $out */
		$out = self::default_section( 'purposes' );

		foreach ( self::array_value( $this->stored['purposes'] ?? null ) as $key => $value ) {
			$base        = self::array_value( $out[ $key ] ?? null );
			$out[ $key ] = array_merge( $base, self::array_value( $value ) );
		}
		return $out;
	}

	/**
	 * The ordered list of stored tag entries (catalog instances + custom snippets). Tags
	 * have no merge base — the stored list is authoritative.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function tags(): array {
		$out = array();
		foreach ( self::array_value( $this->stored['tags'] ?? null ) as $tag ) {
			if ( is_array( $tag ) ) {
				$out[] = self::array_value( $tag );
			}
		}
		return $out;
	}

	/**
	 * Effective geo config: stored merged over defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function geo(): array {
		return $this->merged( 'geo' );
	}

	/**
	 * Effective proof config: stored merged over defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function proof(): array {
		return $this->merged( 'proof' );
	}

	/**
	 * A top-level associative section merged over its defaults (one level deep).
	 *
	 * @return array<string, mixed>
	 */
	private function merged( string $section ): array {
		return array_merge(
			self::default_section( $section ),
			self::array_value( $this->stored[ $section ] ?? null )
		);
	}

	/**
	 * A default section as a typed map.
	 *
	 * @return array<string, mixed>
	 */
	private static function default_section( string $section ): array {
		return self::array_value( self::defaults()[ $section ] ?? null );
	}

	/**
	 * Narrow an untyped value to a string-keyed map (non-arrays become empty).
	 *
	 * @return array<string, mixed>
	 */
	private static function array_value( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $key => $item ) {
			$out[ (string) $key ] = $item;
		}
		return $out;
	}

	/**
	 * The PURE heart: allowlist + coerce the raw POSTed option, dropping unknown keys. The
	 * `register_setting` sanitize callback delegates here. All values arrive untrusted.
	 *
	 * @param array<array-key, mixed> $raw The raw submitted option.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $raw ): array {
		$out = array( 'version' => self::VERSION );

		if ( array_key_exists( 'banner', $raw ) ) {
			$out['banner'] = self::sanitize_banner( $raw['banner'] );
		}
		if ( array_key_exists( 'purposes', $raw ) ) {
			$out['purposes'] = self::sanitize_purposes( $raw['purposes'] );
		}
		if ( array_key_exists( 'tags', $raw ) ) {
			$out['tags'] = self::sanitize_tags( $raw['tags'] );
		}
		if ( array_key_exists( 'geo', $raw ) ) {
			$out['geo'] = self::sanitize_geo( $raw['geo'] );
		}
		if ( array_key_exists( 'proof', $raw ) ) {
			$out['proof'] = self::sanitize_proof( $raw['proof'] );
		}

		return $out;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function sanitize_banner( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$out = array();
		if ( array_key_exists( 'enabled', $value ) ) {
			$out['enabled'] = (bool) $value['enabled'];
		}
		$position = self::in_list( $value['position'] ?? null, self::POSITIONS );
		if ( null !== $position ) {
			$out['position'] = $position;
		}
		$theme = self::in_list( $value['theme'] ?? null, self::THEMES );
		if ( null !== $theme ) {
			$out['theme'] = $theme;
		}
		$color = self::sanitize_color( $value['primaryColor'] ?? null );
		if ( null !== $color ) {
			$out['primaryColor'] = $color;
		}
		if ( array_key_exists( 'radius', $value ) ) {
			$out['radius'] = min( absint( self::to_string( $value['radius'] ) ), self::MAX_RADIUS );
		}
		if ( array_key_exists( 'privacyUrl', $value ) ) {
			$out['privacyUrl'] = esc_url_raw( self::to_string( $value['privacyUrl'] ) );
		}
		return $out;
	}

	/**
	 * Per-purpose `enabled` (only meaningful for personalization) and copy overrides. Only
	 * keys in the fixed set survive; blank label/description is allowed (= gettext default).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function sanitize_purposes( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$out = array();
		foreach ( $value as $key => $purpose ) {
			if ( ! in_array( $key, self::PURPOSE_KEYS, true ) || ! is_array( $purpose ) ) {
				continue;
			}
			$entry = array();
			if ( array_key_exists( 'enabled', $purpose ) ) {
				$entry['enabled'] = (bool) $purpose['enabled'];
			}
			if ( array_key_exists( 'label', $purpose ) ) {
				$entry['label'] = sanitize_text_field( self::to_string( $purpose['label'] ) );
			}
			if ( array_key_exists( 'description', $purpose ) ) {
				$entry['description'] = sanitize_text_field( self::to_string( $purpose['description'] ) );
			}
			$out[ (string) $key ] = $entry;
		}
		return $out;
	}

	/**
	 * The ordered tag list: catalog instances + custom snippets. Each entry must carry a
	 * known catalog key (or `custom`) and a non-empty, deduped id (case preserved for ids
	 * like `GTM-XXX`).
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function sanitize_tags( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$out  = array();
		$seen = array();
		foreach ( $value as $tag ) {
			if ( ! is_array( $tag ) ) {
				continue;
			}
			$catalog = sanitize_text_field( self::to_string( $tag['catalog'] ?? '' ) );
			$id      = sanitize_text_field( self::to_string( $tag['id'] ?? '' ) );
			if ( '' === $id || '' === $catalog || ! array_key_exists( $catalog, self::CATALOG_FIELDS ) || isset( $seen[ $id ] ) ) {
				continue;
			}

			$fields = self::sanitize_fields( $catalog, $tag['fields'] ?? null );
			// A custom snippet with no non-empty script is empty — an untouched "add another"
			// row, the JS-free template seed, or one the Administrator cleared — so drop it.
			if ( 'custom' === $catalog && array() === ( $fields['fragments'] ?? array() ) ) {
				continue;
			}
			$seen[ $id ] = true;

			$entry = array(
				'id'       => $id,
				'catalog'  => $catalog,
				'enabled'  => array_key_exists( 'enabled', $tag ) ? (bool) $tag['enabled'] : true,
				'purposes' => self::sanitize_purpose_keys( $tag['purposes'] ?? null ),
				'fields'   => $fields,
			);
			if ( array_key_exists( 'label', $tag ) ) {
				$entry['label'] = sanitize_text_field( self::to_string( $tag['label'] ) );
			}
			$out[] = $entry;
		}
		return $out;
	}

	/**
	 * Tag purpose assignment intersected with the fixed purpose set.
	 *
	 * @return list<string>
	 */
	private static function sanitize_purpose_keys( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $key ) {
			$key = self::to_string( $key );
			if ( in_array( $key, self::PURPOSE_KEYS, true ) && ! in_array( $key, $out, true ) ) {
				$out[] = $key;
			}
		}
		return $out;
	}

	/**
	 * The stored field values for a tag, per the catalog entry's field schema. `custom` is
	 * special — its fields are a list of script fragments (see `sanitize_custom_fields`).
	 *
	 * @return array<string, mixed>
	 */
	private static function sanitize_fields( string $catalog, mixed $value ): array {
		if ( 'custom' === $catalog ) {
			return self::sanitize_custom_fields( $value );
		}

		$allowed = self::CATALOG_FIELDS[ $catalog ];
		if ( ! is_array( $value ) ) {
			return array();
		}

		$out = array();
		foreach ( $allowed as $field ) {
			if ( ! array_key_exists( $field, $value ) ) {
				continue;
			}
			$out[ $field ] = sanitize_text_field( self::to_string( $value[ $field ] ) );
		}
		return $out;
	}

	/**
	 * A custom snippet's fields: an ordered `fragments` list of `{ code, location }`. Each
	 * `code` is stored RAW (only `wp_unslash`, never escaped) — it is injected by JS, gated
	 * by admin `unfiltered_html` trust; `location` is allowlisted (defaulting to `head`).
	 * Empty-code fragments (untouched/template rows) are dropped and the list is re-indexed;
	 * no non-empty fragment yields an empty array (so the snippet itself is dropped upstream).
	 *
	 * @return array{fragments?: list<array{code: string, location: string}>}
	 */
	private static function sanitize_custom_fields( mixed $value ): array {
		if ( ! is_array( $value ) || ! is_array( $value['fragments'] ?? null ) ) {
			return array();
		}

		$fragments = array();
		foreach ( $value['fragments'] as $fragment ) {
			if ( ! is_array( $fragment ) ) {
				continue;
			}
			$code = (string) wp_unslash( self::to_string( $fragment['code'] ?? '' ) );
			if ( '' === $code ) {
				continue;
			}
			$fragments[] = array(
				'code'     => $code,
				'location' => self::in_list( self::to_string( $fragment['location'] ?? '' ), self::SNIPPET_LOCATIONS ) ?? 'head',
			);
		}

		return array() === $fragments ? array() : array( 'fragments' => $fragments );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function sanitize_geo( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		if ( array_key_exists( 'adaptive', $value ) ) {
			$out['adaptive'] = (bool) $value['adaptive'];
		}
		$policy = self::in_list( $value['globalPolicy'] ?? null, self::GLOBAL_POLICIES );
		if ( null !== $policy ) {
			$out['globalPolicy'] = $policy;
		}
		return $out;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function sanitize_proof( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		if ( array_key_exists( 'enabled', $value ) ) {
			$out['enabled'] = (bool) $value['enabled'];
		}
		return $out;
	}

	/**
	 * The value when it is a string in the allowlist, otherwise null (dropping it).
	 *
	 * @param list<string> $allowed
	 */
	private static function in_list( mixed $value, array $allowed ): ?string {
		return is_string( $value ) && in_array( $value, $allowed, true ) ? $value : null;
	}

	/** A valid hex color, or null when WordPress rejects it. */
	private static function sanitize_color( mixed $value ): ?string {
		if ( null === $value ) {
			return null;
		}
		$color = sanitize_hex_color( self::to_string( $value ) );
		return is_string( $color ) && '' !== $color ? $color : null;
	}

	/** Coerce a scalar option value to string (non-scalars become ''). */
	private static function to_string( mixed $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}
}

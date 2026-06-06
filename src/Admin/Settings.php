<?php
declare( strict_types = 1 );

namespace Consentful\Admin;

/**
 * The Site-owner layer (Layer 2) of the two-tier operator model: the constrained
 * `consentful_settings` option, overlaid on the integrator's BannerConfig and registered
 * Tags (Layer 1, the source of truth). Constructable from arrays so the sanitize/overlay
 * logic is pure and unit-testable; `from_wp()` reads the live option + locks.
 *
 * The Integrator declares which fields are locked via the `consentful_locked_settings`
 * filter; a locked field can never be saved (dropped by `sanitize`), is rendered
 * read-only, and is skipped by the overlay so Layer 1 always wins.
 *
 * Appearance (and tag visibility) is Site-owner editable; banner *copy* is not — copy comes
 * from the gettext defaults and is translated in the language files, never overridden here.
 */
final class Settings {

	/** @var list<string> The top-level lockable/overlayable field keys. */
	private const FIELDS = array( 'enabled', 'position', 'theme', 'primaryColor', 'radius', 'privacyUrl', 'tags' );

	/** @var list<string> Allowed banner positions. */
	private const POSITIONS = array( 'bar', 'corner', 'modal' );

	/** @var list<string> Allowed banner themes. */
	private const THEMES = array( 'light', 'dark', 'auto' );

	/** Max corner radius the Site owner may set (px). */
	private const MAX_RADIUS = 32;

	/**
	 * @param array<array-key, mixed> $stored The sanitized option (Layer 2).
	 * @param list<string>            $locked Locked top-level field keys (Layer 1 declares these).
	 */
	public function __construct(
		private readonly array $stored,
		private readonly array $locked,
	) {}

	/** Read the live option and locks from WordPress. */
	public static function from_wp(): self {
		$stored = get_option( CONSENTFUL_OPTION, array() );
		return new self( is_array( $stored ) ? $stored : array(), self::locked_fields() );
	}

	/**
	 * The Integrator-declared locked field keys (string keys only).
	 *
	 * @return list<string>
	 */
	public static function locked_fields(): array {
		$fields = apply_filters( 'consentful_locked_settings', array() );
		return is_array( $fields ) ? array_values( array_filter( $fields, 'is_string' ) ) : array();
	}

	public function is_locked( string $field ): bool {
		return in_array( $field, $this->locked, true );
	}

	/**
	 * The stored banner appearance values, EXCLUDING locked fields — ready for
	 * `BannerConfig::with_overrides`. (The overlay also honors locks, but excluding here
	 * keeps the override map free of values the Site owner can never have set.)
	 *
	 * @return array<string, mixed>
	 */
	public function banner_overrides(): array {
		$overrides = array();
		foreach ( array( 'enabled', 'position', 'theme', 'primaryColor', 'radius', 'privacyUrl' ) as $field ) {
			if ( array_key_exists( $field, $this->stored ) && ! $this->is_locked( $field ) ) {
				$overrides[ $field ] = $this->stored[ $field ];
			}
		}
		return $overrides;
	}

	/**
	 * Tag ids the Site owner disabled (the stored `tags` map keys whose value is false). A
	 * locked `tags` field yields no hidden ids — Layer 1 wins.
	 *
	 * @return list<string>
	 */
	public function hidden_tag_ids(): array {
		if ( $this->is_locked( 'tags' ) || ! isset( $this->stored['tags'] ) || ! is_array( $this->stored['tags'] ) ) {
			return array();
		}

		$hidden = array();
		foreach ( $this->stored['tags'] as $id => $enabled ) {
			if ( false === $enabled ) {
				$hidden[] = (string) $id;
			}
		}
		return $hidden;
	}

	/** The raw stored value for a field, or null when unset. */
	public function stored( string $field ): mixed {
		return $this->stored[ $field ] ?? null;
	}

	/**
	 * The PURE heart: allowlist + coerce the raw POSTed option, DROPPING unknown keys AND
	 * every locked field (a Site owner can never save a locked field). The
	 * `register_setting` sanitize callback delegates here.
	 *
	 * @param array<array-key, mixed> $raw    The raw submitted option.
	 * @param list<string>            $locked Locked top-level field keys.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $raw, array $locked ): array {
		$out = array();

		foreach ( self::FIELDS as $field ) {
			if ( ! array_key_exists( $field, $raw ) || in_array( $field, $locked, true ) ) {
				continue;
			}

			$value = self::sanitize_field( $field, $raw[ $field ] );
			if ( null !== $value ) {
				$out[ $field ] = $value;
			}
		}

		return $out;
	}

	/** Sanitize one known field; null means "drop it". */
	private static function sanitize_field( string $field, mixed $value ): mixed {
		return match ( $field ) {
			'enabled'      => (bool) $value,
			'position'     => self::in_list( $value, self::POSITIONS ),
			'theme'        => self::in_list( $value, self::THEMES ),
			'primaryColor' => self::sanitize_color( $value ),
			'radius'       => min( absint( self::to_string( $value ) ), self::MAX_RADIUS ),
			'privacyUrl'   => self::sanitize_url( $value ),
			'tags'         => self::sanitize_tags( $value ),
			default        => null,
		};
	}

	/**
	 * The value when it is in the allowlist, otherwise null (dropping it).
	 *
	 * @param list<string> $allowed
	 */
	private static function in_list( mixed $value, array $allowed ): ?string {
		return is_string( $value ) && in_array( $value, $allowed, true ) ? $value : null;
	}

	/** A valid hex color, or null when WordPress rejects it. */
	private static function sanitize_color( mixed $value ): ?string {
		$color = sanitize_hex_color( self::to_string( $value ) );
		return is_string( $color ) && '' !== $color ? $color : null;
	}

	/** A non-empty escaped URL, or null when empty. */
	private static function sanitize_url( mixed $value ): ?string {
		$url = esc_url_raw( self::to_string( $value ) );
		return '' !== $url ? $url : null;
	}

	/** Defensively coerce a scalar option value to string (non-scalars become ''). */
	private static function to_string( mixed $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * The Tag toggle map: `id => bool`. The id is preserved (trimmed / tag-stripped via
	 * sanitize_text_field, NOT sanitize_key) so it still matches the registered Tag id
	 * downstream — Tag ids may carry uppercase (e.g. `GTM-XXX`), which sanitize_key would
	 * lowercase, silently breaking the toggle. A non-matching key is harmless: the Gate only
	 * hides ids that match a toggleable Tag.
	 *
	 * @return array<string, bool>
	 */
	private static function sanitize_tags( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$out = array();
		foreach ( $value as $id => $enabled ) {
			$key = sanitize_text_field( self::to_string( $id ) );
			if ( '' !== $key ) {
				$out[ $key ] = (bool) $enabled;
			}
		}
		return $out;
	}
}

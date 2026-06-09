<?php
declare( strict_types = 1 );

namespace Consentful\Frontend;

/**
 * The resolved consent-banner presentation block. A pure value object: copy &
 * appearance only, no consent logic. ClientConfig serializes it verbatim into the
 * camelCase `banner` key the JS banner coercer consumes; the same value feeds the
 * cache-safe, identical-for-every-visitor head output.
 *
 * `defaults()` is the only place gettext is called — copy comes from code defaults;
 * the Administrator tunes appearance and purpose copy from the admin UI, applied here
 * by the hydrator via `with_overrides()` / `with_purpose_overrides()`.
 */
final class BannerConfig {

	/** @var list<string> Allowed banner positions; anything else falls back to the base. */
	private const POSITIONS = array( 'bar', 'corner', 'modal' );

	/** @var list<string> Allowed banner themes; anything else falls back to the base. */
	private const THEMES = array( 'light', 'dark', 'auto' );

	/**
	 * @param array<string, string>                        $copy     UI copy keyed by control (camelCase keys per §2).
	 * @param array<string, array{label: string, description: string}> $purposes Presentation copy keyed by Purpose key.
	 */
	public function __construct(
		public readonly bool $enabled,
		public readonly string $position,
		public readonly string $theme,
		public readonly string $primary_color,
		public readonly int $radius,
		public readonly int $version,
		public readonly string $privacy_url,
		public readonly array $copy,
		public readonly array $purposes,
	) {}

	/**
	 * @return array<string, mixed> The frozen §2 banner shape (camelCase keys).
	 */
	public function to_array(): array {
		return array(
			'enabled'      => $this->enabled,
			'position'     => $this->position,
			'theme'        => $this->theme,
			'primaryColor' => $this->primary_color,
			'radius'       => $this->radius,
			'version'      => $this->version,
			'privacyUrl'   => $this->privacy_url,
			'copy'         => $this->copy,
			'purposes'     => $this->purposes,
		);
	}

	/**
	 * Return a NEW BannerConfig with the Administrator's appearance overrides layered over
	 * this base. A field is overlaid only when it is present in `$overrides`. Values may
	 * arrive as strings (from the option), so each is coerced defensively; invalid
	 * `position`/`theme` fall back to the base. `copy` is not editable here — it is
	 * gettext-translated, never overridden. Pure: no WordPress calls.
	 *
	 * @param array<string, mixed> $overrides The Administrator's stored banner values.
	 */
	public function with_overrides( array $overrides ): self {
		$has = static fn ( string $field ): bool => array_key_exists( $field, $overrides );

		return new self(
			$has( 'enabled' ) ? (bool) $overrides['enabled'] : $this->enabled,
			$has( 'position' ) ? self::in_list( self::to_string( $overrides['position'] ), self::POSITIONS, $this->position ) : $this->position,
			$has( 'theme' ) ? self::in_list( self::to_string( $overrides['theme'] ), self::THEMES, $this->theme ) : $this->theme,
			$has( 'primaryColor' ) ? self::to_string( $overrides['primaryColor'] ) : $this->primary_color,
			$has( 'radius' ) ? self::to_int( $overrides['radius'] ) : $this->radius,
			$this->version,
			$has( 'privacyUrl' ) ? self::to_string( $overrides['privacyUrl'] ) : $this->privacy_url,
			$this->copy,
			$this->purposes,
		);
	}

	/**
	 * Resolve an empty privacy URL to `$fallback` (e.g. the site's configured WordPress
	 * privacy page) so the banner can always link to a privacy policy. An explicit URL —
	 * the integrator's base or the Site owner's override — is left untouched, and an empty
	 * fallback is a no-op. Pure: the caller supplies the resolved fallback (no WordPress
	 * call here), keeping this value object framework-free.
	 */
	public function with_privacy_fallback( string $fallback ): self {
		if ( '' !== $this->privacy_url || '' === $fallback ) {
			return $this;
		}

		return new self(
			$this->enabled,
			$this->position,
			$this->theme,
			$this->primary_color,
			$this->radius,
			$this->version,
			$fallback,
			$this->copy,
			$this->purposes,
		);
	}

	/**
	 * Return a NEW BannerConfig with the Administrator's per-Purpose copy overrides layered
	 * over this base. A purpose's `label`/`description` is overridden only when the override
	 * is a non-blank string — a blank or absent value keeps the gettext default. Pure: no
	 * WordPress calls. Overrides for purposes not in the base map are ignored.
	 *
	 * @param array<string, array<string, mixed>> $overrides Purpose key → { label?, description? }.
	 */
	public function with_purpose_overrides( array $overrides ): self {
		$purposes = $this->purposes;
		foreach ( $purposes as $key => $copy ) {
			$over = $overrides[ $key ] ?? array();
			foreach ( array( 'label', 'description' ) as $field ) {
				$value = self::to_string( $over[ $field ] ?? '' );
				if ( '' !== $value ) {
					$copy[ $field ] = $value;
				}
			}
			$purposes[ $key ] = $copy;
		}

		return new self(
			$this->enabled,
			$this->position,
			$this->theme,
			$this->primary_color,
			$this->radius,
			$this->version,
			$this->privacy_url,
			$this->copy,
			$purposes,
		);
	}

	/**
	 * Return `$value` when it is in the allowlist, otherwise the `$fallback`.
	 *
	 * @param list<string> $allowed
	 */
	private static function in_list( string $value, array $allowed, string $fallback ): string {
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/** Defensively coerce a scalar override value to string (non-scalars become ''). */
	private static function to_string( mixed $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	/** Defensively coerce a scalar override value to int (non-numerics become 0). */
	private static function to_int( mixed $value ): int {
		return is_numeric( $value ) ? (int) $value : 0;
	}

	/**
	 * Sensible code defaults: opt-in banner shown, auto theme, blue primary. Copy is the
	 * English source for gettext; French ships via the bundled `.mo` files.
	 */
	public static function defaults(): self {
		return new self(
			true,
			'bar',
			'auto',
			'#2563eb',
			8,
			1,
			'',
			array(
				'title'             => __( 'Your privacy', 'consentful' ),
				'description'       => __( 'We use cookies and similar technologies to run this site. With your consent, we also use them for analytics, advertising and personalization.', 'consentful' ),
				'privacyLabel'      => __( 'Privacy policy', 'consentful' ),
				'prefsTitle'        => __( 'Manage preferences', 'consentful' ),
				'acceptAll'         => __( 'Accept all', 'consentful' ),
				'rejectAll'         => __( 'Reject all', 'consentful' ),
				'customize'         => __( 'Manage preferences', 'consentful' ),
				'save'              => __( 'Save preferences', 'consentful' ),
				'reopen'            => __( 'Privacy settings', 'consentful' ),
				'saved'             => __( 'Your privacy choices were saved.', 'consentful' ),
				'noticeTitle'       => __( 'Your privacy choices', 'consentful' ),
				'noticeDescription' => __( 'We use cookies and similar technologies to run this site, and for analytics, advertising and personalization. You can opt out at any time.', 'consentful' ),
				'doNotSell'         => __( 'Do Not Sell or Share My Personal Information', 'consentful' ),
				'close'             => __( 'Close', 'consentful' ),
			),
			array(
				'necessary'       => array(
					'label'       => __( 'Strictly necessary', 'consentful' ),
					'description' => __( 'Required for the site to function; always on and cannot be turned off.', 'consentful' ),
				),
				'functional'      => array(
					'label'       => __( 'Functional', 'consentful' ),
					'description' => __( 'Remembers your choices and preferences to improve your experience.', 'consentful' ),
				),
				'analytics'       => array(
					'label'       => __( 'Analytics', 'consentful' ),
					'description' => __( 'Measures how the site is used so we can improve it.', 'consentful' ),
				),
				'marketing'       => array(
					'label'       => __( 'Marketing', 'consentful' ),
					'description' => __( 'Used to deliver and measure advertising relevant to you.', 'consentful' ),
				),
				'personalization' => array(
					'label'       => __( 'Personalization', 'consentful' ),
					'description' => __( 'Tailors content and recommendations to your interests.', 'consentful' ),
				),
			),
		);
	}
}

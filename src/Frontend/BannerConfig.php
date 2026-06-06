<?php
declare( strict_types = 1 );

namespace Consentful\Frontend;

/**
 * The resolved consent-banner presentation block. A pure value object: copy &
 * appearance only, no consent logic. ClientConfig serializes it verbatim into the
 * camelCase `banner` key the JS banner coercer consumes; the same value feeds the
 * cache-safe, identical-for-every-visitor head output.
 *
 * `defaults()` is the only place gettext is called — copy comes from code defaults
 * until the site-owner admin UI lands. An Integrator overrides the binding in
 * `consentful_register` to supply their own instance.
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
	 * Return a NEW BannerConfig with the Site owner's unlocked overrides layered over this
	 * base (the integrator's Layer 1). A field is overlaid only when it is present in
	 * `$overrides` AND not in `$locked` — locked fields keep the base value (Layer 1 wins).
	 * Values may arrive as strings (from the option), so each is coerced defensively;
	 * invalid `position`/`theme` fall back to the base. `copy` and `purposes` are not
	 * Site-owner editable — copy is gettext-translated, never overridden. Pure: no WordPress
	 * calls.
	 *
	 * @param array<string, mixed> $overrides The Site owner's stored banner values.
	 * @param list<string>         $locked    Locked top-level field keys.
	 */
	public function with_overrides( array $overrides, array $locked ): self {
		$has = static fn ( string $field ): bool => array_key_exists( $field, $overrides ) && ! in_array( $field, $locked, true );

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
				'description'       => __( 'We use cookies and similar technologies to run this site and, with your consent, to measure traffic and personalize content.', 'consentful' ),
				'privacyLabel'      => __( 'Privacy policy', 'consentful' ),
				'prefsTitle'        => __( 'Manage preferences', 'consentful' ),
				'acceptAll'         => __( 'Accept all', 'consentful' ),
				'rejectAll'         => __( 'Reject all', 'consentful' ),
				'customize'         => __( 'Customize', 'consentful' ),
				'save'              => __( 'Save preferences', 'consentful' ),
				'reopen'            => __( 'Privacy settings', 'consentful' ),
				'noticeTitle'       => __( 'Your privacy choices', 'consentful' ),
				'noticeDescription' => __( 'We and our partners process personal data for advertising, analytics and personalization. You can opt out at any time.', 'consentful' ),
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

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
				'title'        => __( 'Your privacy', 'consentful' ),
				'description'  => __( 'We use cookies and similar technologies to run this site and, with your consent, to measure traffic and personalize content.', 'consentful' ),
				'privacyLabel' => __( 'Privacy policy', 'consentful' ),
				'prefsTitle'   => __( 'Manage preferences', 'consentful' ),
				'acceptAll'    => __( 'Accept all', 'consentful' ),
				'rejectAll'    => __( 'Reject all', 'consentful' ),
				'customize'    => __( 'Customize', 'consentful' ),
				'save'         => __( 'Save preferences', 'consentful' ),
				'reopen'       => __( 'Privacy settings', 'consentful' ),
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

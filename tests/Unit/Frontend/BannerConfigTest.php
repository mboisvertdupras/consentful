<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Frontend;

use Consentful\Frontend\BannerConfig;
use PHPUnit\Framework\TestCase;

/**
 * BannerConfig is the pure presentation block ClientConfig serializes into the frozen
 * §2 `banner` shape: camelCase keys, real copy from gettext defaults, per-Purpose
 * label/description copy keyed by Purpose key.
 */
final class BannerConfigTest extends TestCase {

	public function test_defaults_emit_the_frozen_appearance_scalars(): void {
		$out = BannerConfig::defaults()->to_array();

		$this->assertTrue( $out['enabled'] );
		$this->assertSame( 'bar', $out['position'] );
		$this->assertSame( 'auto', $out['theme'] );
		$this->assertSame( '#2563eb', $out['primaryColor'] );
		$this->assertSame( 8, $out['radius'] );
		$this->assertSame( 1, $out['version'] );
		$this->assertSame( '', $out['privacyUrl'] );
	}

	public function test_defaults_copy_block_has_every_camel_case_key(): void {
		$copy = BannerConfig::defaults()->to_array()['copy'];

		$this->assertIsArray( $copy );
		$keys = array( 'title', 'description', 'privacyLabel', 'prefsTitle', 'acceptAll', 'rejectAll', 'customize', 'save', 'reopen', 'saved', 'noticeTitle', 'noticeDescription', 'doNotSell', 'close' );
		foreach ( $keys as $key ) {
			$this->assertArrayHasKey( $key, $copy );
			$this->assertNotSame( '', $copy[ $key ] );
		}
	}

	public function test_defaults_carry_the_opt_out_notice_copy_with_english_source(): void {
		$copy = BannerConfig::defaults()->copy;

		$this->assertSame( 'Your privacy choices', $copy['noticeTitle'] );
		$this->assertSame(
			'We use cookies and similar technologies to run this site, and for analytics, advertising and personalization. You can opt out at any time.',
			$copy['noticeDescription']
		);
		$this->assertSame( 'Do Not Sell or Share My Personal Information', $copy['doNotSell'] );
		$this->assertSame( 'Close', $copy['close'] );
	}

	public function test_defaults_carry_copy_for_all_five_default_purposes(): void {
		$purposes = BannerConfig::defaults()->purposes;

		$this->assertSame(
			array( 'necessary', 'functional', 'analytics', 'marketing', 'personalization' ),
			array_keys( $purposes )
		);
		foreach ( $purposes as $copy ) {
			$this->assertNotSame( '', $copy['label'] );
			$this->assertNotSame( '', $copy['description'] );
		}
	}

	public function test_to_array_round_trips_a_custom_instance(): void {
		$copy     = array(
			'title'        => 'T',
			'description'  => 'D',
			'privacyLabel' => 'P',
			'prefsTitle'   => 'PT',
			'acceptAll'    => 'AA',
			'rejectAll'    => 'RA',
			'customize'    => 'C',
			'save'         => 'S',
			'reopen'       => 'R',
		);
		$purposes = array(
			'analytics' => array(
				'label'       => 'Analytics',
				'description' => 'Measures use.',
			),
		);

		$banner = new BannerConfig( false, 'modal', 'dark', '#ff0000', 12, 4, 'https://example.test/privacy', $copy, $purposes );

		$this->assertSame(
			array(
				'enabled'      => false,
				'position'     => 'modal',
				'theme'        => 'dark',
				'primaryColor' => '#ff0000',
				'radius'       => 12,
				'version'      => 4,
				'privacyUrl'   => 'https://example.test/privacy',
				'copy'         => $copy,
				'purposes'     => $purposes,
			),
			$banner->to_array()
		);
	}

	public function test_with_overrides_applies_the_appearance_fields(): void {
		$out = BannerConfig::defaults()->with_overrides(
			array(
				'position'     => 'corner',
				'theme'        => 'dark',
				'primaryColor' => '#ff0000',
				'privacyUrl'   => 'https://example.test/p',
			)
		);

		$this->assertSame( 'corner', $out->position );
		$this->assertSame( 'dark', $out->theme );
		$this->assertSame( '#ff0000', $out->primary_color );
		$this->assertSame( 'https://example.test/p', $out->privacy_url );
	}

	public function test_with_overrides_falls_back_to_base_on_invalid_position_or_theme(): void {
		$out = BannerConfig::defaults()->with_overrides(
			array(
				'position' => 'floating',
				'theme'    => 'neon',
			)
		);

		$this->assertSame( 'bar', $out->position );
		$this->assertSame( 'auto', $out->theme );
	}

	public function test_with_overrides_never_overrides_copy(): void {
		// Copy is gettext-translated, not Administrator editable: a posted `copy` map is
		// ignored and the base (gettext) copy is preserved verbatim.
		$out = BannerConfig::defaults()->with_overrides(
			array(
				'position' => 'corner',
				'copy'     => array( 'title' => 'Hacked' ),
			)
		);

		$this->assertSame( 'corner', $out->position );
		$this->assertSame( BannerConfig::defaults()->copy, $out->copy );
	}

	public function test_with_overrides_coerces_string_radius_and_enabled(): void {
		$out = BannerConfig::defaults()->with_overrides(
			array(
				'radius'  => '12',
				'enabled' => '',
			)
		);

		$this->assertSame( 12, $out->radius );
		$this->assertFalse( $out->enabled );
	}

	public function test_with_overrides_keeps_version_and_purposes(): void {
		$base = BannerConfig::defaults();
		$out  = $base->with_overrides( array( 'position' => 'modal' ) );

		$this->assertSame( $base->version, $out->version );
		$this->assertSame( $base->purposes, $out->purposes );
	}

	public function test_empty_overrides_yield_an_identical_config(): void {
		$base = BannerConfig::defaults();
		$out  = $base->with_overrides( array() );

		$this->assertSame( $base->to_array(), $out->to_array() );
	}

	public function test_with_purpose_overrides_replaces_label_and_description(): void {
		$out = BannerConfig::defaults()->with_purpose_overrides(
			array(
				'analytics' => array(
					'label'       => 'Site stats',
					'description' => 'We measure usage.',
				),
			)
		);

		$this->assertSame( 'Site stats', $out->purposes['analytics']['label'] );
		$this->assertSame( 'We measure usage.', $out->purposes['analytics']['description'] );
	}

	public function test_with_purpose_overrides_keeps_base_for_blank_override(): void {
		$base = BannerConfig::defaults();
		$out  = $base->with_purpose_overrides(
			array(
				'marketing' => array(
					'label'       => '',
					'description' => '',
				),
			)
		);

		$this->assertSame( $base->purposes['marketing'], $out->purposes['marketing'] );
	}

	public function test_with_purpose_overrides_ignores_unknown_keys(): void {
		$base = BannerConfig::defaults();
		$out  = $base->with_purpose_overrides(
			array( 'bogus' => array( 'label' => 'Nope' ) )
		);

		$this->assertSame( $base->purposes, $out->purposes );
		$this->assertArrayNotHasKey( 'bogus', $out->purposes );
	}

	public function test_with_privacy_fallback_fills_an_empty_url(): void {
		$base = BannerConfig::defaults(); // privacyUrl defaults to ''.
		$out  = $base->with_privacy_fallback( 'https://example.test/privacy' );

		$this->assertSame( 'https://example.test/privacy', $out->privacy_url );
		// Everything else is preserved.
		$this->assertSame( $base->position, $out->position );
		$this->assertSame( $base->copy, $out->copy );
	}

	public function test_with_privacy_fallback_keeps_an_explicit_url(): void {
		$base = new BannerConfig( true, 'bar', 'auto', '#2563eb', 8, 1, 'https://example.test/custom', array(), array() );
		$out  = $base->with_privacy_fallback( 'https://example.test/wp-privacy' );

		// An explicit URL wins; the no-op path returns the same instance.
		$this->assertSame( 'https://example.test/custom', $out->privacy_url );
		$this->assertSame( $base, $out );
	}

	public function test_with_privacy_fallback_is_a_noop_for_an_empty_fallback(): void {
		$base = BannerConfig::defaults();
		$out  = $base->with_privacy_fallback( '' );

		$this->assertSame( '', $out->privacy_url );
		$this->assertSame( $base, $out );
	}
}

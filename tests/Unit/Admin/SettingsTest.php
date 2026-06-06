<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Admin;

use Consentful\Admin\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Settings is the pure Site-owner layer (Layer 2). `sanitize` is the heart: allowlist +
 * coerce, dropping unknown keys AND every locked field. `banner_overrides` /
 * `hidden_tag_ids` / `is_locked` / `locked_fields` feed the Gate overlay. Locked fields
 * always defer to the integrator's Layer 1.
 */
final class SettingsTest extends TestCase {

	protected function tearDown(): void {
		remove_all_filters( 'consentful_locked_settings' );
		parent::tearDown();
	}

	public function test_sanitize_coerces_and_allowlists_each_field(): void {
		$out = Settings::sanitize(
			array(
				'enabled'      => '1',
				'position'     => 'corner',
				'theme'        => 'dark',
				'primaryColor' => '#abcdef',
				'radius'       => '12',
				'privacyUrl'   => 'https://example.test/privacy',
			),
			array()
		);

		$this->assertTrue( $out['enabled'] );
		$this->assertSame( 'corner', $out['position'] );
		$this->assertSame( 'dark', $out['theme'] );
		$this->assertSame( '#abcdef', $out['primaryColor'] );
		$this->assertSame( 12, $out['radius'] );
		$this->assertSame( 'https://example.test/privacy', $out['privacyUrl'] );
	}

	public function test_sanitize_drops_unknown_keys(): void {
		$out = Settings::sanitize(
			array(
				'enabled' => true,
				'evil'    => 'x',
				'purposes' => array( 'analytics' => 'hacked' ),
			),
			array()
		);

		$this->assertSame( array( 'enabled' ), array_keys( $out ) );
	}

	public function test_sanitize_drops_every_locked_field_even_when_present(): void {
		$raw = array(
			'enabled'      => true,
			'position'     => 'modal',
			'theme'        => 'light',
			'primaryColor' => '#123456',
			'radius'       => 4,
			'privacyUrl'   => 'https://example.test',
			'tags'         => array( 'ga4' => false ),
		);

		$out = Settings::sanitize( $raw, array( 'enabled', 'position', 'theme', 'primaryColor', 'radius', 'privacyUrl', 'tags' ) );

		$this->assertSame( array(), $out );
	}

	public function test_sanitize_drops_invalid_position_and_theme(): void {
		$out = Settings::sanitize(
			array(
				'position' => 'floating',
				'theme'    => 'neon',
			),
			array()
		);

		$this->assertArrayNotHasKey( 'position', $out );
		$this->assertArrayNotHasKey( 'theme', $out );
	}

	public function test_sanitize_drops_an_invalid_color_and_empty_url(): void {
		$out = Settings::sanitize(
			array(
				'primaryColor' => 'not-a-color',
				'privacyUrl'   => '',
			),
			array()
		);

		$this->assertArrayNotHasKey( 'primaryColor', $out );
		$this->assertArrayNotHasKey( 'privacyUrl', $out );
	}

	public function test_sanitize_clamps_radius_to_zero_through_thirty_two(): void {
		$high = Settings::sanitize( array( 'radius' => 999 ), array() );
		$neg  = Settings::sanitize( array( 'radius' => -5 ), array() );

		$this->assertSame( 32, $high['radius'] );
		$this->assertSame( 5, $neg['radius'] );
	}

	public function test_sanitize_drops_copy_entirely(): void {
		// Banner copy is not Site-owner editable: it comes from the gettext defaults and is
		// translated in the language files, so a posted `copy` map is dropped like any unknown key.
		$out = Settings::sanitize(
			array(
				'enabled' => true,
				'copy'    => array( 'title' => 'Hello' ),
			),
			array()
		);

		$this->assertSame( array( 'enabled' ), array_keys( $out ) );
		$this->assertArrayNotHasKey( 'copy', $out );
	}

	public function test_sanitize_coerces_tags_to_bool(): void {
		$out = Settings::sanitize(
			array(
				'tags' => array(
					'ga4'  => '1',
					'meta' => '0',
					'ads'  => false,
					'gtm'  => true,
				),
			),
			array()
		);

		// PHP's bool coercion: '1'/true → true, '0'/false → false.
		$this->assertSame(
			array(
				'ga4'  => true,
				'meta' => false,
				'ads'  => false,
				'gtm'  => true,
			),
			$out['tags']
		);
	}

	public function test_sanitize_preserves_tag_id_case(): void {
		// Tag ids may carry uppercase (e.g. GTM-ABC); sanitize_key would lowercase them and
		// silently break the toggle, so the id must round-trip verbatim.
		$out = Settings::sanitize(
			array(
				'tags' => array( 'GTM-ABC' => false ),
			),
			array()
		);

		$this->assertSame( array( 'GTM-ABC' => false ), $out['tags'] );
	}

	public function test_banner_overrides_excludes_locked_fields(): void {
		$settings = new Settings(
			array(
				'position' => 'modal',
				'theme'    => 'dark',
			),
			array( 'theme' )
		);

		$this->assertSame( array( 'position' => 'modal' ), $settings->banner_overrides() );
	}

	public function test_hidden_tag_ids_lists_only_disabled_tags(): void {
		$settings = new Settings(
			array(
				'tags' => array(
					'ga4'  => true,
					'meta' => false,
					'ads'  => false,
				),
			),
			array()
		);

		$this->assertSame( array( 'meta', 'ads' ), $settings->hidden_tag_ids() );
	}

	public function test_hidden_tag_ids_is_empty_when_tags_locked(): void {
		$settings = new Settings(
			array( 'tags' => array( 'meta' => false ) ),
			array( 'tags' )
		);

		$this->assertSame( array(), $settings->hidden_tag_ids() );
	}

	public function test_is_locked_reflects_the_locked_list(): void {
		$settings = new Settings( array(), array( 'primaryColor' ) );

		$this->assertTrue( $settings->is_locked( 'primaryColor' ) );
		$this->assertFalse( $settings->is_locked( 'theme' ) );
	}

	public function test_locked_fields_reads_the_filter_and_drops_non_strings(): void {
		add_filter(
			'consentful_locked_settings',
			static fn (): array => array( 'theme', 42, 'primaryColor' )
		);

		$this->assertSame( array( 'theme', 'primaryColor' ), Settings::locked_fields() );
	}

	public function test_locked_fields_is_empty_without_a_filter(): void {
		$this->assertSame( array(), Settings::locked_fields() );
	}
}

<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Admin;

use Consentful\Admin\Settings;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase {

	public static function setUpBeforeClass(): void {
		if ( ! defined( 'CONSENTFUL_OPTION' ) ) {
			define( 'CONSENTFUL_OPTION', 'consentful_settings' );
		}
	}

	protected function tearDown(): void {
		unset(
			$GLOBALS['consentful_test_can'],
			$GLOBALS['consentful_test_options'],
			$GLOBALS['consentful_test_settings_errors']
		);
		parent::tearDown();
	}

	/**
	 * @param array<array-key, mixed> $source
	 * @return array<array-key, mixed>
	 */
	private function sub( array $source, string|int $key ): array {
		$value = $source[ $key ] ?? null;
		$this->assertIsArray( $value );
		return $value;
	}

	/** @return list<array<array-key, mixed>> */
	private function recorded_settings_errors(): array {
		$entries = $GLOBALS['consentful_test_settings_errors'] ?? array();
		$this->assertIsArray( $entries );

		$out = array();
		foreach ( $entries as $entry ) {
			$this->assertIsArray( $entry );
			$out[] = $entry;
		}
		return $out;
	}

	public function test_empty_option_yields_full_defaults(): void {
		$settings = new Settings( array() );

		$this->assertSame( $this->sub( Settings::defaults(), 'banner' ), $settings->banner() );
		$this->assertSame( $this->sub( Settings::defaults(), 'geo' ), $settings->geo() );
		$this->assertSame( $this->sub( Settings::defaults(), 'proof' ), $settings->proof() );
		$this->assertSame( array(), $settings->tags() );
		$this->assertFalse( $this->sub( $settings->purposes(), 'personalization' )['enabled'] );
	}

	public function test_defaults_describe_the_compliant_baseline(): void {
		$defaults = Settings::defaults();

		$this->assertSame( 1, $defaults['version'] );
		$this->assertTrue( $this->sub( $defaults, 'banner' )['enabled'] );
		$this->assertSame( 'bar', $this->sub( $defaults, 'banner' )['position'] );
		$this->assertTrue( $this->sub( $defaults, 'geo' )['adaptive'] );
		$this->assertSame( 'opt_in', $this->sub( $defaults, 'geo' )['globalPolicy'] );
		$this->assertTrue( $this->sub( $defaults, 'proof' )['enabled'] );
	}

	public function test_accessors_merge_stored_over_defaults(): void {
		$settings = new Settings(
			array(
				'banner' => array( 'position' => 'modal' ),
				'geo'    => array( 'adaptive' => false ),
			)
		);

		$this->assertSame( 'modal', $settings->banner()['position'] );
		$this->assertSame( 'auto', $settings->banner()['theme'] );
		$this->assertFalse( $settings->geo()['adaptive'] );
		$this->assertSame( 'opt_in', $settings->geo()['globalPolicy'] );
	}

	public function test_all_returns_the_raw_stored_array(): void {
		$stored   = array( 'banner' => array( 'position' => 'corner' ) );
		$settings = new Settings( $stored );

		$this->assertSame( $stored, $settings->all() );
	}

	public function test_sanitize_always_stamps_the_version(): void {
		$this->assertSame( 1, Settings::sanitize( array() )['version'] );
	}

	public function test_sanitize_drops_unknown_top_level_keys(): void {
		$out = Settings::sanitize(
			array(
				'banner' => array( 'enabled' => true ),
				'evil'   => 'x',
				'locked' => array( 'theme' ),
			)
		);

		$this->assertSame( array( 'version', 'banner' ), array_keys( $out ) );
	}

	public function test_sanitize_banner_coerces_and_allowlists(): void {
		$out = Settings::sanitize(
			array(
				'banner' => array(
					'enabled'      => '1',
					'position'     => 'corner',
					'theme'        => 'dark',
					'primaryColor' => '#abcdef',
					'radius'       => '12',
					'privacyUrl'   => 'https://example.test/privacy',
					'evil'         => 'x',
				),
			)
		);

		$this->assertSame(
			array(
				'enabled'      => true,
				'position'     => 'corner',
				'theme'        => 'dark',
				'primaryColor' => '#abcdef',
				'radius'       => 12,
				'privacyUrl'   => 'https://example.test/privacy',
			),
			$this->sub( $out, 'banner' )
		);
	}

	public function test_sanitize_banner_drops_invalid_position_theme_and_color(): void {
		$out = Settings::sanitize(
			array(
				'banner' => array(
					'position'     => 'floating',
					'theme'        => 'neon',
					'primaryColor' => 'not-a-color',
				),
			)
		);

		$this->assertSame( array(), $this->sub( $out, 'banner' ) );
	}

	public function test_sanitize_banner_clamps_radius(): void {
		$high = Settings::sanitize( array( 'banner' => array( 'radius' => 999 ) ) );
		$neg  = Settings::sanitize( array( 'banner' => array( 'radius' => -5 ) ) );

		$this->assertSame( 32, $this->sub( $high, 'banner' )['radius'] );
		$this->assertSame( 5, $this->sub( $neg, 'banner' )['radius'] );
	}

	public function test_sanitize_purposes_keeps_only_fixed_keys_and_copy(): void {
		$out      = Settings::sanitize(
			array(
				'purposes' => array(
					'personalization' => array( 'enabled' => '1' ),
					'analytics'       => array(
						'label'       => 'Stats',
						'description' => 'How we measure',
					),
					'bogus'           => array( 'enabled' => true ),
				),
			)
		);
		$purposes = $this->sub( $out, 'purposes' );

		$this->assertTrue( $this->sub( $purposes, 'personalization' )['enabled'] );
		$this->assertSame( 'Stats', $this->sub( $purposes, 'analytics' )['label'] );
		$this->assertSame( 'How we measure', $this->sub( $purposes, 'analytics' )['description'] );
		$this->assertArrayNotHasKey( 'bogus', $purposes );
	}

	public function test_sanitize_purposes_allows_blank_copy(): void {
		$out = Settings::sanitize(
			array( 'purposes' => array( 'marketing' => array( 'label' => '' ) ) )
		);

		$this->assertSame( '', $this->sub( $this->sub( $out, 'purposes' ), 'marketing' )['label'] );
	}

	public function test_sanitize_tags_catalog_instance(): void {
		$out = Settings::sanitize(
			array(
				'tags' => array(
					array(
						'id'       => 'ga4',
						'catalog'  => 'ga4',
						'enabled'  => '1',
						'purposes' => array( 'analytics', 'bogus' ),
						'fields'   => array( 'measurementId' => 'G-XXXX' ),
					),
				),
			)
		);

		$this->assertSame(
			array(
				array(
					'id'       => 'ga4',
					'catalog'  => 'ga4',
					'enabled'  => true,
					'purposes' => array( 'analytics' ),
					'fields'   => array( 'measurementId' => 'G-XXXX' ),
				),
			),
			$this->sub( $out, 'tags' )
		);
	}

	public function test_sanitize_tags_drops_unknown_catalog(): void {
		$out = Settings::sanitize(
			array(
				'tags' => array(
					array(
						'id'      => 'mystery',
						'catalog' => 'not-a-catalog',
					),
					array(
						'id'      => 'gtm',
						'catalog' => 'gtm',
					),
				),
			)
		);

		$this->assertSame( array( 'gtm' ), array_column( $this->sub( $out, 'tags' ), 'id' ) );
	}

	public function test_sanitize_tags_dedupes_by_id_and_drops_empty_id(): void {
		$out  = Settings::sanitize(
			array(
				'tags' => array(
					array(
						'id'      => 'ga4',
						'catalog' => 'ga4',
					),
					array(
						'id'      => 'ga4',
						'catalog' => 'ga4',
					),
					array(
						'id'      => '',
						'catalog' => 'ga4',
					),
				),
			)
		);
		$tags = $this->sub( $out, 'tags' );

		$this->assertCount( 1, $tags );
		$this->assertSame( 'ga4', $this->sub( $tags, 0 )['id'] );
	}

	public function test_sanitize_tags_preserves_id_case(): void {
		$out = Settings::sanitize(
			array(
				'tags' => array(
					array(
						'id'      => 'GTM-ABC',
						'catalog' => 'gtm',
					),
				),
			)
		);

		$this->assertSame( 'GTM-ABC', $this->sub( $this->sub( $out, 'tags' ), 0 )['id'] );
	}

	public function test_sanitize_tags_keeps_custom_fragments_raw_and_allowlists_location(): void {
		$head = '<script>document.title = "x";</script>';
		$body = '<noscript><img src="https://example.test/p.gif"></noscript>';
		$out  = Settings::sanitize(
			array(
				'tags' => array(
					array(
						'id'      => 'custom-hotjar',
						'catalog' => 'custom',
						'label'   => 'Hotjar',
						'fields'  => array(
							'fragments' => array(
								array( 'code' => $head, 'location' => 'head' ),
								array( 'code' => $body, 'location' => 'footer' ),
							),
						),
					),
				),
			)
		);

		$tag       = $this->sub( $this->sub( $out, 'tags' ), 0 );
		$fragments = $this->sub( $this->sub( $tag, 'fields' ), 'fragments' );
		$this->assertCount( 2, $fragments );
		$this->assertSame( $head, $this->sub( $fragments, 0 )['code'] );
		$this->assertSame( 'head', $this->sub( $fragments, 0 )['location'] );
		$this->assertSame( $body, $this->sub( $fragments, 1 )['code'] );
		$this->assertSame( 'footer', $this->sub( $fragments, 1 )['location'] );
		$this->assertSame( 'Hotjar', $tag['label'] );
	}

	public function test_sanitize_tags_defaults_invalid_fragment_location_to_head(): void {
		$out = Settings::sanitize(
			array(
				'tags' => array(
					array(
						'id'      => 'custom-1',
						'catalog' => 'custom',
						'fields'  => array(
							'fragments' => array(
								array( 'code' => 'A();', 'location' => 'sidebar' ),
							),
						),
					),
				),
			)
		);

		$fragments = $this->sub( $this->sub( $this->sub( $this->sub( $out, 'tags' ), 0 ), 'fields' ), 'fragments' );
		$this->assertSame( 'head', $this->sub( $fragments, 0 )['location'] );
	}

	public function test_sanitize_tags_drops_empty_fragments_and_empty_snippets(): void {
		$out = Settings::sanitize(
			array(
				'tags' => array(
					array(
						'id'      => 'custom-1',
						'catalog' => 'custom',
						'fields'  => array( 'fragments' => array( array( 'code' => '', 'location' => 'head' ) ) ),
					),
					array(
						'id'      => 'custom-2',
						'catalog' => 'custom',
						'fields'  => array(
							'fragments' => array(
								array( 'code' => '', 'location' => 'head' ),
								array( 'code' => 'A();', 'location' => 'footer' ),
							),
						),
					),
				),
			)
		);

		$tags = $this->sub( $out, 'tags' );
		$this->assertSame( array( 'custom-2' ), array_column( $tags, 'id' ) );
		$fragments = $this->sub( $this->sub( $this->sub( $tags, 0 ), 'fields' ), 'fragments' );
		$this->assertCount( 1, $fragments );
		$this->assertSame( 'A();', $this->sub( $fragments, 0 )['code'] );
		$this->assertSame( 'footer', $this->sub( $fragments, 0 )['location'] );
	}

	public function test_sanitize_keeps_fragment_backslashes_byte_for_byte(): void {
		$code = 'var s = "a\nb"; var r = /\d+/;';
		$out  = Settings::sanitize(
			array(
				'tags' => array(
					array(
						'id'      => 'custom-1',
						'catalog' => 'custom',
						'fields'  => array( 'fragments' => array( array( 'code' => $code, 'location' => 'head' ) ) ),
					),
				),
			)
		);

		$fragments = $this->sub( $this->sub( $this->sub( $this->sub( $out, 'tags' ), 0 ), 'fields' ), 'fragments' );
		$this->assertSame( $code, $this->sub( $fragments, 0 )['code'] );
	}

	public function test_sanitize_saves_fragments_when_the_user_can_unfiltered_html(): void {
		$GLOBALS['consentful_test_can']             = true;
		$GLOBALS['consentful_test_settings_errors'] = array();

		$out = Settings::sanitize(
			array(
				'tags' => array(
					array(
						'id'      => 'custom-1',
						'catalog' => 'custom',
						'fields'  => array( 'fragments' => array( array( 'code' => 'A();', 'location' => 'footer' ) ) ),
					),
				),
			)
		);

		$fragments = $this->sub( $this->sub( $this->sub( $this->sub( $out, 'tags' ), 0 ), 'fields' ), 'fragments' );
		$this->assertSame( 'A();', $this->sub( $fragments, 0 )['code'] );
		$this->assertSame( array(), $this->recorded_settings_errors() );
	}

	public function test_sanitize_preserves_previous_fragments_without_unfiltered_html(): void {
		$GLOBALS['consentful_test_can']             = false;
		$GLOBALS['consentful_test_settings_errors'] = array();
		$GLOBALS['consentful_test_options']         = array(
			CONSENTFUL_OPTION => array(
				'tags' => array(
					array(
						'id'      => 'custom-1',
						'catalog' => 'custom',
						'fields'  => array( 'fragments' => array( array( 'code' => 'Old();', 'location' => 'footer' ) ) ),
					),
				),
			),
		);

		$out = Settings::sanitize(
			array(
				'banner' => array( 'enabled' => '1' ),
				'tags'   => array(
					array(
						'id'      => 'custom-1',
						'catalog' => 'custom',
						'fields'  => array( 'fragments' => array( array( 'code' => 'Evil();', 'location' => 'head' ) ) ),
					),
					array(
						'id'      => 'custom-2',
						'catalog' => 'custom',
						'fields'  => array( 'fragments' => array( array( 'code' => 'New();', 'location' => 'head' ) ) ),
					),
				),
			)
		);

		$tags = $this->sub( $out, 'tags' );
		$this->assertSame( array( 'custom-1' ), array_column( $tags, 'id' ) );
		$fragments = $this->sub( $this->sub( $this->sub( $tags, 0 ), 'fields' ), 'fragments' );
		$this->assertSame(
			array(
				array(
					'code'     => 'Old();',
					'location' => 'footer',
				),
			),
			$fragments
		);
		$this->assertTrue( $this->sub( $out, 'banner' )['enabled'] );

		$errors = $this->recorded_settings_errors();
		$this->assertCount( 1, $errors );
		$this->assertSame( 'consentful_snippets_locked', $errors[0]['code'] );
	}

	public function test_sanitize_stays_silent_when_locked_fragments_are_unchanged(): void {
		$GLOBALS['consentful_test_can']             = false;
		$GLOBALS['consentful_test_settings_errors'] = array();
		$GLOBALS['consentful_test_options']         = array(
			CONSENTFUL_OPTION => array(
				'tags' => array(
					array(
						'id'      => 'custom-1',
						'catalog' => 'custom',
						'fields'  => array( 'fragments' => array( array( 'code' => 'Old();', 'location' => 'footer' ) ) ),
					),
				),
			),
		);

		$out = Settings::sanitize(
			array(
				'tags' => array(
					array(
						'id'      => 'custom-1',
						'catalog' => 'custom',
						'fields'  => array( 'fragments' => array( array( 'code' => 'Old();', 'location' => 'footer' ) ) ),
					),
					array(
						'id'      => 'custom-2',
						'catalog' => 'custom',
						'fields'  => array( 'fragments' => array( array( 'code' => '', 'location' => 'head' ) ) ),
					),
				),
			)
		);

		$this->assertSame( array( 'custom-1' ), array_column( $this->sub( $out, 'tags' ), 'id' ) );
		$this->assertSame( array(), $this->recorded_settings_errors() );
	}

	public function test_sanitize_keeps_a_numeric_pixel_id(): void {
		$GLOBALS['consentful_test_settings_errors'] = array();

		$out = Settings::sanitize(
			array(
				'tags' => array(
					array(
						'id'      => 'meta-pixel',
						'catalog' => 'meta-pixel',
						'fields'  => array( 'pixelId' => '123456789' ),
					),
				),
			)
		);

		$tag = $this->sub( $this->sub( $out, 'tags' ), 0 );
		$this->assertSame( array( 'pixelId' => '123456789' ), $this->sub( $tag, 'fields' ) );
		$this->assertSame( array(), $this->recorded_settings_errors() );
	}

	public function test_sanitize_keeps_an_empty_pixel_id(): void {
		$GLOBALS['consentful_test_settings_errors'] = array();

		$out = Settings::sanitize(
			array(
				'tags' => array(
					array(
						'id'      => 'meta-pixel',
						'catalog' => 'meta-pixel',
						'fields'  => array( 'pixelId' => '' ),
					),
				),
			)
		);

		$tag = $this->sub( $this->sub( $out, 'tags' ), 0 );
		$this->assertSame( array( 'pixelId' => '' ), $this->sub( $tag, 'fields' ) );
		$this->assertSame( array(), $this->recorded_settings_errors() );
	}

	public function test_sanitize_rejects_an_invalid_pixel_id_and_keeps_the_previous_value(): void {
		$GLOBALS['consentful_test_settings_errors'] = array();
		$GLOBALS['consentful_test_options']         = array(
			CONSENTFUL_OPTION => array(
				'tags' => array(
					array(
						'id'      => 'meta-pixel',
						'catalog' => 'meta-pixel',
						'fields'  => array( 'pixelId' => '111' ),
					),
				),
			),
		);

		$out = Settings::sanitize(
			array(
				'tags' => array(
					array(
						'id'      => 'meta-pixel',
						'catalog' => 'meta-pixel',
						'fields'  => array( 'pixelId' => 'abc123' ),
					),
				),
			)
		);

		$tag = $this->sub( $this->sub( $out, 'tags' ), 0 );
		$this->assertSame( array( 'pixelId' => '111' ), $this->sub( $tag, 'fields' ) );

		$errors = $this->recorded_settings_errors();
		$this->assertCount( 1, $errors );
		$this->assertSame( 'consentful_pixel_id', $errors[0]['code'] );
	}

	public function test_sanitize_tags_drops_fields_outside_catalog_schema(): void {
		$out = Settings::sanitize(
			array(
				'tags' => array(
					array(
						'id'      => 'ga4',
						'catalog' => 'ga4',
						'fields'  => array(
							'measurementId' => 'G-XXXX',
							'pixelId'       => 'evil',
						),
					),
				),
			)
		);

		$tag = $this->sub( $this->sub( $out, 'tags' ), 0 );
		$this->assertSame( array( 'measurementId' => 'G-XXXX' ), $this->sub( $tag, 'fields' ) );
	}

	public function test_sanitize_geo(): void {
		$out = Settings::sanitize(
			array(
				'geo' => array(
					'adaptive'     => '0',
					'globalPolicy' => 'opt_out',
					'evil'         => 'x',
				),
			)
		);

		$this->assertSame(
			array(
				'adaptive'     => false,
				'globalPolicy' => 'opt_out',
			),
			$this->sub( $out, 'geo' )
		);
	}

	public function test_sanitize_geo_drops_invalid_global_policy(): void {
		$out = Settings::sanitize( array( 'geo' => array( 'globalPolicy' => 'whatever' ) ) );

		$this->assertArrayNotHasKey( 'globalPolicy', $this->sub( $out, 'geo' ) );
	}

	public function test_sanitize_proof(): void {
		$out = Settings::sanitize( array( 'proof' => array( 'enabled' => '0' ) ) );

		$this->assertSame( array( 'enabled' => false ), $this->sub( $out, 'proof' ) );
	}
}

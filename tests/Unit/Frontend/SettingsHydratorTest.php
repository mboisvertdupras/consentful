<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Frontend;

use Consentful\Admin\Settings;
use Consentful\Catalog\Catalog;
use Consentful\Frontend\SettingsHydrator;
use PHPUnit\Framework\TestCase;

/**
 * The hydrator turns the EFFECTIVE settings + Catalog into a ClientConfig: the compliant
 * default baseline from an empty option, the Google merge rule (ga4 + google-ads + gtm →
 * one `google` adapter/tag), script instances (Meta Pixel, custom snippets), the
 * personalization toggle, the simple geo toggle, and purpose-copy overrides flowing into
 * the banner.
 */
final class SettingsHydratorTest extends TestCase {

	/**
	 * Narrow a sub-array of an untyped (`mixed`-valued) array for nested offset access.
	 *
	 * @param array<array-key, mixed> $source
	 * @return array<array-key, mixed>
	 */
	private function sub( array $source, string|int $key ): array {
		$value = $source[ $key ] ?? null;
		$this->assertIsArray( $value );
		return $value;
	}

	/**
	 * Build a ClientConfig array from a partial stored option (merged via Settings accessors).
	 *
	 * @param array<string, mixed> $stored
	 * @param list<\Consentful\Consent\Purpose> $extra_purposes
	 * @param list<\Consentful\Adapter\Adapter> $extra_adapters
	 * @param list<\Consentful\Tag\Tag>         $extra_tags
	 * @return array<string, mixed>
	 */
	private function build(
		array $stored = array(),
		array $extra_purposes = array(),
		array $extra_adapters = array(),
		array $extra_tags = array()
	): array {
		$settings = new Settings( $stored );
		$hydrator = new SettingsHydrator(
			array(
				'banner'   => $settings->banner(),
				'purposes' => $settings->purposes(),
				'tags'     => $settings->tags(),
				'geo'      => $settings->geo(),
				'proof'    => $settings->proof(),
			),
			Catalog::with_defaults(),
			$extra_purposes,
			$extra_adapters,
			$extra_tags
		);

		return $hydrator->client_config(
			1,
			1,
			'consentful',
			'http://example.test/wp-json/consentful/v1/geo',
			'http://example.test/wp-json/consentful/v1/consent',
			'http://example.test/privacy'
		)->to_array();
	}

	public function test_empty_settings_yield_the_compliant_default_baseline(): void {
		$out = $this->build();

		// 4 default purposes (no personalization).
		$this->assertSame(
			array( 'necessary', 'functional', 'analytics', 'marketing' ),
			array_column( $this->sub( $out, 'purposes' ), 'key' )
		);

		// 5 default jurisdictions, builtin geo, no tags/adapters.
		$this->assertSame( array( '*', 'QC', 'EU', 'UK', 'US' ), array_keys( $this->sub( $out, 'jurisdictions' ) ) );
		$this->assertSame( 'EU', $this->sub( $this->sub( $out, 'geo' ), 'map' )['FR'] );
		$this->assertSame( 'http://example.test/wp-json/consentful/v1/geo', $this->sub( $out, 'geo' )['endpoint'] );
		$this->assertSame( array(), $this->sub( $out, 'tags' ) );
		$this->assertSame( array(), $this->sub( $out, 'adapters' ) );

		$this->assertTrue( $this->sub( $out, 'proof' )['enabled'] );
		$this->assertSame( 'http://example.test/privacy', $this->sub( $out, 'banner' )['privacyUrl'] );
	}

	public function test_ga4_and_ads_merge_into_one_google_adapter(): void {
		$out = $this->build(
			array(
				'tags' => array(
					array(
						'id'       => 'ga4',
						'catalog'  => 'ga4',
						'purposes' => array( 'analytics' ),
						'fields'   => array( 'measurementId' => 'G-AAA' ),
					),
					array(
						'id'       => 'google-ads',
						'catalog'  => 'google-ads',
						'purposes' => array( 'marketing' ),
						'fields'   => array( 'conversionId' => 'AW-BBB' ),
					),
				),
			)
		);

		// One google adapter with both ids.
		$adapters = $this->sub( $out, 'adapters' );
		$this->assertSame( array( 'google' ), array_keys( $adapters ) );
		$google = $this->sub( $adapters, 'google' );
		$this->assertSame( 'google', $google['handler'] );
		$this->assertSame( array( 'G-AAA', 'AW-BBB' ), $google['measurementIds'] );
		$this->assertSame( array(), $google['containerIds'] );
		$this->assertTrue( $google['adsDataRedaction'] );
		$this->assertSame( 500, $google['waitForUpdate'] );

		// One google tag with the union of purposes, Direct, adapter 'google'.
		$tags = $this->sub( $out, 'tags' );
		$this->assertCount( 1, $tags );
		$tag = $this->sub( $tags, 0 );
		$this->assertSame( 'google', $tag['id'] );
		$this->assertSame( 'google', $tag['adapter'] );
		$this->assertSame( 'direct', $tag['delivery'] );
		$this->assertSame( array( 'analytics', 'marketing' ), $tag['purposes'] );
	}

	public function test_gtm_loads_a_container_through_the_google_adapter(): void {
		$out = $this->build(
			array(
				'tags' => array(
					array(
						'id'      => 'gtm',
						'catalog' => 'gtm',
						'fields'  => array( 'containerId' => 'GTM-ABCDEF' ),
					),
				),
			)
		);

		// GTM rides the single Google adapter: no gtag ids, one container id, shared signals.
		$adapters = $this->sub( $out, 'adapters' );
		$this->assertSame( array( 'google' ), array_keys( $adapters ) );
		$google = $this->sub( $adapters, 'google' );
		$this->assertSame( 'google', $google['handler'] );
		$this->assertSame( array(), $google['measurementIds'] );
		$this->assertSame( array( 'GTM-ABCDEF' ), $google['containerIds'] );
		$this->assertSame( array( 'analytics_storage' ), $this->sub( $google, 'purposeSignals' )['analytics'] );

		$tags = $this->sub( $out, 'tags' );
		$this->assertCount( 1, $tags );
		$tag = $this->sub( $tags, 0 );
		$this->assertSame( 'google', $tag['id'] );
		$this->assertSame( 'direct', $tag['delivery'] );
		$this->assertSame( array( 'analytics', 'marketing' ), $tag['purposes'] );
	}

	public function test_meta_pixel_emits_a_single_head_script_fragment(): void {
		$out = $this->build(
			array(
				'tags' => array(
					array(
						'id'      => 'meta-pixel',
						'catalog' => 'meta-pixel',
						'fields'  => array( 'pixelId' => '123456' ),
					),
				),
			)
		);

		$pixel = $this->sub( $this->sub( $out, 'adapters' ), 'meta-pixel' );
		$this->assertSame( 'script', $pixel['handler'] );
		$fragments = $this->sub( $pixel, 'fragments' );
		$this->assertCount( 1, $fragments );
		$fragment = $this->sub( $fragments, 0 );
		$this->assertSame( 'head', $fragment['location'] );
		$code = $fragment['code'];
		$this->assertIsString( $code );
		$this->assertStringContainsString( "fbq('init','123456')", $code );
		$this->assertStringNotContainsString( 'document.write', $code );
		$this->assertSame( array( 'marketing' ), $this->sub( $this->sub( $out, 'tags' ), 0 )['purposes'] );
	}

	public function test_custom_snippet_carries_its_script_fragments(): void {
		$out = $this->build(
			array(
				'tags' => array(
					array(
						'id'       => 'custom-a',
						'catalog'  => 'custom',
						'purposes' => array( 'analytics' ),
						'fields'   => array(
							'fragments' => array(
								array( 'code' => '<script>head();</script>', 'location' => 'head' ),
								array( 'code' => '<script src="https://example.test/b.js"></script>', 'location' => 'footer' ),
							),
						),
					),
				),
			)
		);

		$adapters = $this->sub( $out, 'adapters' );
		$this->assertSame( array( 'custom-a' ), array_keys( $adapters ) );
		$a = $this->sub( $adapters, 'custom-a' );
		$this->assertSame( 'script', $a['handler'] );

		$fragments = $this->sub( $a, 'fragments' );
		$this->assertCount( 2, $fragments );
		$this->assertSame( '<script>head();</script>', $this->sub( $fragments, 0 )['code'] );
		$this->assertSame( 'head', $this->sub( $fragments, 0 )['location'] );
		$this->assertSame( '<script src="https://example.test/b.js"></script>', $this->sub( $fragments, 1 )['code'] );
		$this->assertSame( 'footer', $this->sub( $fragments, 1 )['location'] );

		// One tag pointing at its own adapter instance.
		$tags = $this->sub( $out, 'tags' );
		$this->assertSame( array( 'custom-a' ), array_column( $tags, 'id' ) );
		$this->assertSame( array( 'custom-a' ), array_column( $tags, 'adapter' ) );
	}

	public function test_disabled_tag_is_omitted(): void {
		$out = $this->build(
			array(
				'tags' => array(
					array(
						'id'      => 'gtm',
						'catalog' => 'gtm',
						'enabled' => false,
					),
				),
			)
		);

		$this->assertSame( array(), $this->sub( $out, 'tags' ) );
		$this->assertSame( array(), $this->sub( $out, 'adapters' ) );
	}

	public function test_personalization_toggle_adds_the_fifth_purpose(): void {
		$out = $this->build(
			array( 'purposes' => array( 'personalization' => array( 'enabled' => true ) ) )
		);

		$this->assertSame(
			array( 'necessary', 'functional', 'analytics', 'marketing', 'personalization' ),
			array_column( $this->sub( $out, 'purposes' ), 'key' )
		);
	}

	public function test_non_adaptive_geo_yields_a_single_star_jurisdiction(): void {
		$out = $this->build(
			array(
				'geo' => array(
					'adaptive'     => false,
					'globalPolicy' => 'opt_out',
				),
			)
		);

		$jurisdictions = $this->sub( $out, 'jurisdictions' );
		$this->assertSame( array( '*' ), array_keys( $jurisdictions ) );
		$policy = $this->sub( $this->sub( $jurisdictions, '*' ), 'policy' );
		$this->assertSame( 'opt_out', $policy['type'] );
		// Empty geo map ⇒ everyone resolves '*'.
		$geo = $this->sub( $out, 'geo' );
		$this->assertSame( array(), $this->sub( $geo, 'map' ) );
		$this->assertSame( '', $geo['endpoint'] );
	}

	public function test_non_adaptive_opt_out_grants_all_non_always_on_purposes(): void {
		$out = $this->build(
			array(
				'geo' => array(
					'adaptive'     => false,
					'globalPolicy' => 'opt_out',
				),
			)
		);

		$policy = $this->sub( $this->sub( $this->sub( $out, 'jurisdictions' ), '*' ), 'policy' );
		$this->assertSame(
			array( 'functional', 'analytics', 'marketing' ),
			$policy['defaultGranted']
		);
	}

	public function test_non_adaptive_notice_only_posture(): void {
		$out = $this->build(
			array(
				'geo' => array(
					'adaptive'     => false,
					'globalPolicy' => 'notice_only',
				),
			)
		);

		$policy = $this->sub( $this->sub( $this->sub( $out, 'jurisdictions' ), '*' ), 'policy' );
		$this->assertSame( 'notice_only', $policy['type'] );
		$this->assertFalse( $policy['showsBanner'] );
	}

	public function test_purpose_copy_override_flows_into_the_banner(): void {
		$out = $this->build(
			array(
				'purposes' => array(
					'analytics' => array(
						'label'       => 'Site stats',
						'description' => 'We measure usage.',
					),
				),
			)
		);

		$purposes  = $this->sub( $this->sub( $out, 'banner' ), 'purposes' );
		$analytics = $this->sub( $purposes, 'analytics' );
		$this->assertSame( 'Site stats', $analytics['label'] );
		$this->assertSame( 'We measure usage.', $analytics['description'] );
		// Blank override keeps the gettext default for an untouched purpose.
		$this->assertSame( 'Marketing', $this->sub( $purposes, 'marketing' )['label'] );
	}

	public function test_proof_disabled_when_setting_off(): void {
		$out = $this->build( array( 'proof' => array( 'enabled' => false ) ) );

		// The client honors the disabled flag; the endpoint is still emitted by ProofConfig.
		$this->assertFalse( $this->sub( $out, 'proof' )['enabled'] );
	}
}

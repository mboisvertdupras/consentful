<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Frontend;

use Consentful\Adapter\AdapterRegistry;
use Consentful\Adapter\GoogleAdapter;
use Consentful\Consent\DefaultPurpose;
use Consentful\Consent\PurposeRegistry;
use Consentful\Frontend\BannerConfig;
use Consentful\Frontend\ClientConfig;
use Consentful\Jurisdiction\Jurisdiction;
use Consentful\Jurisdiction\Policy;
use Consentful\Tag\Delivery;
use Consentful\Tag\Tag;
use Consentful\Tag\TagRegistry;
use PHPUnit\Framework\TestCase;

/**
 * ClientConfig is the frozen PHP→JS bridge: camelCase keys, registry order, the
 * resolved (fallback) Policy, lowercase delivery, and adapter config verbatim.
 */
final class ClientConfigTest extends TestCase {

	/**
	 * @return array<string, mixed>
	 */
	private function build(
		?TagRegistry $tags = null,
		?AdapterRegistry $adapters = null,
		?Jurisdiction $resolved = null,
		?BannerConfig $banner = null
	): array {
		$config = new ClientConfig(
			PurposeRegistry::with_defaults(),
			$tags ?? new TagRegistry(),
			$adapters ?? new AdapterRegistry(),
			$resolved ?? new Jurisdiction( '*', 'Default', Policy::opt_in( 1 ) ),
			$banner ?? BannerConfig::defaults(),
			1,
			1,
		);
		return $config->to_array();
	}

	public function test_top_level_scalars_and_defaults(): void {
		$out = $this->build();

		$this->assertSame( 'consentful', $out['cookie'] );
		$this->assertSame( 1, $out['schemaVersion'] );
		$this->assertSame( 1, $out['policyVersion'] );
		$this->assertSame( 180, $out['maxAgeDays'] );
		$this->assertSame( '*', $out['jurisdiction'] );
	}

	public function test_purposes_are_in_registry_order_with_always_on_flag(): void {
		$out = $this->build();

		$this->assertSame(
			array(
				array(
					'key'      => 'necessary',
					'alwaysOn' => true,
				),
				array(
					'key'      => 'functional',
					'alwaysOn' => false,
				),
				array(
					'key'      => 'analytics',
					'alwaysOn' => false,
				),
				array(
					'key'      => 'marketing',
					'alwaysOn' => false,
				),
				array(
					'key'      => 'personalization',
					'alwaysOn' => false,
				),
			),
			$out['purposes']
		);
	}

	public function test_opt_in_policy_grants_nothing_by_default(): void {
		$out = $this->build();

		$this->assertSame(
			array(
				'type'                => 'opt_in',
				'version'             => 1,
				'denyByDefault'       => true,
				'blocksBeforeConsent' => true,
				'showsBanner'         => true,
				'defaultGranted'      => array(),
			),
			$out['policy']
		);
	}

	public function test_opt_out_policy_lists_non_always_on_default_grants(): void {
		$granted = array( DefaultPurpose::Analytics, DefaultPurpose::Marketing );
		$resolved = new Jurisdiction( 'US', 'United States', Policy::opt_out( 1, $granted ) );

		$out = $this->build( null, null, $resolved );

		$policy = $out['policy'];
		$this->assertIsArray( $policy );
		$this->assertSame( 'opt_out', $policy['type'] );
		$this->assertFalse( $policy['denyByDefault'] );
		$this->assertFalse( $policy['blocksBeforeConsent'] );
		$this->assertTrue( $policy['showsBanner'] );
		// Always-on Necessary is never listed even though it grants by default.
		$this->assertSame( array( 'analytics', 'marketing' ), $policy['defaultGranted'] );
	}

	public function test_notice_only_policy_type_maps_and_hides_banner(): void {
		$resolved = new Jurisdiction( 'XX', 'Notice', Policy::notice_only( 1, array() ) );

		$out = $this->build( null, null, $resolved );

		$policy = $out['policy'];
		$this->assertIsArray( $policy );
		$this->assertSame( 'notice_only', $policy['type'] );
		$this->assertFalse( $policy['showsBanner'] );
	}

	public function test_tags_serialize_with_purpose_keys_and_lowercase_delivery(): void {
		$tags = new TagRegistry();
		$tags->add( new Tag( 'ga4', 'GA4', array( DefaultPurpose::Analytics ), Delivery::Direct, 'google' ) );
		$tags->add(
			new Tag(
				'gtm-tag',
				'A delegated tag',
				array( DefaultPurpose::Marketing, DefaultPurpose::Analytics ),
				Delivery::Delegated,
				'gtm'
			)
		);

		$out = $this->build( $tags );

		$this->assertSame(
			array(
				array(
					'id'       => 'ga4',
					'purposes' => array( 'analytics' ),
					'delivery' => 'direct',
					'adapter'  => 'google',
				),
				array(
					'id'       => 'gtm-tag',
					'purposes' => array( 'marketing', 'analytics' ),
					'delivery' => 'delegated',
					'adapter'  => 'gtm',
				),
			),
			$out['tags']
		);
	}

	public function test_adapters_are_keyed_by_id_and_passed_through_verbatim(): void {
		$adapters = new AdapterRegistry();
		$google   = new GoogleAdapter( array( 'G-XXXXXXX' ) );
		$adapters->add( $google );

		$out = $this->build( null, $adapters );

		$config = $out['adapters'];
		$this->assertIsArray( $config );
		$this->assertArrayHasKey( 'google', $config );
		$this->assertSame( $google->client_config(), $config['google'] );
	}

	public function test_banner_block_serializes_the_banner_config_verbatim(): void {
		$banner = BannerConfig::defaults();

		$out = $this->build( null, null, null, $banner );

		$this->assertSame( $banner->to_array(), $out['banner'] );
	}

	public function test_custom_cookie_and_max_age_are_honored(): void {
		$config = new ClientConfig(
			PurposeRegistry::with_defaults(),
			new TagRegistry(),
			new AdapterRegistry(),
			new Jurisdiction( '*', 'Default', Policy::opt_in( 1 ) ),
			BannerConfig::defaults(),
			2,
			3,
			90,
			'custom_cookie',
		);

		$out = $config->to_array();

		$this->assertSame( 'custom_cookie', $out['cookie'] );
		$this->assertSame( 90, $out['maxAgeDays'] );
		$this->assertSame( 2, $out['schemaVersion'] );
		$this->assertSame( 3, $out['policyVersion'] );
	}
}

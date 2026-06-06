<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Frontend;

use Consentful\Adapter\AdapterRegistry;
use Consentful\Adapter\GoogleAdapter;
use Consentful\Consent\DefaultPurpose;
use Consentful\Consent\ProofConfig;
use Consentful\Consent\PurposeRegistry;
use Consentful\Frontend\BannerConfig;
use Consentful\Frontend\ClientConfig;
use Consentful\Frontend\GeoConfig;
use Consentful\Jurisdiction\Jurisdiction;
use Consentful\Jurisdiction\JurisdictionRegistry;
use Consentful\Jurisdiction\Policy;
use Consentful\Tag\Delivery;
use Consentful\Tag\Tag;
use Consentful\Tag\TagRegistry;
use PHPUnit\Framework\TestCase;

/**
 * ClientConfig is the frozen PHP→JS bridge: camelCase keys, registry order, ALL
 * Jurisdictions (keyed by id with per-jurisdiction Policy), the geo block, lowercase
 * delivery, and adapter config verbatim. The old single jurisdiction/policy keys are
 * gone — the client resolves the active Jurisdiction at runtime.
 */
final class ClientConfigTest extends TestCase {

	/**
	 * @return array<string, mixed>
	 */
	private function build(
		?TagRegistry $tags = null,
		?AdapterRegistry $adapters = null,
		?JurisdictionRegistry $jurisdictions = null,
		?BannerConfig $banner = null,
		?GeoConfig $geo = null,
		string $geo_endpoint_url = '',
		?ProofConfig $proof = null,
		string $proof_endpoint_url = ''
	): array {
		$config = new ClientConfig(
			PurposeRegistry::with_defaults(),
			$tags ?? new TagRegistry(),
			$adapters ?? new AdapterRegistry(),
			$jurisdictions ?? JurisdictionRegistry::with_defaults( 1 ),
			$banner ?? BannerConfig::defaults(),
			$geo ?? GeoConfig::defaults(),
			$geo_endpoint_url,
			$proof ?? ProofConfig::defaults(),
			$proof_endpoint_url,
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
		$this->assertSame( '*', $out['defaultJurisdiction'] );
	}

	public function test_old_single_jurisdiction_and_policy_keys_are_gone(): void {
		$out = $this->build();

		$this->assertArrayNotHasKey( 'jurisdiction', $out );
		$this->assertArrayNotHasKey( 'policy', $out );
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
			),
			$out['purposes']
		);
	}

	/**
	 * Narrow a `to_array()` sub-array (the values are `mixed`) for offset access.
	 *
	 * @param array<mixed> $out
	 * @return array<mixed>
	 */
	private function sub_array( array $out, string $key ): array {
		$value = $out[ $key ] ?? null;
		$this->assertIsArray( $value );
		return $value;
	}

	public function test_jurisdictions_map_has_all_ids_in_insertion_order(): void {
		$jurisdictions = $this->sub_array( $this->build(), 'jurisdictions' );

		$this->assertSame(
			array( '*', 'QC', 'EU', 'UK', 'US' ),
			array_keys( $jurisdictions )
		);
	}

	public function test_fallback_jurisdiction_is_strictest_opt_in(): void {
		$jurisdictions = $this->sub_array( $this->build(), 'jurisdictions' );
		$star          = $this->sub_array( $jurisdictions, '*' );
		$policy        = $this->sub_array( $star, 'policy' );

		$this->assertSame( '*', $star['id'] );
		$this->assertSame( 'opt_in', $policy['type'] );
		$this->assertTrue( $policy['denyByDefault'] );
		$this->assertSame( array(), $policy['defaultGranted'] );
	}

	public function test_us_jurisdiction_is_opt_out_with_non_empty_default_grants(): void {
		$jurisdictions = $this->sub_array( $this->build(), 'jurisdictions' );
		$us            = $this->sub_array( $jurisdictions, 'US' );
		$policy        = $this->sub_array( $us, 'policy' );

		$this->assertSame( 'US', $us['id'] );
		$this->assertSame( 'opt_out', $policy['type'] );
		$this->assertFalse( $policy['denyByDefault'] );
		$this->assertSame(
			array( 'functional', 'analytics', 'marketing' ),
			$policy['defaultGranted']
		);
	}

	public function test_per_jurisdiction_policy_shape_is_serialized(): void {
		$registry = new JurisdictionRegistry(
			new Jurisdiction( '*', 'Default', Policy::opt_in( 1 ) )
		);
		$registry->add(
			new Jurisdiction( 'XX', 'Notice', Policy::notice_only( 1, array() ) )
		);

		$jurisdictions = $this->sub_array( $this->build( null, null, $registry ), 'jurisdictions' );
		$notice        = $this->sub_array( $jurisdictions, 'XX' );
		$policy        = $this->sub_array( $notice, 'policy' );

		$this->assertSame(
			array( 'type', 'version', 'denyByDefault', 'blocksBeforeConsent', 'showsBanner', 'defaultGranted' ),
			array_keys( $policy )
		);
		$this->assertSame( 'notice_only', $policy['type'] );
		$this->assertFalse( $policy['showsBanner'] );
	}

	public function test_geo_block_shape_with_the_builtin_endpoint(): void {
		$out = $this->build( null, null, null, null, null, 'http://example.test/wp-json/consentful/v1/geo' );
		$geo = $this->sub_array( $out, 'geo' );
		$map = $this->sub_array( $geo, 'map' );

		$this->assertSame( array( 'cookie', 'var', 'endpoint', 'map' ), array_keys( $geo ) );
		$this->assertSame( 'http://example.test/wp-json/consentful/v1/geo', $geo['endpoint'] );
		$this->assertSame( 'EU', $map['FR'] );
	}

	public function test_proof_block_shape_and_values(): void {
		$out = $this->build(
			null,
			null,
			null,
			null,
			null,
			'',
			ProofConfig::defaults(),
			'http://example.test/wp-json/consentful/v1/consent'
		);
		$proof = $this->sub_array( $out, 'proof' );

		$this->assertSame( array( 'enabled', 'endpoint', 'bannerVersion' ), array_keys( $proof ) );
		$this->assertTrue( $proof['enabled'] );
		$this->assertSame( 'http://example.test/wp-json/consentful/v1/consent', $proof['endpoint'] );
		// bannerVersion mirrors the BannerConfig version (defaults to 1).
		$this->assertSame( 1, $proof['bannerVersion'] );
	}

	public function test_proof_block_sits_between_geo_and_tags(): void {
		$keys = array_keys( $this->build() );

		$geo   = array_search( 'geo', $keys, true );
		$proof = array_search( 'proof', $keys, true );
		$tags  = array_search( 'tags', $keys, true );

		$this->assertIsInt( $geo );
		$this->assertIsInt( $proof );
		$this->assertIsInt( $tags );
		$this->assertSame( $proof, $geo + 1 );
		$this->assertSame( $tags, $proof + 1 );
	}

	public function test_proof_disabled_when_proof_config_disabled(): void {
		$out   = $this->build( null, null, null, null, null, '', new ProofConfig( false ) );
		$proof = $this->sub_array( $out, 'proof' );

		$this->assertFalse( $proof['enabled'] );
		$this->assertSame( '', $proof['endpoint'] );
	}

	public function test_proof_banner_version_tracks_the_banner_config(): void {
		$banner = new BannerConfig(
			true,
			'bar',
			'auto',
			'#2563eb',
			8,
			7,
			'',
			array(),
			array()
		);

		$out   = $this->build( null, null, null, $banner );
		$proof = $this->sub_array( $out, 'proof' );

		$this->assertSame( 7, $proof['bannerVersion'] );
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

	public function test_hidden_tags_are_omitted_from_the_emitted_tags(): void {
		$tags = new TagRegistry();
		$tags->add( new Tag( 'ga4', 'GA4', array( DefaultPurpose::Analytics ), Delivery::Direct, 'google' ) );
		$tags->add( new Tag( 'meta', 'Meta', array( DefaultPurpose::Marketing ), Delivery::Direct, 'meta' ) );

		$config = new ClientConfig(
			PurposeRegistry::with_defaults(),
			$tags,
			new AdapterRegistry(),
			JurisdictionRegistry::with_defaults( 1 ),
			BannerConfig::defaults(),
			GeoConfig::defaults(),
			'',
			ProofConfig::defaults(),
			'',
			1,
			1,
			180,
			'consentful',
			array( 'meta' ),
		);

		$out = $config->to_array();
		$this->assertIsArray( $out['tags'] );
		$ids = array_column( $out['tags'], 'id' );
		$this->assertSame( array( 'ga4' ), $ids );
	}

	public function test_empty_hidden_tags_leave_tags_unchanged(): void {
		$tags = new TagRegistry();
		$tags->add( new Tag( 'ga4', 'GA4', array( DefaultPurpose::Analytics ), Delivery::Direct, 'google' ) );
		$tags->add( new Tag( 'meta', 'Meta', array( DefaultPurpose::Marketing ), Delivery::Direct, 'meta' ) );

		$out = $this->build( $tags );
		$this->assertIsArray( $out['tags'] );
		$this->assertSame( array( 'ga4', 'meta' ), array_column( $out['tags'], 'id' ) );
	}

	public function test_custom_cookie_and_max_age_are_honored(): void {
		$config = new ClientConfig(
			PurposeRegistry::with_defaults(),
			new TagRegistry(),
			new AdapterRegistry(),
			JurisdictionRegistry::with_defaults( 1 ),
			BannerConfig::defaults(),
			GeoConfig::defaults(),
			'',
			ProofConfig::defaults(),
			'',
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

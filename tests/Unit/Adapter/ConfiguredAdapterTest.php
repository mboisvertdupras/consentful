<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Adapter;

use Consentful\Adapter\ConfiguredAdapter;
use Consentful\Consent\DefaultPurpose;
use Consentful\Tag\Delivery;
use Consentful\Tag\Tag;
use PHPUnit\Framework\TestCase;

/**
 * ConfiguredAdapter is the generic adapter the hydrator builds for gtm + each script
 * instance: its id is the instance id a Tag references, and it returns its client-config
 * array verbatim (carrying the `handler` field the gate resolves on).
 */
final class ConfiguredAdapterTest extends TestCase {

	public function test_id_and_client_config_round_trip(): void {
		$config  = array(
			'handler' => 'script',
			'code'    => 'console.log(1);',
		);
		$adapter = new ConfiguredAdapter( 'custom-hotjar', $config );

		$this->assertSame( 'custom-hotjar', $adapter->id() );
		$this->assertSame( $config, $adapter->client_config() );
	}

	public function test_handles_only_tags_pointing_at_its_id(): void {
		$adapter = new ConfiguredAdapter( 'gtm', array( 'handler' => 'gtm' ) );
		$mine    = new Tag( 'gtm', 'GTM', array( DefaultPurpose::Analytics ), Delivery::Delegated, 'gtm' );
		$other   = new Tag( 'ga4', 'GA4', array( DefaultPurpose::Analytics ), Delivery::Direct, 'google' );

		$this->assertTrue( $adapter->handles( $mine ) );
		$this->assertFalse( $adapter->handles( $other ) );
	}
}

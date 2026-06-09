<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Adapter;

use Consentful\Adapter\GoogleAdapter;
use Consentful\Consent\DefaultPurpose;
use Consentful\Consent\Signal;
use Consentful\Tag\Delivery;
use Consentful\Tag\Tag;
use PHPUnit\Framework\TestCase;

final class GoogleAdapterTest extends TestCase {

	public function test_id_is_google(): void {
		$this->assertSame( 'google', ( new GoogleAdapter( array() ) )->id() );
		$this->assertSame( 'google', GoogleAdapter::ID );
	}

	public function test_handles_only_tags_pointing_at_its_id(): void {
		$adapter = new GoogleAdapter( array() );
		$mine    = new Tag( 'ga4', 'GA4', array( DefaultPurpose::Analytics ), Delivery::Direct, 'google' );
		$other   = new Tag( 'pixel', 'Pixel', array( DefaultPurpose::Marketing ), Delivery::Direct, 'meta' );

		$this->assertTrue( $adapter->handles( $mine ) );
		$this->assertFalse( $adapter->handles( $other ) );
	}

	public function test_default_signal_map_covers_every_default_purpose(): void {
		$map = GoogleAdapter::default_signal_map();

		$this->assertSame( array( Signal::SecurityStorage ), $map['necessary'] );
		$this->assertSame( array( Signal::FunctionalityStorage ), $map['functional'] );
		$this->assertSame( array( Signal::AnalyticsStorage ), $map['analytics'] );
		$this->assertSame(
			array( Signal::AdStorage, Signal::AdUserData, Signal::AdPersonalization ),
			$map['marketing']
		);
		$this->assertSame( array( Signal::PersonalizationStorage ), $map['personalization'] );
	}

	public function test_client_config_uses_the_default_map_and_flags(): void {
		$adapter = new GoogleAdapter( array( 'G-XXXXXXX', 'AW-1111' ) );

		$config = $adapter->client_config();

		$this->assertSame(
			array(
				'handler'          => 'google',
				'measurementIds'   => array( 'G-XXXXXXX', 'AW-1111' ),
				'containerIds'     => array(),
				'purposeSignals'   => array(
					'necessary'       => array( 'security_storage' ),
					'functional'      => array( 'functionality_storage' ),
					'analytics'       => array( 'analytics_storage' ),
					'marketing'       => array( 'ad_storage', 'ad_user_data', 'ad_personalization' ),
					'personalization' => array( 'personalization_storage' ),
				),
				'adsDataRedaction' => true,
				'urlPassthrough'   => true,
				'waitForUpdate'    => 500,
			),
			$config
		);
	}

	public function test_client_config_carries_gtm_container_ids(): void {
		$config = ( new GoogleAdapter( array( 'G-XXXXXXX' ), array(), true, true, 500, array( 'GTM-ABC', 'GTM-DEF' ) ) )->client_config();

		$this->assertSame( array( 'GTM-ABC', 'GTM-DEF' ), $config['containerIds'] );
		$this->assertSame( array( 'G-XXXXXXX' ), $config['measurementIds'] );
	}

	public function test_security_storage_is_always_present_via_necessary(): void {
		$config = ( new GoogleAdapter( array( 'G-XXXXXXX' ) ) )->client_config();

		$signals = $config['purposeSignals'];
		$this->assertIsArray( $signals );
		$this->assertSame( array( 'security_storage' ), $signals['necessary'] );
	}

	public function test_client_config_uses_an_override_map_when_supplied(): void {
		$adapter = new GoogleAdapter(
			array( 'G-OVERRIDE' ),
			array(
				'analytics' => array( Signal::AnalyticsStorage ),
				'marketing' => array( Signal::AdStorage ),
			),
		);

		$config = $adapter->client_config();

		$this->assertSame(
			array(
				'analytics' => array( 'analytics_storage' ),
				'marketing' => array( 'ad_storage' ),
			),
			$config['purposeSignals']
		);
	}

	public function test_flags_and_wait_for_update_are_configurable(): void {
		$adapter = new GoogleAdapter( array( 'G-XXXXXXX' ), array(), false, false, 0 );

		$config = $adapter->client_config();

		$this->assertFalse( $config['adsDataRedaction'] );
		$this->assertFalse( $config['urlPassthrough'] );
		$this->assertSame( 0, $config['waitForUpdate'] );
	}
}

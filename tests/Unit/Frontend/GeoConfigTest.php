<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Frontend;

use Consentful\Frontend\GeoConfig;
use PHPUnit\Framework\TestCase;

/**
 * GeoConfig is a pure value object: defaults degrade to today's strictest fallback,
 * the endpoint resolves by precedence, and the default region map covers EU/UK/US/QC.
 */
final class GeoConfigTest extends TestCase {

	public function test_defaults_have_no_signal_and_enable_the_builtin_endpoint(): void {
		$geo = GeoConfig::defaults();

		$this->assertSame( '', $geo->cookie_name );
		$this->assertSame( '', $geo->var_name );
		$this->assertTrue( $geo->use_builtin_endpoint );
		$this->assertSame( '', $geo->endpoint );
	}

	public function test_to_array_emits_the_frozen_geo_shape(): void {
		$out = GeoConfig::defaults()->to_array( 'http://example.test/wp-json/consentful/v1/geo' );

		$this->assertSame(
			array( 'cookie', 'var', 'endpoint', 'map' ),
			array_keys( $out )
		);
		$this->assertSame( '', $out['cookie'] );
		$this->assertSame( '', $out['var'] );
		$this->assertNotEmpty( $out['map'] );
	}

	public function test_builtin_endpoint_resolves_to_the_passed_url_when_enabled(): void {
		$out = GeoConfig::defaults()->to_array( 'http://example.test/wp-json/consentful/v1/geo' );

		$this->assertSame( 'http://example.test/wp-json/consentful/v1/geo', $out['endpoint'] );
	}

	public function test_explicit_endpoint_wins_over_the_builtin_url(): void {
		$geo = new GeoConfig( '', '', true, 'https://geo.example.com/region', array() );

		$out = $geo->to_array( 'http://example.test/wp-json/consentful/v1/geo' );

		$this->assertSame( 'https://geo.example.com/region', $out['endpoint'] );
	}

	public function test_endpoint_is_empty_when_builtin_disabled_and_none_explicit(): void {
		$geo = new GeoConfig( '', '', false, '', array() );

		$out = $geo->to_array( 'http://example.test/wp-json/consentful/v1/geo' );

		$this->assertSame( '', $out['endpoint'] );
	}

	public function test_default_map_routes_known_regions_and_omits_unmapped(): void {
		$map = GeoConfig::defaults()->to_array( '' )['map'];

		$this->assertSame( 'EU', $map['FR'] );
		$this->assertSame( 'EU', $map['DE'] );
		$this->assertSame( 'UK', $map['GB'] );
		$this->assertSame( 'US', $map['US'] );
		$this->assertSame( 'QC', $map['CA-QC'] );
		// Unmapped regions fall through to the strictest '*' fallback (correct).
		$this->assertArrayNotHasKey( 'JP', $map );
		$this->assertArrayNotHasKey( 'CA', $map );
	}

	public function test_default_map_covers_eu_27_and_eea_3(): void {
		$map = GeoConfig::defaults()->to_array( '' )['map'];

		$eu_eea = array(
			'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR',
			'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK',
			'SI', 'ES', 'SE', 'IS', 'LI', 'NO',
		);
		foreach ( $eu_eea as $code ) {
			$this->assertSame( 'EU', $map[ $code ], "Expected {$code} → EU" );
		}
	}
}

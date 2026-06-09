<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Consent;

use Consentful\Consent\ProofConfig;
use PHPUnit\Framework\TestCase;

final class ProofConfigTest extends TestCase {

	public function test_defaults_enable_proof(): void {
		$this->assertTrue( ProofConfig::defaults()->enabled );
	}

	public function test_to_array_shape_and_values(): void {
		$out = ProofConfig::defaults()->to_array( 'http://site/wp-json/consentful/v1/consent', 5 );

		$this->assertSame(
			array(
				'enabled'       => true,
				'endpoint'      => 'http://site/wp-json/consentful/v1/consent',
				'bannerVersion' => 5,
			),
			$out
		);
	}

	public function test_disabled_config_emits_false(): void {
		$out = ( new ProofConfig( false ) )->to_array( '', 1 );

		$this->assertFalse( $out['enabled'] );
		$this->assertSame( '', $out['endpoint'] );
	}
}

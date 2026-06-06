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
		$keys = array( 'title', 'description', 'privacyLabel', 'prefsTitle', 'acceptAll', 'rejectAll', 'customize', 'save', 'reopen' );
		foreach ( $keys as $key ) {
			$this->assertArrayHasKey( $key, $copy );
			$this->assertNotSame( '', $copy[ $key ] );
		}
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
}

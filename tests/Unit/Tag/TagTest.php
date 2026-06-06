<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Tag;

use Consentful\Consent\Consent;
use Consentful\Consent\DefaultPurpose;
use Consentful\Tag\Delivery;
use Consentful\Tag\Tag;
use PHPUnit\Framework\TestCase;

/**
 * Covers Tag gating (fail-closed on empty purposes) and the Delivery enum.
 */
final class TagTest extends TestCase {

	/**
	 * @param array<string, bool> $grants
	 */
	private function consent( array $grants ): Consent {
		return new Consent( $grants, 'QC', 1, 1, 1000 );
	}

	public function test_empty_purposes_is_fail_closed(): void {
		$tag = new Tag( 'noop', 'No purposes', array(), Delivery::Direct, 'google' );

		$this->assertFalse( $tag->is_granted( $this->consent( array( 'analytics' => true ) ) ) );
	}

	public function test_granted_when_every_purpose_is_granted(): void {
		$tag = new Tag(
			'ga4',
			'Google Analytics 4',
			array( DefaultPurpose::Analytics, DefaultPurpose::Marketing ),
			Delivery::Direct,
			'google'
		);

		$consent = $this->consent(
			array(
				'analytics' => true,
				'marketing' => true,
			)
		);

		$this->assertTrue( $tag->is_granted( $consent ) );
	}

	public function test_not_granted_when_any_purpose_is_denied(): void {
		$tag = new Tag(
			'ga4',
			'Google Analytics 4',
			array( DefaultPurpose::Analytics, DefaultPurpose::Marketing ),
			Delivery::Direct,
			'google'
		);

		$consent = $this->consent(
			array(
				'analytics' => true,
				'marketing' => false,
			)
		);

		$this->assertFalse( $tag->is_granted( $consent ) );
	}

	public function test_not_granted_when_a_purpose_is_absent(): void {
		$tag = new Tag(
			'pixel',
			'Meta Pixel',
			array( DefaultPurpose::Marketing ),
			Delivery::Direct,
			'meta'
		);

		$this->assertFalse( $tag->is_granted( $this->consent( array() ) ) );
	}

	public function test_always_on_purpose_is_granted_without_a_stored_grant(): void {
		$tag = new Tag(
			'essential',
			'Essential snippet',
			array( DefaultPurpose::Necessary ),
			Delivery::Direct,
			'core'
		);

		$this->assertTrue( $tag->is_granted( $this->consent( array() ) ) );
	}

	public function test_always_on_does_not_mask_a_denied_gateable_sibling(): void {
		$tag = new Tag(
			'mixed',
			'Necessary + Analytics',
			array( DefaultPurpose::Necessary, DefaultPurpose::Analytics ),
			Delivery::Direct,
			'core'
		);

		$this->assertFalse( $tag->is_granted( $this->consent( array( 'analytics' => false ) ) ) );
	}

	public function test_exposes_its_constructor_data(): void {
		$purposes = array( DefaultPurpose::Analytics );
		$tag      = new Tag( 'ga4', 'Google Analytics 4', $purposes, Delivery::Delegated, 'gtm' );

		$this->assertSame( 'ga4', $tag->id );
		$this->assertSame( 'Google Analytics 4', $tag->label );
		$this->assertSame( $purposes, $tag->purposes );
		$this->assertSame( Delivery::Delegated, $tag->delivery );
		$this->assertSame( 'gtm', $tag->adapter_id );
	}

	public function test_delivery_has_direct_and_delegated_cases(): void {
		$cases = Delivery::cases();

		$this->assertSame( array( Delivery::Direct, Delivery::Delegated ), $cases );
	}
}

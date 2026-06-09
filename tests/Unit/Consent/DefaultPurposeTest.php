<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Consent;

use Consentful\Consent\CustomPurpose;
use Consentful\Consent\DefaultPurpose;
use Consentful\Consent\Purpose;
use PHPUnit\Framework\TestCase;

final class DefaultPurposeTest extends TestCase {

	public function test_backing_values_are_the_stable_keys(): void {
		$this->assertSame( 'necessary', DefaultPurpose::Necessary->value );
		$this->assertSame( 'functional', DefaultPurpose::Functional->value );
		$this->assertSame( 'analytics', DefaultPurpose::Analytics->value );
		$this->assertSame( 'marketing', DefaultPurpose::Marketing->value );
		$this->assertSame( 'personalization', DefaultPurpose::Personalization->value );
	}

	public function test_key_returns_the_backing_value(): void {
		foreach ( DefaultPurpose::cases() as $purpose ) {
			$this->assertSame( $purpose->value, $purpose->key() );
		}
	}

	public function test_only_necessary_is_always_on(): void {
		$this->assertTrue( DefaultPurpose::Necessary->is_always_on() );
		$this->assertFalse( DefaultPurpose::Functional->is_always_on() );
		$this->assertFalse( DefaultPurpose::Analytics->is_always_on() );
		$this->assertFalse( DefaultPurpose::Marketing->is_always_on() );
		$this->assertFalse( DefaultPurpose::Personalization->is_always_on() );
	}

	public function test_defaults_ship_the_four_universal_purposes_in_order(): void {
		$defaults = DefaultPurpose::defaults();

		$this->assertCount( 4, $defaults );

		$expected = array(
			DefaultPurpose::Necessary,
			DefaultPurpose::Functional,
			DefaultPurpose::Analytics,
			DefaultPurpose::Marketing,
		);
		$this->assertSame( $expected, $defaults );
	}

	public function test_personalization_is_an_optional_member_not_shipped_by_default(): void {
		$this->assertContains( DefaultPurpose::Personalization, DefaultPurpose::cases() );
		$this->assertNotContains( DefaultPurpose::Personalization, DefaultPurpose::defaults() );
	}

	public function test_default_purpose_implements_the_purpose_contract(): void {
		$this->assertInstanceOf( Purpose::class, DefaultPurpose::Necessary );
	}

	public function test_custom_purpose_honors_key_and_always_on(): void {
		$gateable = new CustomPurpose( 'crm' );
		$this->assertSame( 'crm', $gateable->key() );
		$this->assertFalse( $gateable->is_always_on() );

		$essential = new CustomPurpose( 'security', true );
		$this->assertSame( 'security', $essential->key() );
		$this->assertTrue( $essential->is_always_on() );
	}

	public function test_custom_purpose_defaults_to_gateable(): void {
		$purpose = new CustomPurpose( 'ab_testing' );
		$this->assertFalse( $purpose->is_always_on() );
	}

	public function test_custom_purpose_implements_the_purpose_contract(): void {
		$this->assertInstanceOf( Purpose::class, new CustomPurpose( 'crm' ) );
	}
}

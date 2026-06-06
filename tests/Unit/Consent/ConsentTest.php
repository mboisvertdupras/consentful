<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Consent;

use Consentful\Consent\Consent;
use Consentful\Consent\DefaultPurpose;
use PHPUnit\Framework\TestCase;

final class ConsentTest extends TestCase {

	/**
	 * @param array<string, bool> $grants
	 */
	private function consent( array $grants = array() ): Consent {
		return new Consent( $grants, 'QC', 1, 1, 1_700_000_000_000 );
	}

	public function test_granted_is_true_for_always_on_even_when_absent(): void {
		$consent = $this->consent();

		$this->assertTrue( $consent->granted( DefaultPurpose::Necessary ) );
	}

	public function test_granted_reflects_stored_grant_for_gateable_purpose(): void {
		$granted = $this->consent( array( 'analytics' => true ) );
		$denied  = $this->consent( array( 'analytics' => false ) );
		$absent  = $this->consent();

		$this->assertTrue( $granted->granted( DefaultPurpose::Analytics ) );
		$this->assertFalse( $denied->granted( DefaultPurpose::Analytics ) );
		$this->assertFalse( $absent->granted( DefaultPurpose::Analytics ) );
	}

	public function test_to_cookie_emits_compact_shape_with_zero_one_grants(): void {
		$consent = new Consent(
			array( 'analytics' => true, 'marketing' => false ),
			'EU',
			1,
			1,
			1_700_000_000_000,
		);

		$cookie = $consent->to_cookie();

		$this->assertSame(
			array(
				'v' => 1,
				'p' => 1,
				'j' => 'EU',
				'g' => array( 'analytics' => 1, 'marketing' => 0 ),
				't' => 1_700_000_000_000,
			),
			$cookie,
		);
	}

	public function test_to_cookie_from_cookie_round_trip(): void {
		$consent = new Consent(
			array( 'analytics' => true, 'marketing' => false ),
			'US',
			1,
			1,
			1_700_000_000_000,
		);

		$restored = Consent::from_cookie( $consent->to_cookie() );

		$this->assertInstanceOf( Consent::class, $restored );
		$this->assertSame( 'US', $restored->jurisdiction );
		$this->assertSame( 1, $restored->schema_version );
		$this->assertSame( 1, $restored->policy_version );
		$this->assertSame( 1_700_000_000_000, $restored->timestamp );
		$this->assertSame( array( 'analytics' => true, 'marketing' => false ), $restored->grants );
	}

	public function test_from_cookie_returns_null_for_non_array(): void {
		$this->assertNull( Consent::from_cookie( 'not-an-array' ) );
		$this->assertNull( Consent::from_cookie( null ) );
		$this->assertNull( Consent::from_cookie( 42 ) );
	}

	public function test_from_cookie_returns_null_when_required_keys_are_missing(): void {
		$this->assertNull( Consent::from_cookie( array( 'p' => 1, 'g' => array(), 't' => 1 ) ) );
		$this->assertNull( Consent::from_cookie( array( 'v' => 1, 'g' => array(), 't' => 1 ) ) );
		$this->assertNull( Consent::from_cookie( array( 'v' => 1, 'p' => 1, 't' => 1 ) ) );
		$this->assertNull( Consent::from_cookie( array( 'v' => 1, 'p' => 1, 'g' => array() ) ) );
	}

	public function test_from_cookie_returns_null_when_grants_is_not_an_array(): void {
		$this->assertNull( Consent::from_cookie( array( 'v' => 1, 'p' => 1, 'g' => 'nope', 't' => 1 ) ) );
	}

	public function test_from_cookie_accepts_numeric_string_versions_and_timestamp(): void {
		$restored = Consent::from_cookie(
			array( 'v' => '1', 'p' => '2', 'g' => array(), 't' => '1700000000000' ),
		);

		$this->assertInstanceOf( Consent::class, $restored );
		$this->assertSame( 1, $restored->schema_version );
		$this->assertSame( 2, $restored->policy_version );
		$this->assertSame( 1_700_000_000_000, $restored->timestamp );
	}

	public function test_from_cookie_returns_null_for_non_numeric_version(): void {
		$this->assertNull( Consent::from_cookie( array( 'v' => 'abc', 'p' => 1, 'g' => array(), 't' => 1 ) ) );
	}

	public function test_from_cookie_returns_null_for_boolean_version(): void {
		$this->assertNull( Consent::from_cookie( array( 'v' => true, 'p' => 1, 'g' => array(), 't' => 1 ) ) );
	}

	public function test_from_cookie_returns_null_for_non_numeric_timestamp(): void {
		$this->assertNull( Consent::from_cookie( array( 'v' => 1, 'p' => 1, 'g' => array(), 't' => 'oops' ) ) );
	}

	public function test_from_cookie_coerces_non_scalar_jurisdiction_to_empty_string(): void {
		$restored = Consent::from_cookie(
			array( 'v' => 1, 'p' => 1, 'j' => array( 'QC' ), 'g' => array(), 't' => 1 ),
		);

		$this->assertInstanceOf( Consent::class, $restored );
		$this->assertSame( '', $restored->jurisdiction );
	}

	public function test_from_cookie_skips_numeric_string_grant_keys(): void {
		$restored = Consent::from_cookie(
			array( 'v' => 1, 'p' => 1, 'g' => array( '0' => 1, 'analytics' => 1 ), 't' => 1 ),
		);

		$this->assertInstanceOf( Consent::class, $restored );
		$this->assertSame( array( 'analytics' => true ), $restored->grants );
	}

	public function test_from_cookie_defaults_jurisdiction_to_empty_string(): void {
		$restored = Consent::from_cookie( array( 'v' => 1, 'p' => 1, 'g' => array(), 't' => 1 ) );

		$this->assertInstanceOf( Consent::class, $restored );
		$this->assertSame( '', $restored->jurisdiction );
	}

	public function test_from_cookie_coerces_grant_values_to_bool(): void {
		$restored = Consent::from_cookie(
			array( 'v' => 1, 'p' => 1, 'g' => array( 'analytics' => 1, 'marketing' => 0 ), 't' => 1 ),
		);

		$this->assertInstanceOf( Consent::class, $restored );
		$this->assertSame( array( 'analytics' => true, 'marketing' => false ), $restored->grants );
	}

	public function test_is_valid_happy_path(): void {
		$consent = $this->consent();

		$this->assertTrue( $consent->is_valid( 1, 1, 1000, 1_700_000_000_500 ) );
	}

	public function test_is_valid_false_on_schema_mismatch(): void {
		$consent = $this->consent();

		$this->assertFalse( $consent->is_valid( 2, 1, 1_000_000, 1_700_000_000_500 ) );
	}

	public function test_is_valid_false_on_policy_mismatch(): void {
		$consent = $this->consent();

		$this->assertFalse( $consent->is_valid( 1, 2, 1_000_000, 1_700_000_000_500 ) );
	}

	public function test_is_valid_false_when_expired(): void {
		$consent = $this->consent();

		// now - timestamp = 2000 ms, exceeding the 1000 ms window.
		$this->assertFalse( $consent->is_valid( 1, 1, 1000, 1_700_000_002_000 ) );
	}

	public function test_is_valid_false_when_timestamp_is_zero(): void {
		$consent = new Consent( array(), 'QC', 1, 1, 0 );

		$this->assertFalse( $consent->is_valid( 1, 1, 1_000_000, 1_700_000_000_000 ) );
	}

	public function test_is_valid_at_exactly_max_age_is_valid(): void {
		$consent = $this->consent();

		// age == max_age_ms is within the window (inclusive boundary).
		$this->assertTrue( $consent->is_valid( 1, 1, 1000, 1_700_000_001_000 ) );
	}

	public function test_is_valid_one_ms_past_max_age_is_invalid(): void {
		$consent = $this->consent();

		// age == max_age_ms + 1 is past the window.
		$this->assertFalse( $consent->is_valid( 1, 1, 1000, 1_700_000_001_001 ) );
	}

	public function test_is_valid_for_a_future_timestamp(): void {
		$consent = $this->consent();

		// now < timestamp ⇒ age is negative, which is <= max_age_ms, so valid.
		$this->assertTrue( $consent->is_valid( 1, 1, 1000, 1_699_999_999_000 ) );
	}

	public function test_granted_treats_a_present_false_grant_as_not_granted(): void {
		$consent = $this->consent( array( 'analytics' => false ) );

		$this->assertFalse( $consent->granted( DefaultPurpose::Analytics ) );
	}
}

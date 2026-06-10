<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Jurisdiction;

use Consentful\Consent\CustomPurpose;
use Consentful\Consent\DefaultPurpose;
use Consentful\Jurisdiction\Jurisdiction;
use Consentful\Jurisdiction\JurisdictionRegistry;
use Consentful\Jurisdiction\Policy;
use Consentful\Jurisdiction\PolicyType;
use PHPUnit\Framework\TestCase;

final class JurisdictionRegistryTest extends TestCase {

	public function test_constructor_stores_and_adds_fallback(): void {
		$fallback = new Jurisdiction( '*', 'Default (strictest)', Policy::opt_in( 1 ) );
		$registry = new JurisdictionRegistry( $fallback );

		$this->assertSame( $fallback, $registry->fallback() );
		$this->assertSame( $fallback, $registry->get( '*' ) );
	}

	public function test_add_then_get_returns_match(): void {
		$registry = new JurisdictionRegistry( new Jurisdiction( '*', 'Default', Policy::opt_in( 1 ) ) );
		$quebec   = new Jurisdiction( 'QC', 'Québec (Loi 25)', Policy::opt_in( 1 ) );
		$registry->add( $quebec );

		$this->assertSame( $quebec, $registry->get( 'QC' ) );
	}

	public function test_all_returns_every_jurisdiction_in_insertion_order(): void {
		$fallback = new Jurisdiction( '*', 'Default', Policy::opt_in( 1 ) );
		$registry = new JurisdictionRegistry( $fallback );
		$quebec   = new Jurisdiction( 'QC', 'Québec (Loi 25)', Policy::opt_in( 1 ) );
		$us       = new Jurisdiction( 'US', 'United States', Policy::opt_out( 1, array() ) );
		$registry->add( $quebec );
		$registry->add( $us );

		$this->assertSame( array( $fallback, $quebec, $us ), $registry->all() );
	}

	public function test_with_defaults_all_lists_every_default_jurisdiction(): void {
		$ids = array_map(
			static fn ( Jurisdiction $jurisdiction ): string => $jurisdiction->id,
			JurisdictionRegistry::with_defaults( 1, DefaultPurpose::defaults() )->all()
		);

		$this->assertSame( array( '*', 'QC', 'EU', 'UK', 'US' ), $ids );
	}

	public function test_with_defaults_uses_opt_in_for_eu_uk_qc(): void {
		$registry = JurisdictionRegistry::with_defaults( 1, DefaultPurpose::defaults() );

		$this->assertSame( PolicyType::OptIn, $registry->get( 'QC' )->policy->type );
		$this->assertSame( PolicyType::OptIn, $registry->get( 'EU' )->policy->type );
		$this->assertSame( PolicyType::OptIn, $registry->get( 'UK' )->policy->type );
	}

	public function test_with_defaults_uses_opt_out_for_us(): void {
		$registry = JurisdictionRegistry::with_defaults( 1, DefaultPurpose::defaults() );
		$us       = $registry->get( 'US' );

		$this->assertSame( PolicyType::OptOut, $us->policy->type );
	}

	public function test_us_default_granted_is_every_non_always_on_default_purpose(): void {
		$registry = JurisdictionRegistry::with_defaults( 1, DefaultPurpose::defaults() );
		$us       = $registry->get( 'US' );

		$expected = array_values(
			array_filter(
				DefaultPurpose::defaults(),
				static fn ( DefaultPurpose $purpose ): bool => ! $purpose->is_always_on(),
			)
		);

		$this->assertSame( $expected, $us->policy->default_granted );
		$this->assertTrue( $us->policy->grants_by_default( DefaultPurpose::Analytics ) );
		$this->assertTrue( $us->policy->grants_by_default( DefaultPurpose::Marketing ) );
	}

	public function test_us_default_granted_follows_the_hydrated_purposes(): void {
		$custom = new CustomPurpose( 'ab_testing' );
		$us     = JurisdictionRegistry::with_defaults(
			1,
			array(
				DefaultPurpose::Necessary,
				DefaultPurpose::Functional,
				DefaultPurpose::Analytics,
				DefaultPurpose::Marketing,
				DefaultPurpose::Personalization,
				$custom,
			)
		)->get( 'US' );

		$this->assertSame(
			array(
				DefaultPurpose::Functional,
				DefaultPurpose::Analytics,
				DefaultPurpose::Marketing,
				DefaultPurpose::Personalization,
				$custom,
			),
			$us->policy->default_granted
		);
		$this->assertTrue( $us->policy->grants_by_default( DefaultPurpose::Personalization ) );
		$this->assertTrue( $us->policy->grants_by_default( $custom ) );
	}

	public function test_with_defaults_fallback_is_strictest_opt_in(): void {
		$registry = JurisdictionRegistry::with_defaults( 1, DefaultPurpose::defaults() );
		$fallback = $registry->fallback();

		$this->assertSame( '*', $fallback->id );
		$this->assertSame( 'Default (strictest)', $fallback->label );
		$this->assertSame( PolicyType::OptIn, $fallback->policy->type );
	}

	public function test_unknown_id_falls_back_to_strictest(): void {
		$registry = JurisdictionRegistry::with_defaults( 1, DefaultPurpose::defaults() );

		$this->assertSame( $registry->fallback(), $registry->get( 'ZZ' ) );
	}

	public function test_fallback_is_immutable_against_a_rogue_star_jurisdiction(): void {
		$registry = JurisdictionRegistry::with_defaults( 1, DefaultPurpose::defaults() );

		$registry->add( new Jurisdiction( '*', 'Rogue', Policy::opt_out( 1, array() ) ) );

		$this->assertSame( PolicyType::OptIn, $registry->fallback()->policy->type );
		$this->assertSame( PolicyType::OptIn, $registry->get( 'ZZ' )->policy->type );
	}
}

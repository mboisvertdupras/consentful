<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Consent;

use Consentful\Consent\CustomPurpose;
use Consentful\Consent\DefaultPurpose;
use Consentful\Consent\PurposeRegistry;
use PHPUnit\Framework\TestCase;

final class PurposeRegistryTest extends TestCase {

	public function test_with_defaults_holds_the_four_default_purposes_in_order(): void {
		$registry = PurposeRegistry::with_defaults();

		$this->assertSame( DefaultPurpose::defaults(), $registry->all() );
		$this->assertCount( 4, $registry->all() );
	}

	public function test_personalization_is_opt_in_via_add(): void {
		$registry = PurposeRegistry::with_defaults();
		$registry->add( DefaultPurpose::Personalization );

		$all = $registry->all();
		$this->assertCount( 5, $all );
		$this->assertSame( DefaultPurpose::Personalization, $all[4] );
	}

	public function test_constructor_adds_each_purpose_from_the_iterable(): void {
		$registry = new PurposeRegistry(
			array(
				DefaultPurpose::Necessary,
				DefaultPurpose::Analytics,
			)
		);

		$this->assertSame(
			array(
				DefaultPurpose::Necessary,
				DefaultPurpose::Analytics,
			),
			$registry->all()
		);
	}

	public function test_add_appends_a_custom_purpose(): void {
		$registry = PurposeRegistry::with_defaults();
		$custom   = new CustomPurpose( 'crm' );

		$registry->add( $custom );

		$all = $registry->all();
		$this->assertCount( 5, $all );
		$this->assertSame( $custom, $all[4] );
	}

	public function test_add_dedupes_by_key(): void {
		$registry = new PurposeRegistry( array( DefaultPurpose::Analytics ) );
		$registry->add( new CustomPurpose( 'analytics', true ) );

		$all = $registry->all();
		$this->assertCount( 1, $all );
		$this->assertInstanceOf( CustomPurpose::class, $all[0] );
	}

	public function test_has_reports_membership_by_key(): void {
		$registry = new PurposeRegistry( array( DefaultPurpose::Necessary ) );

		$this->assertTrue( $registry->has( DefaultPurpose::Necessary ) );
		$this->assertFalse( $registry->has( DefaultPurpose::Marketing ) );
	}

	public function test_has_matches_a_custom_purpose_sharing_a_default_key(): void {
		$registry = PurposeRegistry::with_defaults();

		$this->assertTrue( $registry->has( new CustomPurpose( 'analytics' ) ) );
	}
}

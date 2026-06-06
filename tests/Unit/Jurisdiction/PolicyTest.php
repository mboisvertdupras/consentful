<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Jurisdiction;

use Consentful\Consent\CustomPurpose;
use Consentful\Consent\DefaultPurpose;
use Consentful\Jurisdiction\Policy;
use Consentful\Jurisdiction\PolicyType;
use PHPUnit\Framework\TestCase;

/**
 * Policy / PolicyType behavior per jurisdiction posture.
 */
final class PolicyTest extends TestCase {

	public function test_opt_in_denies_blocks_and_shows_banner(): void {
		$policy = Policy::opt_in( 1 );

		$this->assertSame( PolicyType::OptIn, $policy->type );
		$this->assertSame( 1, $policy->version );
		$this->assertTrue( $policy->denies_by_default() );
		$this->assertTrue( $policy->blocks_before_consent() );
		$this->assertTrue( $policy->shows_banner() );
	}

	public function test_opt_in_grants_nothing_non_essential(): void {
		$policy = Policy::opt_in( 1 );

		$this->assertSame( array(), $policy->default_granted );
		$this->assertFalse( $policy->grants_by_default( DefaultPurpose::Analytics ) );
		$this->assertFalse( $policy->grants_by_default( DefaultPurpose::Marketing ) );
	}

	public function test_opt_out_grants_default_list_shows_banner_does_not_block(): void {
		$policy = Policy::opt_out( 2, array( DefaultPurpose::Analytics, DefaultPurpose::Marketing ) );

		$this->assertSame( PolicyType::OptOut, $policy->type );
		$this->assertSame( 2, $policy->version );
		$this->assertFalse( $policy->denies_by_default() );
		$this->assertFalse( $policy->blocks_before_consent() );
		$this->assertTrue( $policy->shows_banner() );
		$this->assertTrue( $policy->grants_by_default( DefaultPurpose::Analytics ) );
		$this->assertTrue( $policy->grants_by_default( DefaultPurpose::Marketing ) );
		$this->assertFalse( $policy->grants_by_default( DefaultPurpose::Functional ) );
	}

	public function test_notice_only_hides_banner(): void {
		$policy = Policy::notice_only( 1, array() );

		$this->assertSame( PolicyType::NoticeOnly, $policy->type );
		$this->assertFalse( $policy->denies_by_default() );
		$this->assertFalse( $policy->blocks_before_consent() );
		$this->assertFalse( $policy->shows_banner() );
	}

	public function test_notice_only_grants_its_default_list_without_a_banner(): void {
		// NoticeOnly performs no gating: it informs without a banner and loads exactly the
		// purposes its default_granted lists (typically all non-essential).
		$policy = Policy::notice_only( 1, array( DefaultPurpose::Analytics, DefaultPurpose::Marketing ) );

		$this->assertFalse( $policy->shows_banner() );
		$this->assertFalse( $policy->blocks_before_consent() );
		$this->assertTrue( $policy->grants_by_default( DefaultPurpose::Analytics ) );
		$this->assertTrue( $policy->grants_by_default( DefaultPurpose::Marketing ) );
		$this->assertFalse( $policy->grants_by_default( DefaultPurpose::Functional ) );
	}

	public function test_grants_by_default_always_on_is_true(): void {
		$opt_in = Policy::opt_in( 1 );

		$this->assertTrue( DefaultPurpose::Necessary->is_always_on() );
		$this->assertTrue( $opt_in->grants_by_default( DefaultPurpose::Necessary ) );
	}

	public function test_grants_by_default_matches_by_key_not_identity(): void {
		$policy = Policy::opt_out( 1, array( DefaultPurpose::Analytics ) );

		// A distinct Purpose object sharing the 'analytics' key still matches.
		$this->assertTrue( $policy->grants_by_default( new CustomPurpose( 'analytics' ) ) );
	}

	public function test_policy_type_cases(): void {
		$this->assertSame(
			array( 'OptIn', 'OptOut', 'NoticeOnly' ),
			array_map(
				static fn ( PolicyType $type ): string => $type->name,
				PolicyType::cases()
			)
		);
	}
}

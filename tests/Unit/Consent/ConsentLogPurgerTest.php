<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Consent;

use Consentful\Consent\ConsentLogPurger;
use Consentful\Tests\Unit\Support\FakeWpdb;
use PHPUnit\Framework\TestCase;

final class ConsentLogPurgerTest extends TestCase {

	private const NOW = 1_700_000_000;

	private function purger( FakeWpdb $db ): ConsentLogPurger {
		return new ConsentLogPurger( $db, $db->prefix . 'consentful_consent_log' );
	}

	public function test_purge_deletes_old_records_and_returns_the_count(): void {
		$db               = FakeWpdb::create();
		$db->query_result = 7;

		$deleted = $this->purger( $db )->purge( 730, self::NOW );

		$this->assertSame( 7, $deleted );
		$this->assertCount( 1, $db->recorded_queries );
		$query = $db->recorded_queries[0];
		$this->assertStringContainsString( 'DELETE FROM', $query );
		$this->assertStringContainsString( 'wp_consentful_consent_log', $query );
		$this->assertStringContainsString( 'created_at <', $query );
	}

	public function test_cutoff_is_retention_days_before_now(): void {
		$db = FakeWpdb::create();

		$this->purger( $db )->purge( 10, self::NOW );

		$cutoff = gmdate( 'Y-m-d H:i:s', self::NOW - ( 10 * DAY_IN_SECONDS ) );
		$this->assertStringContainsString( $cutoff, $db->recorded_queries[0] );
	}

	public function test_non_positive_retention_keeps_records(): void {
		$db = FakeWpdb::create();

		$this->assertSame( 0, $this->purger( $db )->purge( 0, self::NOW ) );
		$this->assertSame( 0, $this->purger( $db )->purge( -30, self::NOW ) );
		$this->assertSame( array(), $db->recorded_queries );
	}
}

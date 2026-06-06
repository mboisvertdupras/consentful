<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Consent;

use Consentful\Consent\ConsentRecord;
use Consentful\Consent\DatabaseSink;
use Consentful\Tests\Unit\Support\FakeWpdb;
use PHPUnit\Framework\TestCase;

/**
 * DatabaseSink is the thin $wpdb shell: one prepared insert per record into the
 * prefixed table, with explicit %s/%d formats — never raw SQL interpolation. Exercised
 * with a fake wpdb that records the insert call.
 */
final class DatabaseSinkTest extends TestCase {

	private function record(): ConsentRecord {
		return new ConsentRecord(
			'cid-9',
			1733400000,
			'EU',
			1,
			1,
			1,
			array( 'necessary' => true ),
			'iphash',
			null,
		);
	}

	public function test_store_records_exactly_one_insert_with_row_and_formats(): void {
		$db   = FakeWpdb::create();
		$sink = new DatabaseSink( $db, 'wp_consentful_consent_log' );

		$sink->store( $this->record() );

		$this->assertCount( 1, $db->recorded_inserts );
		$insert = $db->recorded_inserts[0];
		$this->assertSame( 'wp_consentful_consent_log', $insert['table'] );
		$this->assertSame( $this->record()->to_row(), $insert['data'] );
		$this->assertSame(
			array( '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s' ),
			$insert['format']
		);
	}

	public function test_format_count_matches_the_row_column_count(): void {
		$db   = FakeWpdb::create();
		$sink = new DatabaseSink( $db, 'wp_consentful_consent_log' );

		$sink->store( $this->record() );

		$insert = $db->recorded_inserts[0];
		$this->assertIsArray( $insert['format'] );
		$this->assertCount( count( $insert['data'] ), $insert['format'] );
	}

	public function test_table_name_uses_the_wpdb_prefix(): void {
		$db = FakeWpdb::create( 'site7_' );

		$this->assertSame( 'site7_consentful_consent_log', DatabaseSink::table_name( $db ) );
	}
}

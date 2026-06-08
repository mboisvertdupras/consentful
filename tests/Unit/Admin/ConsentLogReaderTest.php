<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Admin;

use Consentful\Admin\ConsentLogReader;
use Consentful\Consent\ConsentLogSchema;
use Consentful\Tests\Unit\Support\FakeWpdb;
use PHPUnit\Framework\TestCase;

/**
 * ConsentLogReader is the thin $wpdb shell of the auditor surface: it binds the table as a
 * `%i` identifier and LIMIT/OFFSET as `%d` values via prepare (never interpolated), and its
 * pure `to_export_row` maps a stored row (DATETIME, JSON purposes, nullable hashes) to the
 * flat export shape. Exercised with a fake wpdb that records reads and returns seeded rows.
 */
final class ConsentLogReaderTest extends TestCase {

	/**
	 * @return array<string, mixed>
	 */
	private function row( string $cid = 'cid-1' ): array {
		return array(
			'id'             => 5,
			'consent_id'     => $cid,
			'created_at'     => '2024-12-05 12:00:00',
			'jurisdiction'   => 'US',
			'policy_version' => 1,
			'schema_version' => 1,
			'banner_version' => 1,
			'purposes'       => '{"necessary":true,"analytics":false}',
			'ip_hash'        => 'iphash',
			'ua_hash'        => null,
		);
	}

	public function test_count_returns_the_var_result(): void {
		$db             = FakeWpdb::create();
		$db->var_result = 42;
		$reader         = new ConsentLogReader( $db, 'wp_consentful_consent_log' );

		$this->assertSame( 42, $reader->count() );
		$this->assertStringContainsString( 'COUNT(*)', $db->recorded_reads[0] );
		$this->assertStringContainsString( 'wp_consentful_consent_log', $db->recorded_reads[0] );
	}

	public function test_recent_builds_the_prepared_limit_offset_query(): void {
		$db          = FakeWpdb::create();
		$db->results = array( $this->row() );
		$reader      = new ConsentLogReader( $db, 'wp_consentful_consent_log' );

		$reader->recent( 50, 100 );

		$query = $db->recorded_reads[0];
		$this->assertStringContainsString( 'ORDER BY created_at DESC, id DESC', $query );
		$this->assertStringContainsString( 'LIMIT 50 OFFSET 100', $query );
		$this->assertStringContainsString( 'wp_consentful_consent_log', $query );
	}

	public function test_recent_maps_rows_to_the_export_shape(): void {
		$db          = FakeWpdb::create();
		$db->results = array( $this->row() );
		$reader      = new ConsentLogReader( $db, 'wp_consentful_consent_log' );

		$rows = $reader->recent( 10, 0 );

		$this->assertCount( 1, $rows );
		$this->assertSame( 'cid-1', $rows[0]['consent_id'] );
		$this->assertSame( 'analytics=0;necessary=1', $rows[0]['purposes'] );
	}

	public function test_all_export_rows_maps_each_row(): void {
		$db          = FakeWpdb::create();
		$db->results = array( $this->row( 'a' ), $this->row( 'b' ) );
		$reader      = new ConsentLogReader( $db, 'wp_consentful_consent_log' );

		$rows = array();
		foreach ( $reader->all_export_rows() as $row ) {
			$rows[] = $row;
		}

		$this->assertCount( 2, $rows );
		$this->assertSame( 'a', $rows[0]['consent_id'] );
		$this->assertSame( 'b', $rows[1]['consent_id'] );
	}

	public function test_to_export_row_maps_a_db_row(): void {
		$export = ConsentLogReader::to_export_row( $this->row() );

		$this->assertSame(
			array(
				'consent_id'     => 'cid-1',
				'created_at'     => gmdate( 'c', (int) strtotime( '2024-12-05 12:00:00 UTC' ) ),
				'jurisdiction'   => 'US',
				'policy_version' => 1,
				'schema_version' => 1,
				'banner_version' => 1,
				'purposes'       => 'analytics=0;necessary=1',
				'ip_hash'        => 'iphash',
				'ua_hash'        => '',
			),
			$export
		);
	}

	public function test_to_export_row_handles_a_missing_or_unparseable_value(): void {
		$export = ConsentLogReader::to_export_row(
			array(
				'consent_id' => 'x',
				'created_at' => 'not-a-date',
				'purposes'   => 'not-json',
			)
		);

		$this->assertSame( 'x', $export['consent_id'] );
		// Unparseable timestamp passes through; bad JSON yields an empty purposes string.
		$this->assertSame( 'not-a-date', $export['created_at'] );
		$this->assertSame( '', $export['purposes'] );
		$this->assertSame( 0, $export['policy_version'] );
	}

	public function test_export_row_keys_match_the_schema_columns(): void {
		$export = ConsentLogReader::to_export_row( $this->row() );

		// The from-DB mapper follows the single column-contract owner's order.
		$this->assertSame( ConsentLogSchema::column_names(), array_keys( $export ) );
	}
}

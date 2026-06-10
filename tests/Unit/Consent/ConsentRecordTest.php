<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Consent;

use Consentful\Consent\ConsentLogSchema;
use Consentful\Consent\ConsentRecord;
use PHPUnit\Framework\TestCase;

final class ConsentRecordTest extends TestCase {

	private function record(
		?string $ip_hash = 'a1b2',
		?string $ua_hash = 'c3d4'
	): ConsentRecord {
		return new ConsentRecord(
			'cid-123',
			1733400000,
			'US',
			2,
			3,
			4,
			array(
				'necessary' => true,
				'analytics' => false,
			),
			$ip_hash,
			$ua_hash,
		);
	}

	public function test_to_row_formats_the_datetime_and_json_purposes(): void {
		$row = $this->record()->to_row();

		$this->assertSame( 'cid-123', $row['consent_id'] );
		$this->assertSame( gmdate( 'Y-m-d H:i:s', 1733400000 ), $row['created_at'] );
		$this->assertSame( 'US', $row['jurisdiction'] );
		$this->assertSame( 2, $row['policy_version'] );
		$this->assertSame( 3, $row['schema_version'] );
		$this->assertSame( 4, $row['banner_version'] );
		$this->assertSame( '{"necessary":true,"analytics":false}', $row['purposes'] );
		$this->assertSame( 'a1b2', $row['ip_hash'] );
		$this->assertSame( 'c3d4', $row['ua_hash'] );
	}

	public function test_to_row_keeps_null_hashes_as_null(): void {
		$row = $this->record( null, null )->to_row();

		$this->assertNull( $row['ip_hash'] );
		$this->assertNull( $row['ua_hash'] );
	}

	public function test_to_row_never_carries_raw_pii(): void {
		$row = $this->record( 'hashed-ip', 'hashed-ua' )->to_row();

		$this->assertArrayNotHasKey( 'ip', $row );
		$this->assertArrayNotHasKey( 'ua', $row );
		$this->assertArrayNotHasKey( 'remote_addr', $row );
		$this->assertArrayNotHasKey( 'user_agent', $row );
	}

	public function test_to_export_row_uses_iso_8601_and_sorted_purposes(): void {
		$row = $this->record()->to_export_row();

		$this->assertSame( gmdate( 'c', 1733400000 ), $row['created_at'] );
		$this->assertSame( 'analytics=0;necessary=1', $row['purposes'] );
		$this->assertSame( 'a1b2', $row['ip_hash'] );
	}

	public function test_to_export_row_renders_absent_hashes_as_empty_string(): void {
		$row = $this->record( null, null )->to_export_row();

		$this->assertSame( '', $row['ip_hash'] );
		$this->assertSame( '', $row['ua_hash'] );
	}

	public function test_to_export_row_keys_are_stable(): void {
		$row = $this->record()->to_export_row();

		$this->assertSame(
			array(
				'consent_id',
				'created_at',
				'jurisdiction',
				'policy_version',
				'schema_version',
				'banner_version',
				'purposes',
				'ip_hash',
				'ua_hash',
			),
			array_keys( $row )
		);
	}

	public function test_row_shapes_follow_the_schema_column_order(): void {
		$record = $this->record();

		$this->assertSame( ConsentLogSchema::column_names(), array_keys( $record->to_row() ) );
		$this->assertSame( ConsentLogSchema::column_names(), array_keys( $record->to_export_row() ) );
	}
}

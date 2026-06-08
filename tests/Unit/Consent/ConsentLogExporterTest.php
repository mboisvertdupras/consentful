<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Consent;

use Consentful\Consent\ConsentLogExporter;
use Consentful\Consent\ConsentLogSchema;
use Consentful\Consent\ConsentRecord;
use PHPUnit\Framework\TestCase;

/**
 * The pure CSV builder: a header row plus one row per record, RFC-4180 quoted so the
 * output round-trips through a compliant reader (here, str_getcsv).
 */
final class ConsentLogExporterTest extends TestCase {

	private function record( string $cid = 'cid-1', string $jurisdiction = 'US' ): ConsentRecord {
		return new ConsentRecord(
			$cid,
			1733400000,
			$jurisdiction,
			1,
			1,
			1,
			array(
				'necessary' => true,
				'analytics' => false,
			),
			'iphash',
			'uahash',
		);
	}

	public function test_header_row_lists_every_column(): void {
		$csv  = ConsentLogExporter::to_csv( array() );
		$rows = $this->parse( $csv );

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
			$rows[0]
		);
		// The header is the schema's column order, not a re-listed copy.
		$this->assertSame( ConsentLogSchema::column_names(), $rows[0] );
	}

	public function test_one_row_per_record(): void {
		$csv  = ConsentLogExporter::to_csv( array( $this->record( 'a' ), $this->record( 'b' ) ) );
		$rows = $this->parse( $csv );

		$this->assertCount( 3, $rows );
		$this->assertSame( 'a', $rows[1][0] );
		$this->assertSame( 'b', $rows[2][0] );
	}

	public function test_record_values_round_trip(): void {
		$csv  = ConsentLogExporter::to_csv( array( $this->record() ) );
		$rows = $this->parse( $csv );

		$this->assertSame(
			array(
				'cid-1',
				gmdate( 'c', 1733400000 ),
				'US',
				'1',
				'1',
				'1',
				'analytics=0;necessary=1',
				'iphash',
				'uahash',
			),
			$rows[1]
		);
	}

	public function test_embedded_quotes_and_commas_are_escaped_and_round_trip(): void {
		// A pre-built export row with characters that must be RFC-4180 quoted.
		$row = array(
			'consent_id'     => 'cid,"x"',
			'created_at'     => '2024-12-05T12:00:00+00:00',
			'jurisdiction'   => 'US',
			'policy_version' => 1,
			'schema_version' => 1,
			'banner_version' => 1,
			'purposes'       => 'necessary=1',
			'ip_hash'        => '',
			'ua_hash'        => '',
		);

		$csv  = ConsentLogExporter::to_csv( array( $row ) );
		$rows = $this->parse( $csv );

		$this->assertSame( 'cid,"x"', $rows[1][0] );
	}

	public function test_formula_injection_triggers_are_neutralized(): void {
		// Grant keys reach the log via the public consent endpoint, so a value beginning with
		// a formula trigger (=, +, -, @, tab, CR) must be prefixed with a single quote so the
		// auditor's spreadsheet does not execute it as a formula (CWE-1236).
		$row = array(
			'consent_id'     => 'cid',
			'created_at'     => '2024-12-05T12:00:00+00:00',
			'jurisdiction'   => 'US',
			'policy_version' => 1,
			'schema_version' => 1,
			'banner_version' => 1,
			'purposes'       => '=cmd|calc!A1=1',
			'ip_hash'        => '',
			'ua_hash'        => '',
		);

		$rows = $this->parse( ConsentLogExporter::to_csv( array( $row ) ) );

		// The dangerous leading '=' is now an inert literal, prefixed with a single quote.
		$this->assertSame( "'=cmd|calc!A1=1", $rows[1][6] );
	}

	public function test_accepts_a_generator_of_records(): void {
		$gen = ( static function () {
			yield ( new ConsentRecord( 'g1', 1733400000, 'EU', 1, 1, 1, array( 'necessary' => true ), null, null ) );
		} )();

		$rows = $this->parse( ConsentLogExporter::to_csv( $gen ) );

		$this->assertCount( 2, $rows );
		$this->assertSame( 'g1', $rows[1][0] );
	}

	/**
	 * Parse the CSV back into rows with the standard library reader.
	 *
	 * @return list<list<string>>
	 */
	private function parse( string $csv ): array {
		$rows = array();
		foreach ( explode( "\r\n", rtrim( $csv, "\r\n" ) ) as $line ) {
			$fields = array_map(
				static fn ( ?string $field ): string => (string) $field,
				str_getcsv( $line, ',', '"', '\\' )
			);
			$rows[] = $fields;
		}
		return $rows;
	}
}

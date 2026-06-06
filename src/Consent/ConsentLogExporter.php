<?php
declare( strict_types = 1 );

namespace Consentful\Consent;

/**
 * Builds an auditor-facing CSV from Consent records. Pure and unit-tested — it drives
 * both the WP-CLI export command and (in a later increment) the admin export screen.
 * Each input item is a ConsentRecord or its `to_export_row()` array. Rows are built as
 * a plain string with RFC-4180 quoting (no filesystem stream needed), so the output
 * round-trips through any compliant CSV reader.
 */
final class ConsentLogExporter {

	/**
	 * The fixed export header, matching ConsentRecord::to_export_row() key order.
	 *
	 * @var list<string>
	 */
	private const HEADER = array(
		'consent_id',
		'created_at',
		'jurisdiction',
		'policy_version',
		'schema_version',
		'banner_version',
		'purposes',
		'ip_hash',
		'ua_hash',
	);

	/**
	 * A header row plus one row per record, CRLF-terminated per RFC-4180. Accepts
	 * ConsentRecords or pre-built export rows.
	 *
	 * @param iterable<ConsentRecord|array<string, scalar>> $records
	 */
	public static function to_csv( iterable $records ): string {
		$lines = array( self::line( self::HEADER ) );
		foreach ( $records as $record ) {
			$row     = $record instanceof ConsentRecord ? $record->to_export_row() : $record;
			$lines[] = self::line( self::row_values( $row ) );
		}
		return implode( "\r\n", $lines ) . "\r\n";
	}

	/**
	 * The export-row values in header order, each coerced to string.
	 *
	 * @param array<string, scalar> $row
	 * @return list<string>
	 */
	private static function row_values( array $row ): array {
		$values = array();
		foreach ( self::HEADER as $column ) {
			$values[] = (string) ( $row[ $column ] ?? '' );
		}
		return $values;
	}

	/**
	 * One RFC-4180 record: fields joined by commas, each quoted with embedded quotes
	 * doubled.
	 *
	 * @param list<string> $fields
	 */
	private static function line( array $fields ): string {
		return implode( ',', array_map( self::quote( ... ), $fields ) );
	}

	/** RFC-4180 field: wrap in double quotes, doubling any embedded quote. */
	private static function quote( string $field ): string {
		return '"' . str_replace( '"', '""', $field ) . '"';
	}
}

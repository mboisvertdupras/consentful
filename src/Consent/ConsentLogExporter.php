<?php
declare( strict_types = 1 );

namespace Consentful\Consent;

final class ConsentLogExporter {

	/**
	 * @param iterable<ConsentRecord|array<string, scalar>> $records
	 */
	public static function to_csv( iterable $records ): string {
		$lines = array( self::line( ConsentLogSchema::column_names() ) );
		foreach ( $records as $record ) {
			$row     = $record instanceof ConsentRecord ? $record->to_export_row() : $record;
			$lines[] = self::line( self::row_values( $row ) );
		}
		return implode( "\r\n", $lines ) . "\r\n";
	}

	/**
	 * @param array<string, scalar> $row
	 * @return list<string>
	 */
	private static function row_values( array $row ): array {
		$values = array();
		foreach ( ConsentLogSchema::column_names() as $column ) {
			$values[] = (string) ( $row[ $column ] ?? '' );
		}
		return $values;
	}

	/**
	 * @param list<string> $fields
	 */
	private static function line( array $fields ): string {
		return implode( ',', array_map( self::quote( ... ), $fields ) );
	}

	private static function quote( string $field ): string {
		if ( '' !== $field && str_contains( "=+-@\t\r", $field[0] ) ) {
			$field = "'" . $field;
		}
		return '"' . str_replace( '"', '""', $field ) . '"';
	}
}

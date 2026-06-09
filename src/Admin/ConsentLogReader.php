<?php
declare( strict_types = 1 );

namespace Consentful\Admin;

use Consentful\Consent\ConsentLogSchema;

final class ConsentLogReader {

	public function __construct(
		private readonly \wpdb $db,
		private readonly string $table,
	) {}

	public static function for_wp(): self {
		global $wpdb;
		/** @var \wpdb $wpdb */
		return new self( $wpdb, ConsentLogSchema::table( $wpdb ) );
	}

	public function count(): int {
		return (int) $this->db->get_var(
			$this->db->prepare( 'SELECT COUNT(*) FROM %i', $this->table )
		);
	}

	/** @return list<array<string, scalar>> */
	public function recent( int $limit, int $offset ): array {
		$rows = $this->db->get_results(
			$this->db->prepare(
				'SELECT * FROM %i ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d',
				$this->table,
				$limit,
				$offset
			),
			ARRAY_A
		);

		return $this->map_rows( $rows );
	}

	/** @return iterable<array<string, scalar>> */
	public function all_export_rows(): iterable {
		$rows = $this->db->get_results(
			$this->db->prepare( 'SELECT * FROM %i ORDER BY created_at DESC, id DESC', $this->table ),
			ARRAY_A
		);

		return $this->map_rows( $rows );
	}

	/** @return list<array<string, scalar>> */
	private function map_rows( mixed $rows ): array {
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$out = array();
		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				$out[] = self::to_export_row( $row );
			}
		}
		return $out;
	}

	/**
	 * @param array<array-key, mixed> $row
	 * @return array<string, scalar>
	 */
	public static function to_export_row( array $row ): array {
		return array(
			'consent_id'     => self::str( $row, 'consent_id' ),
			'created_at'     => self::iso8601( self::str( $row, 'created_at' ) ),
			'jurisdiction'   => self::str( $row, 'jurisdiction' ),
			'policy_version' => self::int( $row, 'policy_version' ),
			'schema_version' => self::int( $row, 'schema_version' ),
			'banner_version' => self::int( $row, 'banner_version' ),
			'purposes'       => self::purposes_string( self::str( $row, 'purposes' ) ),
			'ip_hash'        => self::str( $row, 'ip_hash' ),
			'ua_hash'        => self::str( $row, 'ua_hash' ),
		);
	}

	/** @param array<array-key, mixed> $row */
	private static function str( array $row, string $column ): string {
		$value = $row[ $column ] ?? '';
		return is_scalar( $value ) ? (string) $value : '';
	}

	/** @param array<array-key, mixed> $row */
	private static function int( array $row, string $column ): int {
		$value = $row[ $column ] ?? 0;
		return is_numeric( $value ) ? (int) $value : 0;
	}

	private static function iso8601( string $datetime ): string {
		$timestamp = strtotime( $datetime . ' UTC' );
		return false === $timestamp ? $datetime : ConsentLogSchema::export_timestamp( $timestamp );
	}

	private static function purposes_string( string $json ): string {
		$decoded = json_decode( $json, true );
		return is_array( $decoded ) ? ConsentLogSchema::purposes_to_export( $decoded ) : '';
	}
}

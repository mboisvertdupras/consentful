<?php
declare( strict_types = 1 );

namespace Consentful\Admin;

use Consentful\Consent\ConsentLogSchema;

/**
 * Reads the bundled Consent log table for the admin screen and the CSV export — the thin
 * `$wpdb` shell of the auditor surface. The injected wpdb is the only WordPress coupling;
 * the table is bound as a `%i` identifier and LIMIT/OFFSET as `%d` values via
 * `$wpdb->prepare`, so the query string stays a literal and nothing is interpolated.
 *
 * The DB-row → export-row mapping (`to_export_row`) is pure and unit-tested: it converts a
 * stored row (DATETIME `created_at`, JSON `purposes`, nullable hashes) into the flat
 * export shape the ConsentLogExporter consumes.
 */
final class ConsentLogReader {

	public function __construct(
		private readonly \wpdb $db,
		private readonly string $table,
	) {}

	/** Build from the global wpdb and the prefix-derived Consent log table name. */
	public static function for_wp(): self {
		global $wpdb;
		/** @var \wpdb $wpdb */
		return new self( $wpdb, ConsentLogSchema::table( $wpdb ) );
	}

	/** Total record count. The table is bound as a `%i` identifier. */
	public function count(): int {
		return (int) $this->db->get_var(
			$this->db->prepare( 'SELECT COUNT(*) FROM %i', $this->table )
		);
	}

	/**
	 * A page of records, newest first. The table is a `%i` identifier and LIMIT/OFFSET are
	 * `%d` values — all bound via prepare, never interpolated.
	 *
	 * @return list<array<string, scalar>>
	 */
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

	/**
	 * Every row mapped to the export shape, for streaming to the ConsentLogExporter.
	 *
	 * @return iterable<array<string, scalar>>
	 */
	public function all_export_rows(): iterable {
		$rows = $this->db->get_results(
			$this->db->prepare( 'SELECT * FROM %i ORDER BY created_at DESC, id DESC', $this->table ),
			ARRAY_A
		);

		return $this->map_rows( $rows );
	}

	/**
	 * Map raw `get_results( ARRAY_A )` output (mixed) to a list of export rows.
	 *
	 * @param mixed $rows
	 * @return list<array<string, scalar>>
	 */
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
	 * Pure DB-row → export-row mapping: DATETIME `created_at` → ISO-8601 UTC, JSON
	 * `purposes` → the stable `key=0/1;…` string, nullable hashes → ''. Mirrors
	 * ConsentRecord::to_export_row()'s column order so the exporter quotes them in order.
	 *
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

	/**
	 * A scalar-safe string read of a column (non-scalar / absent → '').
	 *
	 * @param array<array-key, mixed> $row
	 */
	private static function str( array $row, string $column ): string {
		$value = $row[ $column ] ?? '';
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * A scalar-safe int read of a column (non-numeric / absent → 0).
	 *
	 * @param array<array-key, mixed> $row
	 */
	private static function int( array $row, string $column ): int {
		$value = $row[ $column ] ?? 0;
		return is_numeric( $value ) ? (int) $value : 0;
	}

	/** Convert a stored DATETIME to the schema's ISO-8601 UTC export format; pass through if unparseable. */
	private static function iso8601( string $datetime ): string {
		$timestamp = strtotime( $datetime . ' UTC' );
		return false === $timestamp ? $datetime : ConsentLogSchema::export_timestamp( $timestamp );
	}

	/** Decode the stored purposes JSON, then encode it via the schema's shared export codec. */
	private static function purposes_string( string $json ): string {
		$decoded = json_decode( $json, true );
		return is_array( $decoded ) ? ConsentLogSchema::purposes_to_export( $decoded ) : '';
	}
}

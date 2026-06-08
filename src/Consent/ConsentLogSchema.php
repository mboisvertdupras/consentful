<?php
declare( strict_types = 1 );

namespace Consentful\Consent;

/**
 * The single owner of the Consent log table contract: its name, its ordered column
 * set (each column's DDL type and `$wpdb` printf format), and the codecs that encode a
 * record's purposes/timestamp for export. The DDL, the DatabaseSink format map, the
 * exporter header, both export-row mappers and the purger all derive from here, so a
 * column is added or renamed in ONE place instead of by hand-mirrored restatement.
 *
 * `create_table_sql` is pure (it builds the CREATE TABLE string from a passed table
 * name + charset, so it is tested for shape without WordPress); `create`/`drop`/`table`
 * confine the `$wpdb` coupling. The table name comes from the prefix, never user input,
 * so the DROP needs no escaping.
 */
final class ConsentLogSchema {

	private const TABLE = 'consentful_consent_log';

	/**
	 * The canonical record columns, in storage/export order: name => DDL type + `$wpdb`
	 * printf format. The `id` auto-increment PRIMARY KEY and the secondary KEYs are
	 * DDL-structural (declared in `create_table_sql`), not record columns, so they are
	 * not listed here.
	 *
	 * @var array<string, array{ddl: string, format: string}>
	 */
	private const COLUMNS = array(
		'consent_id'     => array(
			'ddl'    => 'VARCHAR(64) NOT NULL',
			'format' => '%s',
		),
		'created_at'     => array(
			'ddl'    => 'DATETIME NOT NULL',
			'format' => '%s',
		),
		'jurisdiction'   => array(
			'ddl'    => 'VARCHAR(16) NOT NULL',
			'format' => '%s',
		),
		'policy_version' => array(
			'ddl'    => 'SMALLINT UNSIGNED NOT NULL',
			'format' => '%d',
		),
		'schema_version' => array(
			'ddl'    => 'SMALLINT UNSIGNED NOT NULL',
			'format' => '%d',
		),
		'banner_version' => array(
			'ddl'    => 'SMALLINT UNSIGNED NOT NULL',
			'format' => '%d',
		),
		'purposes'       => array(
			'ddl'    => 'TEXT NOT NULL',
			'format' => '%s',
		),
		'ip_hash'        => array(
			'ddl'    => 'CHAR(64) NULL',
			'format' => '%s',
		),
		'ua_hash'        => array(
			'ddl'    => 'CHAR(64) NULL',
			'format' => '%s',
		),
	);

	/**
	 * The record column names in canonical order. The exporter header, both export-row
	 * mappers and the insert row follow this order.
	 *
	 * @return list<string>
	 */
	public static function column_names(): array {
		return array_keys( self::COLUMNS );
	}

	/**
	 * The `$wpdb->insert` printf formats in column order — paired positionally with
	 * ConsentRecord::to_row().
	 *
	 * @return list<string>
	 */
	public static function insert_formats(): array {
		return array_column( self::COLUMNS, 'format' );
	}

	/**
	 * The CREATE TABLE statement in dbDelta's expected layout (two spaces after the
	 * PRIMARY KEY, lower-case `key`). The column lines are generated from COLUMNS; the
	 * `id` PRIMARY KEY and the two secondary KEYs are structural. Pure — driven only by
	 * the passed table name and charset/collation.
	 */
	public static function create_table_sql( string $table, string $charset_collate ): string {
		$lines = array( 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT' );
		foreach ( self::COLUMNS as $name => $column ) {
			$lines[] = $name . ' ' . $column['ddl'];
		}
		$lines[] = 'PRIMARY KEY  (id)';
		$lines[] = 'KEY consent_id (consent_id)';
		$lines[] = 'KEY created_at (created_at)';

		return "CREATE TABLE {$table} (\n\t\t\t" . implode( ",\n\t\t\t", $lines ) . "\n\t\t) {$charset_collate};";
	}

	/**
	 * The stable `key=0/1;…` purposes encoding for export, sorted by key for determinism.
	 * Shared by the in-memory record path (ConsentRecord, an `array<string, bool>`) and the
	 * from-DB reader path (ConsentLogReader, the decoded stored JSON) so the two cannot
	 * drift; each value is read for truthiness, so either input shape encodes identically.
	 *
	 * @param array<array-key, mixed> $purposes Purpose key → granted (truthy).
	 */
	public static function purposes_to_export( array $purposes ): string {
		ksort( $purposes );
		$parts = array();
		foreach ( $purposes as $key => $granted ) {
			$parts[] = $key . '=' . ( $granted ? '1' : '0' );
		}
		return implode( ';', $parts );
	}

	/** The export timestamp format: ISO-8601 UTC from an epoch second. */
	public static function export_timestamp( int $epoch ): string {
		return gmdate( 'c', $epoch );
	}

	/** Create (or migrate) the table via dbDelta. Confines the `$wpdb` coupling here. */
	public static function create( \wpdb $db ): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( self::create_table_sql( self::table( $db ), $db->get_charset_collate() ) );
	}

	/** Drop the table (used by uninstall). */
	public static function drop( \wpdb $db ): void {
		$db->query( 'DROP TABLE IF EXISTS ' . self::table( $db ) );
	}

	/** The prefixed table name — the single owner of the Consent log table identity. */
	public static function table( \wpdb $db ): string {
		return $db->prefix . self::TABLE;
	}
}

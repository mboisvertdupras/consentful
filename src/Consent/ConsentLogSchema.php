<?php
declare( strict_types = 1 );

namespace Consentful\Consent;

final class ConsentLogSchema {

	private const TABLE = 'consentful_consent_log';

	/** @var array<string, array{ddl: string, format: string}> */
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

	/** @return list<string> */
	public static function column_names(): array {
		return array_keys( self::COLUMNS );
	}

	/** @return list<string> */
	public static function insert_formats(): array {
		return array_column( self::COLUMNS, 'format' );
	}

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

	/** @param array<array-key, mixed> $purposes */
	public static function purposes_to_export( array $purposes ): string {
		ksort( $purposes );
		$parts = array();
		foreach ( $purposes as $key => $granted ) {
			$parts[] = $key . '=' . ( $granted ? '1' : '0' );
		}
		return implode( ';', $parts );
	}

	public static function export_timestamp( int $epoch ): string {
		return gmdate( 'c', $epoch );
	}

	public static function create( \wpdb $db ): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( self::create_table_sql( self::table( $db ), $db->get_charset_collate() ) );
	}

	public static function drop( \wpdb $db ): void {
		$db->query( 'DROP TABLE IF EXISTS ' . self::table( $db ) );
	}

	public static function table( \wpdb $db ): string {
		return $db->prefix . self::TABLE;
	}
}

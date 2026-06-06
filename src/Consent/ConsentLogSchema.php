<?php
declare( strict_types = 1 );

namespace Consentful\Consent;

/**
 * The Consent log table DDL — a thin schema shell. `create_table_sql` is pure (it
 * builds the CREATE TABLE string from a passed table name + charset, so it is tested
 * for shape without WordPress); `create`/`drop` are the dbDelta / DROP WP-entry points
 * that confine the `$wpdb` coupling here. The table name comes from the prefix, never
 * user input, so the DROP needs no escaping.
 */
final class ConsentLogSchema {

	private const TABLE = 'consentful_consent_log';

	/**
	 * The CREATE TABLE statement in dbDelta's expected layout (two spaces after a
	 * column type, lower-case `key`). Pure — driven only by the passed table name and
	 * charset/collation.
	 */
	public static function create_table_sql( string $table, string $charset_collate ): string {
		return "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			consent_id VARCHAR(64) NOT NULL,
			created_at DATETIME NOT NULL,
			jurisdiction VARCHAR(16) NOT NULL,
			policy_version SMALLINT UNSIGNED NOT NULL,
			schema_version SMALLINT UNSIGNED NOT NULL,
			banner_version SMALLINT UNSIGNED NOT NULL,
			purposes TEXT NOT NULL,
			ip_hash CHAR(64) NULL,
			ua_hash CHAR(64) NULL,
			PRIMARY KEY  (id),
			KEY consent_id (consent_id),
			KEY created_at (created_at)
		) {$charset_collate};";
	}

	/** Create (or migrate) the table via dbDelta. Confines the `$wpdb` coupling here. */
	public static function create( \wpdb $db ): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( self::create_table_sql( self::table( $db ), $db->get_charset_collate() ) );
	}

	/** Drop the table (used by uninstall in a later increment). */
	public static function drop( \wpdb $db ): void {
		$db->query( 'DROP TABLE IF EXISTS ' . self::table( $db ) );
	}

	/** The prefixed table name. */
	public static function table( \wpdb $db ): string {
		return $db->prefix . self::TABLE;
	}
}

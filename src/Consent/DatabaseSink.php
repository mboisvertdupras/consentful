<?php
declare( strict_types = 1 );

namespace Consentful\Consent;

/**
 * The built-in Sink: writes a Consent record to the bundled Consent log table. A thin
 * `$wpdb` shell — the only WordPress coupling is the injected wpdb. The record, its
 * row shape and the format map are pure; this class just hands them to
 * `$wpdb->insert`, which prepares values against the explicit `%s`/`%d` formats so
 * nothing is interpolated into SQL.
 *
 * The wpdb instance and table name are injected (not `global $wpdb`) so the class is
 * unit-testable with a fake wpdb.
 */
final class DatabaseSink implements Sink {

	/**
	 * Column → printf-style placeholder for `$wpdb->insert`. Order matches
	 * ConsentRecord::to_row(); nullable hashes are still `%s` (a null value stays null).
	 *
	 * @var array<string, string>
	 */
	private const FORMATS = array(
		'consent_id'     => '%s',
		'created_at'     => '%s',
		'jurisdiction'   => '%s',
		'policy_version' => '%d',
		'schema_version' => '%d',
		'banner_version' => '%d',
		'purposes'       => '%s',
		'ip_hash'        => '%s',
		'ua_hash'        => '%s',
	);

	public function __construct(
		private readonly \wpdb $db,
		private readonly string $table,
	) {}

	/** Insert the record with explicit `%s`/`%d` formats — never raw SQL interpolation. */
	public function store( ConsentRecord $record ): void {
		$this->db->insert( $this->table, $record->to_row(), array_values( self::FORMATS ) );
	}

	/** The prefixed Consent log table name (from the wpdb prefix, never user input). */
	public static function table_name( \wpdb $db ): string {
		return $db->prefix . 'consentful_consent_log';
	}
}

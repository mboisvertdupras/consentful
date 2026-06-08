<?php
declare( strict_types = 1 );

namespace Consentful\Consent;

/**
 * The built-in Sink: writes a Consent record to the bundled Consent log table. A thin
 * `$wpdb` shell — the only WordPress coupling is the injected wpdb. The record's row
 * shape and the `%s`/`%d` format map both come from ConsentLogSchema (the single owner
 * of the column contract); this class just hands them to `$wpdb->insert`, which prepares
 * values against those explicit formats so nothing is interpolated into SQL.
 *
 * The wpdb instance and table name are injected (not `global $wpdb`) so the class is
 * unit-testable with a fake wpdb.
 */
final class DatabaseSink implements Sink {

	public function __construct(
		private readonly \wpdb $db,
		private readonly string $table,
	) {}

	/** Insert the record with explicit `%s`/`%d` formats — never raw SQL interpolation. */
	public function store( ConsentRecord $record ): void {
		$this->db->insert( $this->table, $record->to_row(), ConsentLogSchema::insert_formats() );
	}
}

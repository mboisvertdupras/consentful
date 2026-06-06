<?php
declare( strict_types = 1 );

namespace Consentful\Consent;

/**
 * Enforces the Consent log retention limit (ADR 0002: "retention limits ... are part of the
 * design, not an add-on"). Deletes records older than the retention window. A thin $wpdb
 * shell — the cutoff is computed from server time and the DELETE is prepared (table as an
 * `%i` identifier, cutoff as a `%s` value), so nothing is interpolated. The wpdb instance
 * and table are injected so it is unit-testable with a fake wpdb.
 */
final class ConsentLogPurger {

	public function __construct(
		private readonly \wpdb $db,
		private readonly string $table,
	) {}

	/**
	 * Delete records created more than `$retention_days` before `$now` (epoch seconds). A
	 * non-positive window keeps records indefinitely (returns 0). Returns the rows deleted.
	 */
	public function purge( int $retention_days, int $now ): int {
		if ( $retention_days <= 0 ) {
			return 0;
		}

		$cutoff = gmdate( 'Y-m-d H:i:s', $now - ( $retention_days * DAY_IN_SECONDS ) );

		$sql = $this->db->prepare( 'DELETE FROM %i WHERE created_at < %s', $this->table, $cutoff );
		if ( ! is_string( $sql ) ) {
			return 0;
		}

		$deleted = $this->db->query( $sql );
		return is_int( $deleted ) ? $deleted : 0;
	}
}

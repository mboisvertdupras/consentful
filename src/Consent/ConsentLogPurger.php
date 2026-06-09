<?php
declare( strict_types = 1 );

namespace Consentful\Consent;

final class ConsentLogPurger {

	public function __construct(
		private readonly \wpdb $db,
		private readonly string $table,
	) {}

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

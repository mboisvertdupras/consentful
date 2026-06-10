<?php
declare( strict_types = 1 );

namespace Consentful\Consent;

final class DatabaseSink implements Sink {

	public function __construct(
		private readonly \wpdb $db,
		private readonly string $table,
	) {}

	public function store( ConsentRecord $record ): void {
		$this->db->insert( $this->table, $record->to_row(), ConsentLogSchema::insert_formats() );
	}
}

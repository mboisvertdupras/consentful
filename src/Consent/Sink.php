<?php
declare( strict_types = 1 );

namespace Consentful\Consent;

/**
 * The destination a Consent record is written to. The built-in default is
 * DatabaseSink (the bundled Consent log table); a developer may redirect records to
 * their own store via the `consentful_sink` filter (per ADR 0002). The REST controller
 * depends only on this interface.
 */
interface Sink {

	/** Persist a single proof-of-consent record. */
	public function store( ConsentRecord $record ): void;
}

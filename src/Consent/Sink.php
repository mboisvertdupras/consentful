<?php
declare( strict_types = 1 );

namespace Consentful\Consent;

/**
 * The destination a Consent record is written to. The built-in default is
 * DatabaseSink (the bundled Consent log table); an Integrator may bind their own
 * implementation in `consentful_register` to redirect records to an external store
 * (per ADR 0002). The REST controller depends only on this interface.
 */
interface Sink {

	/** Persist a single proof-of-consent record. */
	public function store( ConsentRecord $record ): void;
}

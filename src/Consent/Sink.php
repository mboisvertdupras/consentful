<?php
declare( strict_types = 1 );

namespace Consentful\Consent;

interface Sink {

	public function store( ConsentRecord $record ): void;
}

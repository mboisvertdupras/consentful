<?php
declare( strict_types = 1 );

namespace Consentful\Consent;

interface Purpose {

	public function key(): string;

	public function is_always_on(): bool;
}

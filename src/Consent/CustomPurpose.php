<?php
declare( strict_types = 1 );

namespace Consentful\Consent;

final class CustomPurpose implements Purpose {

	public function __construct(
		private readonly string $key,
		private readonly bool $always_on = false,
	) {}

	public function key(): string {
		return $this->key;
	}

	public function is_always_on(): bool {
		return $this->always_on;
	}
}

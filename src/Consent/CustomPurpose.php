<?php
declare( strict_types = 1 );

namespace Consentful\Consent;

/**
 * An integrator-defined Purpose, beyond the default taxonomy. Lets the source of
 * truth (code/config) add purposes the enum does not ship.
 */
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

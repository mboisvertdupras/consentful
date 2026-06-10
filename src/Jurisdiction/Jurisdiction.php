<?php
declare( strict_types = 1 );

namespace Consentful\Jurisdiction;

final class Jurisdiction {

	public function __construct(
		public readonly string $id,
		public readonly string $label,
		public readonly Policy $policy,
	) {}
}

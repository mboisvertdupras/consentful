<?php
declare( strict_types = 1 );

namespace Consentful\Jurisdiction;

/**
 * A region resolved per Visitor (e.g. 'QC', 'EU', 'US', '*') paired with the Policy
 * it selects. The id is the stable key used by the JurisdictionRegistry.
 */
final class Jurisdiction {

	public function __construct(
		public readonly string $id,
		public readonly string $label,
		public readonly Policy $policy,
	) {}
}

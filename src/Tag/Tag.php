<?php
declare( strict_types = 1 );

namespace Consentful\Tag;

use Consentful\Consent\Purpose;

final class Tag {

	/**
	 * @param list<Purpose> $purposes
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $label,
		public readonly array $purposes,
		public readonly Delivery $delivery,
		public readonly string $adapter_id,
	) {}
}

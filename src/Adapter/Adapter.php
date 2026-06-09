<?php
declare( strict_types = 1 );

namespace Consentful\Adapter;

use Consentful\Tag\Tag;

interface Adapter {

	public function id(): string;

	public function handles( Tag $tag ): bool;

	/** @return array<string, mixed> */
	public function client_config(): array;
}

<?php
declare( strict_types = 1 );

namespace Consentful\Adapter;

use Consentful\Tag\Tag;

/**
 * Knows how to load a Tag on the client. Vendor-neutral — the core hard-codes
 * nothing; Google is just one adapter. A Tag references an adapter by its stable
 * `id()`. Purpose-gating lives on the Tag, not the adapter (single source of truth).
 */
interface Adapter {

	/** Stable id a Tag references. */
	public function id(): string;

	/** Whether this adapter loads the given Tag. */
	public function handles( Tag $tag ): bool;

	/**
	 * JSON-serializable config handed to the client gate.
	 *
	 * @return array<string, mixed>
	 */
	public function client_config(): array;
}

<?php
declare( strict_types = 1 );

namespace Consentful\Adapter;

use Consentful\Tag\Tag;

/**
 * A generic Adapter built from a pre-computed client-config array. The hydrator uses it
 * for `gtm` and each `script` instance (Google keeps its own GoogleAdapter so the
 * Signal map stays authoritative). The id is the instance id a Tag references; the
 * client config carries the `handler` field the gate resolves on.
 */
final class ConfiguredAdapter implements Adapter {

	/**
	 * @param array<string, mixed> $client_config The verbatim §2 handler shape (must carry `handler`).
	 */
	public function __construct(
		private readonly string $id,
		private readonly array $client_config,
	) {}

	public function id(): string {
		return $this->id;
	}

	public function handles( Tag $tag ): bool {
		return $this->id === $tag->adapter_id;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function client_config(): array {
		return $this->client_config;
	}
}

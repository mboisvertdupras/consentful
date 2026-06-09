<?php
declare( strict_types = 1 );

namespace Consentful\Adapter;

final class ConfiguredAdapter implements Adapter {

	/**
	 * @param array<string, mixed> $client_config
	 */
	public function __construct(
		private readonly string $id,
		private readonly array $client_config,
	) {}

	public function id(): string {
		return $this->id;
	}

	/** @return array<string, mixed> */
	public function client_config(): array {
		return $this->client_config;
	}
}

<?php
declare( strict_types = 1 );

namespace Consentful\Adapter;

/**
 * The active set of adapters, keyed by their stable id.
 */
final class AdapterRegistry {

	/** @var array<string, Adapter> */
	private array $adapters = array();

	public function add( Adapter $adapter ): void {
		$this->adapters[ $adapter->id() ] = $adapter;
	}

	public function has( string $id ): bool {
		return isset( $this->adapters[ $id ] );
	}

	public function get( string $id ): ?Adapter {
		return $this->adapters[ $id ] ?? null;
	}

	/**
	 * @return list<Adapter>
	 */
	public function all(): array {
		return array_values( $this->adapters );
	}
}

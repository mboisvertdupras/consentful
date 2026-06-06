<?php
declare( strict_types = 1 );

namespace Consentful\Container;

use Closure;

/**
 * A minimal service container. Bindings are factories (closures that receive the
 * container); `singleton` memoizes the first resolution. Deliberately tiny — no
 * autowiring or reflection — so it ships unscoped under the Consentful namespace.
 */
final class Container {

	/** @var array<string, Closure(self): mixed> */
	private array $factories = array();

	/** @var array<string, bool> */
	private array $shared = array();

	/** @var array<string, mixed> */
	private array $instances = array();

	/**
	 * Register a factory that builds a fresh value on every resolution.
	 *
	 * @param Closure(self): mixed $factory
	 */
	public function bind( string $id, Closure $factory ): void {
		$this->factories[ $id ] = $factory;
		unset( $this->shared[ $id ], $this->instances[ $id ] );
	}

	/**
	 * Register a factory whose value is built once and reused.
	 *
	 * @param Closure(self): mixed $factory
	 */
	public function singleton( string $id, Closure $factory ): void {
		$this->factories[ $id ] = $factory;
		$this->shared[ $id ]    = true;
		unset( $this->instances[ $id ] );
	}

	/**
	 * Register an already-built value as a shared binding.
	 */
	public function instance( string $id, mixed $instance ): void {
		$this->instances[ $id ] = $instance;
		$this->shared[ $id ]    = true;
	}

	public function has( string $id ): bool {
		return isset( $this->factories[ $id ] ) || array_key_exists( $id, $this->instances );
	}

	/**
	 * Resolve a binding.
	 *
	 * @throws NotFoundException When nothing is bound to the given id.
	 */
	public function get( string $id ): mixed {
		if ( array_key_exists( $id, $this->instances ) ) {
			return $this->instances[ $id ];
		}
		if ( ! isset( $this->factories[ $id ] ) ) {
			throw NotFoundException::for_id( $id );
		}
		$value = ( $this->factories[ $id ] )( $this );
		if ( isset( $this->shared[ $id ] ) ) {
			$this->instances[ $id ] = $value;
		}
		return $value;
	}
}

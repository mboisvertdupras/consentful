<?php
declare( strict_types = 1 );

namespace Consentful\Consent;

/**
 * The active Purpose set — the default taxonomy plus any integrator additions.
 * Keyed by Purpose key (dedupes); preserves insertion order.
 */
final class PurposeRegistry {

	/** @var array<string, Purpose> */
	private array $purposes = array();

	/**
	 * @param iterable<Purpose> $purposes
	 */
	public function __construct( iterable $purposes ) {
		foreach ( $purposes as $purpose ) {
			$this->add( $purpose );
		}
	}

	/** Seed the registry from the default taxonomy. */
	public static function with_defaults(): self {
		return new self( DefaultPurpose::defaults() );
	}

	public function add( Purpose $purpose ): void {
		$this->purposes[ $purpose->key() ] = $purpose;
	}

	public function has( Purpose $purpose ): bool {
		return isset( $this->purposes[ $purpose->key() ] );
	}

	/**
	 * @return list<Purpose>
	 */
	public function all(): array {
		return array_values( $this->purposes );
	}
}

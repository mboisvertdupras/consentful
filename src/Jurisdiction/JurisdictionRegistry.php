<?php
declare( strict_types = 1 );

namespace Consentful\Jurisdiction;

use Consentful\Consent\DefaultPurpose;

/**
 * The active Jurisdiction set. Resolution is fail-closed: an unknown id falls back
 * to the strictest Policy so an unresolved region never loosens the gate.
 */
final class JurisdictionRegistry {

	/** @var array<string, Jurisdiction> */
	private array $jurisdictions = array();

	public function __construct(
		private readonly Jurisdiction $fallback,
	) {
		$this->add( $fallback );
	}

	public function add( Jurisdiction $jurisdiction ): void {
		$this->jurisdictions[ $jurisdiction->id ] = $jurisdiction;
	}

	public function get( string $id ): Jurisdiction {
		return $this->jurisdictions[ $id ] ?? $this->fallback;
	}

	public function fallback(): Jurisdiction {
		return $this->fallback;
	}

	/**
	 * Every registered Jurisdiction in insertion order ('*' fallback first, since the
	 * ctor adds it first). The client config ships all of them; the resolver picks one.
	 *
	 * @return list<Jurisdiction>
	 */
	public function all(): array {
		return array_values( $this->jurisdictions );
	}

	/**
	 * Seed the default jurisdictions. The '*' fallback is the strictest (opt-in);
	 * US grants every non-essential Purpose by default (opt-out).
	 */
	public static function with_defaults( int $policy_version ): self {
		$registry = new self(
			new Jurisdiction( '*', 'Default (strictest)', Policy::opt_in( $policy_version ) )
		);

		$registry->add( new Jurisdiction( 'QC', 'Québec (Loi 25)', Policy::opt_in( $policy_version ) ) );
		$registry->add( new Jurisdiction( 'EU', 'European Union (GDPR)', Policy::opt_in( $policy_version ) ) );
		$registry->add( new Jurisdiction( 'UK', 'United Kingdom (UK GDPR)', Policy::opt_in( $policy_version ) ) );

		$default_granted = array_values(
			array_filter(
				DefaultPurpose::defaults(),
				static fn ( DefaultPurpose $purpose ): bool => ! $purpose->is_always_on(),
			)
		);
		$registry->add(
			new Jurisdiction(
				'US',
				'United States (state opt-out)',
				Policy::opt_out( $policy_version, $default_granted )
			)
		);

		return $registry;
	}
}

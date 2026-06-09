<?php
declare( strict_types = 1 );

namespace Consentful\Jurisdiction;

use Consentful\Consent\DefaultPurpose;

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

	/** @return list<Jurisdiction> */
	public function all(): array {
		return array_values( $this->jurisdictions );
	}

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

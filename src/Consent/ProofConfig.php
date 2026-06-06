<?php
declare( strict_types = 1 );

namespace Consentful\Consent;

/**
 * The proof-of-consent client config: whether the gate posts a Consent record on each
 * decision. A pure value object — no consent logic, no WordPress. ClientConfig
 * serializes it into the camelCase `proof` block the JS gate reads; the endpoint URL
 * and banner version are supplied by the caller (the Gate) so this stays pure.
 *
 * An Integrator overrides the binding in `consentful_register` to disable proof.
 */
final class ProofConfig {

	public function __construct(
		public readonly bool $enabled,
	) {}

	/**
	 * The frozen `proof` block (camelCase keys). An empty endpoint tells the client to
	 * send nothing.
	 *
	 * @return array{enabled: bool, endpoint: string, bannerVersion: int}
	 */
	public function to_array( string $endpoint_url, int $banner_version ): array {
		return array(
			'enabled'       => $this->enabled,
			'endpoint'      => $endpoint_url,
			'bannerVersion' => $banner_version,
		);
	}

	/** Proof on by default — durable, pseudonymous consent records are the point. */
	public static function defaults(): self {
		return new self( true );
	}
}

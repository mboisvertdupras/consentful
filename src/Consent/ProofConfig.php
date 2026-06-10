<?php
declare( strict_types = 1 );

namespace Consentful\Consent;

final class ProofConfig {

	public function __construct(
		public readonly bool $enabled,
		public readonly int $retention_days = 730,
	) {}

	/** @return array{enabled: bool, endpoint: string, bannerVersion: int} */
	public function to_array( string $endpoint_url, int $banner_version ): array {
		return array(
			'enabled'       => $this->enabled,
			'endpoint'      => $endpoint_url,
			'bannerVersion' => $banner_version,
		);
	}

	public static function defaults(): self {
		return new self( true );
	}
}

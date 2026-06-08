<?php
declare( strict_types = 1 );

namespace Consentful\Consent;

/**
 * The proof-of-consent config: whether the gate posts a Consent record on each decision,
 * and how long records are retained. A pure value object — no consent logic, no WordPress.
 * ClientConfig serializes only the client-facing fields into the camelCase `proof` block;
 * `retention_days` is SERVER-ONLY (the cron purge reads it, the client never sees it).
 *
 * `enabled` is driven by the admin `proof` setting; `retention_days` stays a server-only
 * default (the cron purge reads it).
 */
final class ProofConfig {

	/**
	 * @param int $retention_days Days a Consent record is kept before the scheduled purge
	 *                            deletes it (ADR 0002 retention limit). `<= 0` keeps records
	 *                            indefinitely (the Integrator manages retention themselves).
	 */
	public function __construct(
		public readonly bool $enabled,
		public readonly int $retention_days = 730,
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

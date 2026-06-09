<?php
declare( strict_types = 1 );

namespace Consentful\Consent;

final class ConsentRecord {

	/**
	 * @param array<string, bool> $purposes
	 */
	public function __construct(
		public readonly string $consent_id,
		public readonly int $created_at,
		public readonly string $jurisdiction,
		public readonly int $policy_version,
		public readonly int $schema_version,
		public readonly int $banner_version,
		public readonly array $purposes,
		public readonly ?string $ip_hash,
		public readonly ?string $ua_hash,
	) {}

	/**
	 * @return array{consent_id: string, created_at: string, jurisdiction: string, policy_version: int, schema_version: int, banner_version: int, purposes: string, ip_hash: string|null, ua_hash: string|null}
	 */
	public function to_row(): array {
		return array(
			'consent_id'     => $this->consent_id,
			'created_at'     => gmdate( 'Y-m-d H:i:s', $this->created_at ),
			'jurisdiction'   => $this->jurisdiction,
			'policy_version' => $this->policy_version,
			'schema_version' => $this->schema_version,
			'banner_version' => $this->banner_version,
			'purposes'       => (string) wp_json_encode( $this->purposes ),
			'ip_hash'        => $this->ip_hash,
			'ua_hash'        => $this->ua_hash,
		);
	}

	/**
	 * @return array{consent_id: string, created_at: string, jurisdiction: string, policy_version: int, schema_version: int, banner_version: int, purposes: string, ip_hash: string, ua_hash: string}
	 */
	public function to_export_row(): array {
		return array(
			'consent_id'     => $this->consent_id,
			'created_at'     => ConsentLogSchema::export_timestamp( $this->created_at ),
			'jurisdiction'   => $this->jurisdiction,
			'policy_version' => $this->policy_version,
			'schema_version' => $this->schema_version,
			'banner_version' => $this->banner_version,
			'purposes'       => ConsentLogSchema::purposes_to_export( $this->purposes ),
			'ip_hash'        => $this->ip_hash ?? '',
			'ua_hash'        => $this->ua_hash ?? '',
		);
	}
}

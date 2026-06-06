<?php
declare( strict_types = 1 );

namespace Consentful\Consent;

/**
 * An immutable, server-side proof-of-consent record — the durable, pseudonymous
 * evidence a client cookie cannot be. Built once per decision at the REST boundary
 * from validated input plus server-stamped fields, then handed to a Sink.
 *
 * Pure: no WordPress functions (it serializes only via gmdate / wp_json_encode it is
 * handed indirectly), so it runs under PHPUnit without WordPress. Distinct from the
 * runtime Consent value object (the cookie) — that one drives tag gating; this one is
 * the separate audit entity. Hashes are sha256(salt . value) hex or null — never raw
 * IP/UA.
 */
final class ConsentRecord {

	/**
	 * @param array<string, bool> $purposes Purpose key → granted.
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
	 * The DB row: scalar columns plus the DATETIME-formatted timestamp and the
	 * JSON-encoded purposes. Hash columns carry the hex digest or null. Never any raw
	 * IP/UA. Column order mirrors the DatabaseSink format map.
	 *
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
	 * An auditor-friendly, flat row that drives the CSV export: ISO-8601 UTC timestamp,
	 * a stable `key=0/1;…` purposes string (sorted by key), the scalar versions, and the
	 * hex hashes ('' when absent). All values are scalar so the row round-trips through
	 * fputcsv unchanged.
	 *
	 * @return array{consent_id: string, created_at: string, jurisdiction: string, policy_version: int, schema_version: int, banner_version: int, purposes: string, ip_hash: string, ua_hash: string}
	 */
	public function to_export_row(): array {
		return array(
			'consent_id'     => $this->consent_id,
			'created_at'     => gmdate( 'c', $this->created_at ),
			'jurisdiction'   => $this->jurisdiction,
			'policy_version' => $this->policy_version,
			'schema_version' => $this->schema_version,
			'banner_version' => $this->banner_version,
			'purposes'       => $this->purposes_string(),
			'ip_hash'        => $this->ip_hash ?? '',
			'ua_hash'        => $this->ua_hash ?? '',
		);
	}

	/** Stable `key=0/1;…` purposes encoding, sorted by key for deterministic export. */
	private function purposes_string(): string {
		$purposes = $this->purposes;
		ksort( $purposes );

		$parts = array();
		foreach ( $purposes as $key => $granted ) {
			$parts[] = $key . '=' . ( $granted ? '1' : '0' );
		}
		return implode( ';', $parts );
	}
}

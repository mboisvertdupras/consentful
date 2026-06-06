<?php
declare( strict_types = 1 );

namespace Consentful\Consent;

/**
 * An immutable, runtime Consent — a Visitor's decision, backed by the cookie
 * (schema v1, compact keys) as its serialization. Tag gating reads `granted()`.
 * (Distinct from the server-side Consent record, the separate proof entity.)
 */
final class Consent {

	/**
	 * @param array<string, bool> $grants Purpose key → granted.
	 */
	public function __construct(
		public readonly array $grants,
		public readonly string $jurisdiction,
		public readonly int $schema_version,
		public readonly int $policy_version,
		public readonly int $timestamp,
	) {}

	/** Always-on purposes are granted; otherwise the stored grant decides. */
	public function granted( Purpose $purpose ): bool {
		if ( $purpose->is_always_on() ) {
			return true;
		}
		return ! empty( $this->grants[ $purpose->key() ] );
	}

	/** False when a version differs, the timestamp is unset, or the window lapsed. */
	public function is_valid( int $schema_version, int $policy_version, int $max_age_ms, int $now_ms ): bool {
		if ( $schema_version !== $this->schema_version || $policy_version !== $this->policy_version ) {
			return false;
		}
		if ( $this->timestamp <= 0 ) {
			return false;
		}
		return ( $now_ms - $this->timestamp ) <= $max_age_ms;
	}

	/**
	 * Serialize to the compact cookie shape (grants as 0/1).
	 *
	 * @return array{ v: int, p: int, j: string, g: array<string, int>, t: int }
	 */
	public function to_cookie(): array {
		$g = array();
		foreach ( $this->grants as $key => $granted ) {
			$g[ $key ] = $granted ? 1 : 0;
		}
		return array(
			'v' => $this->schema_version,
			'p' => $this->policy_version,
			'j' => $this->jurisdiction,
			'g' => $g,
			't' => $this->timestamp,
		);
	}

	/**
	 * Rebuild from a decoded cookie payload. Null unless the shape matches v1.
	 */
	public static function from_cookie( mixed $data ): ?self {
		if ( ! is_array( $data ) ) {
			return null;
		}
		if ( ! isset( $data['v'], $data['p'], $data['t'] ) || ! array_key_exists( 'g', $data ) ) {
			return null;
		}
		if ( ! is_numeric( $data['v'] ) || ! is_numeric( $data['p'] ) || ! is_numeric( $data['t'] ) ) {
			return null;
		}
		if ( ! is_array( $data['g'] ) ) {
			return null;
		}

		$grants = array();
		foreach ( $data['g'] as $key => $value ) {
			// PHP cannot hold a numeric-string as a string key; skip to keep the
			// declared array<string, bool> honest and harden a tampered cookie.
			if ( ! is_string( $key ) ) {
				continue;
			}
			$grants[ $key ] = (bool) $value;
		}

		$jurisdiction = isset( $data['j'] ) && is_scalar( $data['j'] ) ? (string) $data['j'] : '';

		return new self(
			$grants,
			$jurisdiction,
			(int) $data['v'],
			(int) $data['p'],
			(int) $data['t'],
		);
	}
}

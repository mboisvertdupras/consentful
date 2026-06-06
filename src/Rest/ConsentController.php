<?php
declare( strict_types = 1 );

namespace Consentful\Rest;

use Consentful\Consent\ConsentRecord;
use Consentful\Consent\Sink;

/**
 * The separate, non-cached proof-of-consent endpoint. The gate async-POSTs a Consent
 * record here on each decision; the record is pseudonymized SERVER-SIDE (the client
 * never sends raw IP/UA — the server reads REMOTE_ADDR / HTTP_USER_AGENT and stores
 * only salted sha256 hashes) and handed to the bound Sink.
 *
 * Public and unauthenticated BY NECESSITY (cache-safety): a per-visitor nonce in page
 * HTML would poison the full-page cache, so `permission_callback` is `__return_true`
 * and there is no nonce. The trust boundary is enforced by strict input validation and
 * server-side hashing in the pure `record_from_input` core, never by interpolating
 * values into SQL. A rate limit is an integrator / edge concern (CDN, WAF) — this
 * controller does not build one.
 */
final class ConsentController {

	public const NAMESPACE = 'consentful/v1';
	public const ROUTE     = '/consent';

	/** Upper bound on stored grant entries — a public endpoint must not bloat the row. */
	private const MAX_GRANTS = 50;

	public function __construct(
		private readonly Sink $sink,
		private readonly string $salt,
	) {}

	/** Hook the route registration onto the REST init. */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	/** Register the public POST route on `rest_api_init`. */
	public function register_route(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * The route callback. Builds the record from the JSON body + `$_SERVER`, server-
	 * stamping the authoritative timestamp, then stores it. Returns a plain array the
	 * WP REST layer serializes; a malformed body yields a 400 WP_Error.
	 *
	 * @return array{stored: true, id: string}|\WP_Error
	 */
	public function handle( \WP_REST_Request $request ): array|\WP_Error {
		// get_json_params() returns null (empty body / non-JSON content-type) or a scalar
		// (scalar JSON) in real WordPress; normalize so a malformed body is a clean 400,
		// never a strict_types TypeError 500 at this public, unauthenticated boundary.
		$params = self::params_from( $request->get_json_params() );

		$cid = self::clean_cid( isset( $params['cid'] ) && is_string( $params['cid'] ) ? $params['cid'] : '' );
		$cid = '' !== $cid ? $cid : wp_generate_password( 32, false, false );

		// $_SERVER goes straight into the pure core, which validates and salt-hashes
		// each value server-side — raw IP/UA are never stored or echoed.
		$record = $this->record_from_input( $cid, $params, $_SERVER, time() );

		if ( null === $record ) {
			return new \WP_Error(
				'consentful_invalid',
				'Invalid consent record.',
				array( 'status' => 400 )
			);
		}

		$this->sink->store( $record );

		return array(
			'stored' => true,
			'id'     => $record->consent_id,
		);
	}

	/**
	 * The pure, unit-tested heart: validate and build a ConsentRecord from a clean cid,
	 * the request params, the server vars and the server time. Null when the input is
	 * unusable (missing/empty grants). IP/UA are hashed here, never stored raw. The
	 * policy/schema/banner version fields are client-claimed provenance (advisory, like the
	 * ignored client timestamp); only created_at and the IP/UA hashes are server-authoritative.
	 *
	 * @param array<mixed> $params The JSON body params (untrusted).
	 * @param array<mixed> $server The request server vars (e.g. $_SERVER).
	 * @param int          $now    The authoritative server time (epoch seconds).
	 */
	public function record_from_input( string $cid, array $params, array $server, int $now ): ?ConsentRecord {
		$grants = self::clean_grants( $params['grants'] ?? null );
		if ( array() === $grants ) {
			return null;
		}

		return new ConsentRecord(
			$cid,
			$now,
			self::clean_jurisdiction( $params['jurisdiction'] ?? null ),
			self::int_field( $params['policyVersion'] ?? null ),
			self::int_field( $params['schemaVersion'] ?? null ),
			self::int_field( $params['bannerVersion'] ?? null ),
			$grants,
			self::hash_pii( $this->salt, self::server_string( $server, 'REMOTE_ADDR' ) ),
			self::hash_pii( $this->salt, self::server_string( $server, 'HTTP_USER_AGENT' ) ),
		);
	}

	/**
	 * Normalize the JSON body to an array. WordPress' get_json_params() can return null
	 * (empty body / non-JSON content-type) or a scalar (scalar JSON); under strict_types
	 * those would fatal the typed core. A non-array body collapses to an empty array, so a
	 * malformed request becomes a clean 400 rather than an uncaught 500. Public so this
	 * boundary normalization is unit-tested directly — a stub-faithful WP_REST_Request
	 * double cannot return a non-array.
	 *
	 * @param  mixed $raw The raw get_json_params() value.
	 * @return array<mixed>
	 */
	public static function params_from( mixed $raw ): array {
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Coerce the grants map to `array<string, bool>`: drop non-string and over-long keys,
	 * cast each value to bool, and bound the count — a public, unauthenticated write must
	 * not let a caller bloat the row or silently overflow the TEXT column. Anything but a
	 * non-empty array yields an empty array (⇒ invalid).
	 *
	 * @return array<string, bool>
	 */
	private static function clean_grants( mixed $value ): array {
		if ( ! is_array( $value ) || array() === $value ) {
			return array();
		}

		$grants = array();
		foreach ( $value as $key => $granted ) {
			if ( ! is_string( $key ) || strlen( $key ) > 64 ) {
				continue;
			}
			$grants[ $key ] = (bool) $granted;
			if ( count( $grants ) >= self::MAX_GRANTS ) {
				break;
			}
		}
		return $grants;
	}

	/** A cid is `[A-Za-z0-9-]{1,64}`; anything else collapses to '' (caller falls back). */
	private static function clean_cid( string $value ): string {
		return 1 === preg_match( '/^[A-Za-z0-9-]{1,64}$/', $value ) ? $value : '';
	}

	/** Sanitize the jurisdiction to `[A-Za-z*-]{1,16}`; '*' (strictest) otherwise. */
	private static function clean_jurisdiction( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			return '*';
		}
		$jurisdiction = sanitize_text_field( $value );
		if ( strlen( $jurisdiction ) > 16 || 1 !== preg_match( '/^[A-Za-z*-]+$/', $jurisdiction ) ) {
			return '*';
		}
		return $jurisdiction;
	}

	/** A non-negative int from an untrusted value; 0 when not scalar-coercible. */
	private static function int_field( mixed $value ): int {
		return is_scalar( $value ) ? absint( $value ) : 0;
	}

	/**
	 * Read a server var as a trimmed string ('' when absent or non-string).
	 *
	 * @param array<mixed> $server The request server vars (e.g. $_SERVER).
	 */
	private static function server_string( array $server, string $key ): string {
		$value = $server[ $key ] ?? '';
		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * Salted sha256 of a PII value, hex. The salt makes the hash non-reversible and
	 * non-correlatable across sites. Null when the value is empty (no PII to hash) — so
	 * we never persist a hash of the empty string.
	 */
	private static function hash_pii( string $salt, string $value ): ?string {
		if ( '' === $value ) {
			return null;
		}
		return hash( 'sha256', $salt . $value );
	}
}

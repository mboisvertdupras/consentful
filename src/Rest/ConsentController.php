<?php
declare( strict_types = 1 );

namespace Consentful\Rest;

use Consentful\Consent\ConsentRecord;
use Consentful\Consent\Sink;

final class ConsentController {

	public const NAMESPACE = 'consentful/v1';
	public const ROUTE     = '/consent';

	private const MAX_GRANTS = 50;

	public function __construct(
		private readonly Sink $sink,
		private readonly string $salt,
	) {}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

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

	public function handle( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$params = self::params_from( $request->get_json_params() );

		$cid = self::clean_cid( isset( $params['cid'] ) && is_string( $params['cid'] ) ? $params['cid'] : '' );
		$cid = '' !== $cid ? $cid : wp_generate_password( 32, false, false );

		$record = $this->record_from_input( $cid, $params, $_SERVER, time() );

		if ( null === $record ) {
			return new \WP_Error(
				'consentful_invalid',
				'Invalid consent record.',
				array( 'status' => 400 )
			);
		}

		$this->sink->store( $record );

		$response = new \WP_REST_Response(
			array(
				'stored' => true,
				'id'     => $record->consent_id,
			)
		);
		$response->header( 'Cache-Control', 'no-store, max-age=0' );
		return $response;
	}

	/**
	 * @param array<mixed> $params
	 * @param array<mixed> $server
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

	/** @return array<mixed> */
	public static function params_from( mixed $raw ): array {
		return is_array( $raw ) ? $raw : array();
	}

	/** @return array<string, bool> */
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

	private static function clean_cid( string $value ): string {
		return 1 === preg_match( '/^[A-Za-z0-9-]{1,64}$/', $value ) ? $value : '';
	}

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

	private static function int_field( mixed $value ): int {
		return is_scalar( $value ) ? absint( $value ) : 0;
	}

	/** @param array<mixed> $server */
	private static function server_string( array $server, string $key ): string {
		$value = $server[ $key ] ?? '';
		return is_string( $value ) ? trim( $value ) : '';
	}

	private static function hash_pii( string $salt, string $value ): ?string {
		if ( '' === $value ) {
			return null;
		}
		return hash( 'sha256', $salt . $value );
	}
}

<?php
declare( strict_types = 1 );

namespace Consentful\Rest;

final class GeoController {

	public const NAMESPACE = 'consentful/v1';
	public const ROUTE     = '/geo';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	public function register_route(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/** @return array{region: string|null} */
	public function handle(): array {
		return array( 'region' => self::detect_region( $_SERVER ) );
	}

	/** @param array<mixed> $server */
	public static function detect_region( array $server ): ?string {
		$country = self::valid_country( $server['HTTP_CLOUDFRONT_VIEWER_COUNTRY'] ?? null );
		if ( null !== $country ) {
			return self::with_region( $country, $server['HTTP_CLOUDFRONT_VIEWER_COUNTRY_REGION'] ?? null );
		}

		$country = self::valid_country( $server['HTTP_CF_IPCOUNTRY'] ?? null );
		if ( null !== $country ) {
			return $country;
		}

		$country = self::valid_country( $server['HTTP_X_GEO_COUNTRY'] ?? null );
		if ( null !== $country ) {
			return self::with_region( $country, $server['HTTP_X_GEO_REGION'] ?? null );
		}

		return null;
	}

	private static function valid_country( mixed $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}
		$country = strtoupper( trim( $value ) );
		return 1 === preg_match( '/^[A-Z]{2}$/', $country ) ? $country : null;
	}

	private static function with_region( string $country, mixed $value ): string {
		if ( ! is_string( $value ) ) {
			return $country;
		}
		$region = strtoupper( trim( $value ) );
		return 1 === preg_match( '/^[A-Z0-9]{1,3}$/', $region ) ? $country . '-' . $region : $country;
	}
}

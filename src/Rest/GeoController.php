<?php
declare( strict_types = 1 );

namespace Consentful\Rest;

/**
 * The separate, non-cached geo endpoint. The async client fallback hits it only when
 * the sync signals (edge cookie / window var) leave the region unresolved and the
 * Visitor has not yet decided — keeping page HTML cache-safe and identical for all.
 *
 * Region detection reads CDN geo headers from a server-passed array so it stays pure
 * and unit-testable without WordPress. Header values are visitor-controllable behind
 * the CDN, so the country/subdivision are strictly validated and never echoed raw.
 */
final class GeoController {

	public const NAMESPACE = 'consentful/v1';
	public const ROUTE     = '/geo';

	/** Hook the route registration onto the REST init. */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	/** Register the public GET route on `rest_api_init`. */
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

	/**
	 * The route callback. Returns a plain array the WP REST layer serializes — no
	 * WP_REST_Response so the class stays unit-testable without WordPress loaded.
	 *
	 * @return array{region: string|null}
	 */
	public function handle(): array {
		return array( 'region' => self::detect_region( $_SERVER ) );
	}

	/**
	 * Resolve a region code from CDN geo headers. First non-empty source wins; a
	 * subdivision is appended as `CC-RR`. Country must match /^[A-Z]{2}$/ and the
	 * subdivision /^[A-Z0-9]{1,3}$/ after upper-casing; otherwise treated as absent.
	 *
	 * @param array<mixed> $server The request server vars (e.g. $_SERVER).
	 */
	public static function detect_region( array $server ): ?string {
		// CloudFront: country plus an optional subdivision.
		$country = self::valid_country( $server['HTTP_CLOUDFRONT_VIEWER_COUNTRY'] ?? null );
		if ( null !== $country ) {
			return self::with_region( $country, $server['HTTP_CLOUDFRONT_VIEWER_COUNTRY_REGION'] ?? null );
		}

		// Cloudflare: country only.
		$country = self::valid_country( $server['HTTP_CF_IPCOUNTRY'] ?? null );
		if ( null !== $country ) {
			return $country;
		}

		// Generic / Akamai / Fastly: country plus an optional subdivision.
		$country = self::valid_country( $server['HTTP_X_GEO_COUNTRY'] ?? null );
		if ( null !== $country ) {
			return self::with_region( $country, $server['HTTP_X_GEO_REGION'] ?? null );
		}

		return null;
	}

	/** Upper-case and validate an ISO-3166-1 alpha-2 country; null when invalid. */
	private static function valid_country( mixed $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}
		$country = strtoupper( trim( $value ) );
		return 1 === preg_match( '/^[A-Z]{2}$/', $country ) ? $country : null;
	}

	/** Append a valid subdivision as `CC-RR`; return the bare country otherwise. */
	private static function with_region( string $country, mixed $value ): string {
		if ( ! is_string( $value ) ) {
			return $country;
		}
		$region = strtoupper( trim( $value ) );
		return 1 === preg_match( '/^[A-Z0-9]{1,3}$/', $region ) ? $country . '-' . $region : $country;
	}
}

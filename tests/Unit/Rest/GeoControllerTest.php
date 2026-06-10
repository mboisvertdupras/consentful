<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Rest;

use Consentful\Rest\GeoController;
use PHPUnit\Framework\TestCase;

final class GeoControllerTest extends TestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['consentful_test_actions'], $GLOBALS['consentful_test_rest_routes'] );
		parent::tearDown();
	}

	/** @return list<mixed> */
	private function recorded_actions(): array {
		$actions = $GLOBALS['consentful_test_actions'] ?? array();
		return is_array( $actions ) ? array_values( $actions ) : array();
	}

	/** @return list<mixed> */
	private function recorded_routes(): array {
		$routes = $GLOBALS['consentful_test_rest_routes'] ?? array();
		return is_array( $routes ) ? array_values( $routes ) : array();
	}

	public function test_cloudfront_combines_country_and_subdivision(): void {
		$region = GeoController::detect_region(
			array(
				'HTTP_CLOUDFRONT_VIEWER_COUNTRY'        => 'us',
				'HTTP_CLOUDFRONT_VIEWER_COUNTRY_REGION' => 'ca',
			)
		);

		$this->assertSame( 'US-CA', $region );
	}

	public function test_cloudfront_country_without_a_subdivision(): void {
		$region = GeoController::detect_region(
			array( 'HTTP_CLOUDFRONT_VIEWER_COUNTRY' => 'CA' )
		);

		$this->assertSame( 'CA', $region );
	}

	public function test_cloudflare_country_only(): void {
		$region = GeoController::detect_region(
			array( 'HTTP_CF_IPCOUNTRY' => 'FR' )
		);

		$this->assertSame( 'FR', $region );
	}

	public function test_generic_header_combines_country_and_region(): void {
		$region = GeoController::detect_region(
			array(
				'HTTP_X_GEO_COUNTRY' => 'gb',
				'HTTP_X_GEO_REGION'  => 'eng',
			)
		);

		$this->assertSame( 'GB-ENG', $region );
	}

	public function test_cloudfront_takes_precedence_over_cloudflare(): void {
		$region = GeoController::detect_region(
			array(
				'HTTP_CLOUDFRONT_VIEWER_COUNTRY' => 'US',
				'HTTP_CF_IPCOUNTRY'              => 'FR',
			)
		);

		$this->assertSame( 'US', $region );
	}

	public function test_invalid_country_is_rejected(): void {
		$this->assertNull(
			GeoController::detect_region( array( 'HTTP_CF_IPCOUNTRY' => 'xx-bad' ) )
		);
	}

	public function test_invalid_subdivision_falls_back_to_the_bare_country(): void {
		$region = GeoController::detect_region(
			array(
				'HTTP_CLOUDFRONT_VIEWER_COUNTRY'        => 'US',
				'HTTP_CLOUDFRONT_VIEWER_COUNTRY_REGION' => 'california',
			)
		);

		$this->assertSame( 'US', $region );
	}

	public function test_no_headers_resolve_to_null(): void {
		$this->assertNull( GeoController::detect_region( array() ) );
	}

	public function test_non_string_header_is_treated_as_absent(): void {
		$this->assertNull( GeoController::detect_region( array( 'HTTP_CF_IPCOUNTRY' => array( 'US' ) ) ) );
	}

	public function test_register_hooks_register_route_on_rest_api_init(): void {
		$GLOBALS['consentful_test_actions'] = array();

		( new GeoController() )->register();

		$this->assertContains( 'rest_api_init', array_column( $this->recorded_actions(), 'hook' ) );
	}

	public function test_register_route_records_the_public_get_route(): void {
		$GLOBALS['consentful_test_rest_routes'] = array();

		( new GeoController() )->register_route();

		$routes = $this->recorded_routes();
		$this->assertCount( 1, $routes );
		$route = $routes[0];
		$this->assertIsArray( $route );
		$this->assertSame( 'consentful/v1', $route['namespace'] );
		$this->assertSame( '/geo', $route['route'] );
		$args = $route['args'];
		$this->assertIsArray( $args );
		$this->assertSame( 'GET', $args['methods'] );
		$this->assertSame( '__return_true', $args['permission_callback'] );
	}

	public function test_handle_returns_a_region_keyed_response(): void {
		$out = ( new GeoController() )->handle();

		$data = $out->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'region', $data );
	}

	public function test_handle_sends_a_no_store_cache_header(): void {
		$out = ( new GeoController() )->handle();

		$this->assertSame( 'no-store, max-age=0', $out->get_headers()['Cache-Control'] ?? null );
	}
}

<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Rest;

use Consentful\Consent\ConsentRecord;
use Consentful\Consent\Sink;
use Consentful\Rest\ConsentController;
use Consentful\Tests\Unit\Support\FakeRestRequest;
use PHPUnit\Framework\TestCase;

final class ConsentControllerTest extends TestCase {

	private const SALT = 'pepper';

	protected function tearDown(): void {
		unset( $GLOBALS['consentful_test_actions'], $GLOBALS['consentful_test_rest_routes'] );
		parent::tearDown();
	}

	private function controller( ?RecordingSink $sink = null ): ConsentController {
		return new ConsentController( $sink ?? new RecordingSink(), self::SALT );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function valid_params(): array {
		return array(
			'cid'           => 'cid-abc',
			'grants'        => array(
				'necessary' => 1,
				'analytics' => 0,
				'marketing' => true,
			),
			'jurisdiction'  => 'US',
			'policyVersion' => 2,
			'schemaVersion' => 3,
			'bannerVersion' => 4,
			'timestamp'     => 1733400000000,
		);
	}

	/**
	 * @return array<string, string>
	 */
	private function server(): array {
		return array(
			'REMOTE_ADDR'     => '203.0.113.7',
			'HTTP_USER_AGENT' => 'Mozilla/5.0',
		);
	}

	public function test_valid_input_builds_a_record_with_server_stamped_time(): void {
		$record = $this->controller()->record_from_input( 'cid-abc', $this->valid_params(), $this->server(), 1733400000 );

		$this->assertInstanceOf( ConsentRecord::class, $record );
		$this->assertSame( 'cid-abc', $record->consent_id );
		$this->assertSame( 1733400000, $record->created_at );
		$this->assertSame( 'US', $record->jurisdiction );
		$this->assertSame( 2, $record->policy_version );
		$this->assertSame( 3, $record->schema_version );
		$this->assertSame( 4, $record->banner_version );
	}

	public function test_grants_are_coerced_to_bool(): void {
		$record = $this->controller()->record_from_input( 'cid-abc', $this->valid_params(), $this->server(), 1733400000 );

		$this->assertInstanceOf( ConsentRecord::class, $record );
		$this->assertSame(
			array(
				'necessary' => true,
				'analytics' => false,
				'marketing' => true,
			),
			$record->purposes
		);
	}

	public function test_ip_and_ua_are_salted_sha256_hashes(): void {
		$record = $this->controller()->record_from_input( 'cid-abc', $this->valid_params(), $this->server(), 1733400000 );

		$this->assertInstanceOf( ConsentRecord::class, $record );
		$this->assertSame( hash( 'sha256', self::SALT . '203.0.113.7' ), $record->ip_hash );
		$this->assertSame( hash( 'sha256', self::SALT . 'Mozilla/5.0' ), $record->ua_hash );
	}

	public function test_raw_ip_and_ua_never_appear_in_the_row(): void {
		$record = $this->controller()->record_from_input( 'cid-abc', $this->valid_params(), $this->server(), 1733400000 );

		$this->assertInstanceOf( ConsentRecord::class, $record );
		$row = $record->to_row();
		$this->assertNotContains( '203.0.113.7', $row );
		$this->assertNotContains( 'Mozilla/5.0', $row );
	}

	public function test_absent_server_pii_hashes_to_null(): void {
		$record = $this->controller()->record_from_input( 'cid-abc', $this->valid_params(), array(), 1733400000 );

		$this->assertInstanceOf( ConsentRecord::class, $record );
		$this->assertNull( $record->ip_hash );
		$this->assertNull( $record->ua_hash );
	}

	public function test_missing_grants_yield_null(): void {
		$params = $this->valid_params();
		unset( $params['grants'] );

		$this->assertNull( $this->controller()->record_from_input( 'cid-abc', $params, $this->server(), 1733400000 ) );
	}

	public function test_empty_grants_yield_null(): void {
		$params           = $this->valid_params();
		$params['grants'] = array();

		$this->assertNull( $this->controller()->record_from_input( 'cid-abc', $params, $this->server(), 1733400000 ) );
	}

	public function test_non_string_grant_keys_are_dropped(): void {
		$params           = $this->valid_params();
		$params['grants'] = array(
			'necessary' => 1,
			5           => 1,
		);

		$record = $this->controller()->record_from_input( 'cid-abc', $params, $this->server(), 1733400000 );

		$this->assertInstanceOf( ConsentRecord::class, $record );
		$this->assertSame( array( 'necessary' => true ), $record->purposes );
	}

	public function test_invalid_jurisdiction_falls_back_to_star(): void {
		$params                 = $this->valid_params();
		$params['jurisdiction'] = 'not a region!!';

		$record = $this->controller()->record_from_input( 'cid-abc', $params, $this->server(), 1733400000 );

		$this->assertInstanceOf( ConsentRecord::class, $record );
		$this->assertSame( '*', $record->jurisdiction );
	}

	public function test_overlong_jurisdiction_falls_back_to_star(): void {
		$params                 = $this->valid_params();
		$params['jurisdiction'] = str_repeat( 'A', 17 );

		$record = $this->controller()->record_from_input( 'cid-abc', $params, $this->server(), 1733400000 );

		$this->assertInstanceOf( ConsentRecord::class, $record );
		$this->assertSame( '*', $record->jurisdiction );
	}

	public function test_versions_default_to_zero_when_absent(): void {
		$params = array(
			'grants' => array( 'necessary' => 1 ),
		);

		$record = $this->controller()->record_from_input( 'cid-abc', $params, $this->server(), 1733400000 );

		$this->assertInstanceOf( ConsentRecord::class, $record );
		$this->assertSame( 0, $record->policy_version );
		$this->assertSame( 0, $record->schema_version );
		$this->assertSame( 0, $record->banner_version );
	}

	public function test_params_from_normalizes_a_non_array_body_to_an_empty_array(): void {
		$this->assertSame( array(), ConsentController::params_from( null ) );
		$this->assertSame( array(), ConsentController::params_from( 42 ) );
		$this->assertSame( array(), ConsentController::params_from( 'scalar-body' ) );
	}

	public function test_params_from_passes_an_array_body_through(): void {
		$body = array( 'grants' => array( 'necessary' => 1 ) );

		$this->assertSame( $body, ConsentController::params_from( $body ) );
	}

	public function test_overlong_grant_keys_are_dropped(): void {
		$long             = str_repeat( 'x', 65 );
		$params           = $this->valid_params();
		$params['grants'] = array(
			'necessary' => 1,
			$long       => 1,
		);

		$record = $this->controller()->record_from_input( 'cid-abc', $params, $this->server(), 1733400000 );

		$this->assertInstanceOf( ConsentRecord::class, $record );
		$this->assertSame( array( 'necessary' => true ), $record->purposes );
	}

	public function test_grants_count_is_capped(): void {
		$grants = array();
		for ( $i = 0; $i < 120; $i++ ) {
			$grants[ 'p' . $i ] = 1;
		}
		$params           = $this->valid_params();
		$params['grants'] = $grants;

		$record = $this->controller()->record_from_input( 'cid-abc', $params, $this->server(), 1733400000 );

		$this->assertInstanceOf( ConsentRecord::class, $record );
		$this->assertCount( 50, $record->purposes );
	}

	public function test_handle_stores_the_record_and_returns_the_response(): void {
		$sink    = new RecordingSink();
		$request = new FakeRestRequest( $this->valid_params() );

		$result = $this->controller( $sink )->handle( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $result );
		$this->assertSame(
			array(
				'stored' => true,
				'id'     => 'cid-abc',
			),
			$result->get_data()
		);
		$this->assertSame( 'no-store, max-age=0', $result->get_headers()['Cache-Control'] ?? null );
		$this->assertCount( 1, $sink->stored );
	}

	public function test_handle_returns_a_400_wp_error_on_missing_grants(): void {
		$sink    = new RecordingSink();
		$request = new FakeRestRequest( array( 'jurisdiction' => 'US' ) );

		$result = $this->controller( $sink )->handle( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'consentful_invalid', $result->get_error_code() );
		$this->assertSame( array( 'status' => 400 ), $result->get_error_data() );
		$this->assertSame( array(), $sink->stored );
	}

	public function test_handle_generates_a_cid_when_the_client_omits_one(): void {
		$sink    = new RecordingSink();
		$request = new FakeRestRequest( array( 'grants' => array( 'necessary' => 1 ) ) );

		$result = $this->controller( $sink )->handle( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $result );
		$data = $result->get_data();
		$this->assertIsArray( $data );
		$this->assertNotSame( '', $data['id'] );
		$this->assertCount( 1, $sink->stored );
	}

	public function test_handle_rejects_a_malformed_client_cid_and_generates_one(): void {
		$sink    = new RecordingSink();
		$request = new FakeRestRequest(
			array(
				'cid'    => 'bad cid with spaces',
				'grants' => array( 'necessary' => 1 ),
			)
		);

		$result = $this->controller( $sink )->handle( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $result );
		$data = $result->get_data();
		$this->assertIsArray( $data );
		$this->assertNotSame( 'bad cid with spaces', $data['id'] );
		$this->assertCount( 1, $sink->stored );
	}

	public function test_register_hooks_register_route_on_rest_api_init(): void {
		$GLOBALS['consentful_test_actions'] = array();

		$this->controller()->register();

		$this->assertContains( 'rest_api_init', array_column( $this->recorded_actions(), 'hook' ) );
	}

	public function test_register_route_records_the_public_post_route(): void {
		$GLOBALS['consentful_test_rest_routes'] = array();

		$this->controller()->register_route();

		$routes = $this->recorded_routes();
		$this->assertCount( 1, $routes );
		$route = $routes[0];
		$this->assertIsArray( $route );
		$this->assertSame( 'consentful/v1', $route['namespace'] );
		$this->assertSame( '/consent', $route['route'] );
		$args = $route['args'];
		$this->assertIsArray( $args );
		$this->assertSame( 'POST', $args['methods'] );
		$this->assertSame( '__return_true', $args['permission_callback'] );
	}

	/**
	 * @return list<mixed>
	 */
	private function recorded_actions(): array {
		$actions = $GLOBALS['consentful_test_actions'] ?? array();
		return is_array( $actions ) ? array_values( $actions ) : array();
	}

	/**
	 * @return list<mixed>
	 */
	private function recorded_routes(): array {
		$routes = $GLOBALS['consentful_test_rest_routes'] ?? array();
		return is_array( $routes ) ? array_values( $routes ) : array();
	}
}

final class RecordingSink implements Sink {

	/** @var list<ConsentRecord> */
	public array $stored = array();

	public function store( ConsentRecord $record ): void {
		$this->stored[] = $record;
	}
}

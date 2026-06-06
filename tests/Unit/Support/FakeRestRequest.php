<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Support;

/**
 * A test double for `WP_REST_Request` that returns a fixed JSON body. Extends the real
 * class so it satisfies the controller's `\WP_REST_Request` type hint; the body is
 * supplied at construction rather than parsed from a stream.
 */
final class FakeRestRequest extends \WP_REST_Request {

	/** @var array<string, mixed> */
	private array $json;

	/** @param array<string, mixed> $json */
	public function __construct( array $json = array() ) {
		parent::__construct();
		$this->json = $json;
	}

	/** @return array<string, mixed> */
	public function get_json_params() {
		return $this->json;
	}
}

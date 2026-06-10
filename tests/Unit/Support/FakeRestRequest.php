<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Support;

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

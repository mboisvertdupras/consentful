<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Adapter;

use Consentful\Adapter\Adapter;
use Consentful\Adapter\AdapterRegistry;
use PHPUnit\Framework\TestCase;

final class FakeAdapter implements Adapter {

	public function __construct(
		private readonly string $id
	) {}

	public function id(): string {
		return $this->id;
	}

	/** @return array<string, mixed> */
	public function client_config(): array {
		return array( 'id' => $this->id );
	}
}

final class AdapterRegistryTest extends TestCase {

	public function test_add_then_get_returns_the_same_adapter(): void {
		$registry = new AdapterRegistry();
		$adapter  = new FakeAdapter( 'google' );
		$registry->add( $adapter );

		$this->assertSame( $adapter, $registry->get( 'google' ) );
	}

	public function test_get_returns_null_for_an_unknown_id(): void {
		$registry = new AdapterRegistry();

		$this->assertNull( $registry->get( 'missing' ) );
	}

	public function test_has_reports_known_and_unknown_ids(): void {
		$registry = new AdapterRegistry();
		$registry->add( new FakeAdapter( 'google' ) );

		$this->assertTrue( $registry->has( 'google' ) );
		$this->assertFalse( $registry->has( 'missing' ) );
	}

	public function test_add_is_keyed_by_id_and_dedupes(): void {
		$registry = new AdapterRegistry();
		$first    = new FakeAdapter( 'google' );
		$second   = new FakeAdapter( 'google' );

		$registry->add( $first );
		$registry->add( $second );

		$this->assertCount( 1, $registry->all() );
		$this->assertSame( $second, $registry->get( 'google' ) );
	}

	public function test_all_returns_adapters_in_insertion_order(): void {
		$registry = new AdapterRegistry();
		$google   = new FakeAdapter( 'google' );
		$meta     = new FakeAdapter( 'meta' );
		$registry->add( $google );
		$registry->add( $meta );

		$this->assertSame( array( $google, $meta ), $registry->all() );
	}

	public function test_client_config_returns_the_expected_array(): void {
		$adapter = new FakeAdapter( 'google' );

		$this->assertSame( array( 'id' => 'google' ), $adapter->client_config() );
	}
}

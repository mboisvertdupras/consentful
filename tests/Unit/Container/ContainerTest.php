<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Container;

use Consentful\Container\Container;
use Consentful\Container\NotFoundException;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ContainerTest extends TestCase {

	public function test_bind_builds_a_fresh_value_on_each_get(): void {
		$container = new Container();
		$container->bind( 'thing', function ( Container $c ): stdClass {
			return new stdClass();
		} );

		$first  = $container->get( 'thing' );
		$second = $container->get( 'thing' );

		$this->assertInstanceOf( stdClass::class, $first );
		$this->assertNotSame( $first, $second );
	}

	public function test_singleton_memoizes_the_same_instance(): void {
		$container = new Container();
		$container->singleton( 'thing', function ( Container $c ): stdClass {
			return new stdClass();
		} );

		$first  = $container->get( 'thing' );
		$second = $container->get( 'thing' );

		$this->assertSame( $first, $second );
	}

	public function test_factory_receives_the_container(): void {
		$container = new Container();
		$container->bind( 'self_ref', function ( Container $c ): Container {
			return $c;
		} );

		$this->assertSame( $container, $container->get( 'self_ref' ) );
	}

	public function test_instance_stores_an_already_built_value(): void {
		$container = new Container();
		$value     = new stdClass();
		$container->instance( 'thing', $value );

		$this->assertSame( $value, $container->get( 'thing' ) );
	}

	public function test_has_reports_known_and_unknown_ids(): void {
		$container = new Container();
		$container->bind( 'bound', function ( Container $c ): stdClass {
			return new stdClass();
		} );
		$container->instance( 'stored', new stdClass() );

		$this->assertTrue( $container->has( 'bound' ) );
		$this->assertTrue( $container->has( 'stored' ) );
		$this->assertFalse( $container->has( 'missing' ) );
	}

	public function test_get_throws_not_found_for_unknown_id(): void {
		$container = new Container();

		$this->expectException( NotFoundException::class );
		$container->get( 'missing' );
	}

	public function test_instance_bound_to_null_resolves_to_null_and_has_is_true(): void {
		$container = new Container();
		$container->instance( 'nothing', null );

		$this->assertTrue( $container->has( 'nothing' ) );
		$this->assertNull( $container->get( 'nothing' ) );
	}

	public function test_singleton_memoizing_null_resolves_to_null_and_has_is_true(): void {
		$container = new Container();
		$container->singleton( 'nothing', function ( Container $c ) {
			return null;
		} );

		$this->assertNull( $container->get( 'nothing' ) );
		// array_key_exists, not isset — a memoized null still counts as present.
		$this->assertTrue( $container->has( 'nothing' ) );
		$this->assertNull( $container->get( 'nothing' ) );
	}

	public function test_bind_after_singleton_demotes_to_fresh_values(): void {
		$container = new Container();
		$container->singleton( 'thing', function ( Container $c ): stdClass {
			return new stdClass();
		} );

		$shared = $container->get( 'thing' );
		$this->assertSame( $shared, $container->get( 'thing' ) );

		$container->bind( 'thing', function ( Container $c ): stdClass {
			return new stdClass();
		} );

		$first  = $container->get( 'thing' );
		$second = $container->get( 'thing' );
		$this->assertNotSame( $shared, $first );
		$this->assertNotSame( $first, $second );
	}
}

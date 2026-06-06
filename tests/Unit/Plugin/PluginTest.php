<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Plugin;

use Consentful\Adapter\AdapterRegistry;
use Consentful\Consent\PurposeRegistry;
use Consentful\Jurisdiction\JurisdictionRegistry;
use Consentful\Plugin;
use Consentful\Tag\TagRegistry;
use Consentful\Tests\Unit\Support\FakeWpdb;
use PHPUnit\Framework\TestCase;

/**
 * Plugin bootstrap: singleton, idempotent boot, and core service wiring.
 *
 * Relies on tests/bootstrap.php (CONSENTFUL_* constants) and tests/stubs.php
 * (no-op WP shims: add_action, do_action, …).
 */
final class PluginTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		// boot() wires the DB-backed Sink and runs the upgrade check. Seed a matching
		// DB version so activation is skipped, and a fake wpdb so the Sink factory
		// resolves (per-test scope; no global wpdb stub leaks across the suite).
		$GLOBALS['consentful_test_options'] = array(
			'consentful_db_version' => CONSENTFUL_DB_VERSION,
		);
		$GLOBALS['wpdb']                    = FakeWpdb::create();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['consentful_test_options'], $GLOBALS['wpdb'], $GLOBALS['consentful_test_actions'] );
		parent::tearDown();
	}

	public function test_instance_is_a_singleton(): void {
		$this->assertSame( Plugin::instance(), Plugin::instance() );
	}

	public function test_boot_is_idempotent(): void {
		$plugin = Plugin::instance();

		$plugin->boot();
		$registry = $plugin->container()->get( PurposeRegistry::class );

		// A second boot must not rebuild the singletons.
		$plugin->boot();

		$this->assertInstanceOf( PurposeRegistry::class, $registry );
		$this->assertSame( $registry, $plugin->container()->get( PurposeRegistry::class ) );
	}

	public function test_boot_wires_all_four_registries(): void {
		$plugin    = Plugin::instance();
		$plugin->boot();
		$container = $plugin->container();

		$this->assertInstanceOf( PurposeRegistry::class, $container->get( PurposeRegistry::class ) );
		$this->assertInstanceOf( JurisdictionRegistry::class, $container->get( JurisdictionRegistry::class ) );
		$this->assertInstanceOf( TagRegistry::class, $container->get( TagRegistry::class ) );
		$this->assertInstanceOf( AdapterRegistry::class, $container->get( AdapterRegistry::class ) );
	}

	public function test_registries_resolve_as_singletons(): void {
		$container = Plugin::instance()->container();
		Plugin::instance()->boot();

		$this->assertSame(
			$container->get( PurposeRegistry::class ),
			$container->get( PurposeRegistry::class )
		);
	}
}

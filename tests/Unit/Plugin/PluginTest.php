<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Plugin;

use Consentful\Activator;
use Consentful\Plugin;
use Consentful\Tests\Unit\Support\FakeWpdb;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class PluginTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['consentful_test_options'] = array(
			Activator::VERSION_OPTION => CONSENTFUL_DB_VERSION,
		);
		$GLOBALS['wpdb']                    = FakeWpdb::create();
		$GLOBALS['consentful_test_actions'] = array();

		$this->reset_singleton();
	}

	protected function tearDown(): void {
		unset(
			$GLOBALS['consentful_test_options'],
			$GLOBALS['wpdb'],
			$GLOBALS['consentful_test_actions'],
			$GLOBALS['consentful_test_is_admin']
		);
		$this->reset_singleton();
		parent::tearDown();
	}

	private function reset_singleton(): void {
		$property = ( new ReflectionClass( Plugin::class ) )->getProperty( 'instance' );
		$property->setValue( null, null );
	}

	/** @return list<array{hook: string, priority: int}> */
	private function recorded_actions(): array {
		$actions = $GLOBALS['consentful_test_actions'] ?? array();
		if ( ! is_array( $actions ) ) {
			return array();
		}
		$out = array();
		foreach ( $actions as $action ) {
			if ( is_array( $action ) ) {
				$hook     = $action['hook'] ?? '';
				$priority = $action['priority'] ?? 0;
				$out[]    = array(
					'hook'     => is_scalar( $hook ) ? (string) $hook : '',
					'priority' => is_numeric( $priority ) ? (int) $priority : 0,
				);
			}
		}
		return $out;
	}

	public function test_instance_is_a_singleton(): void {
		$this->assertSame( Plugin::instance(), Plugin::instance() );
	}

	public function test_boot_registers_the_gate_and_purge_hooks(): void {
		Plugin::instance()->boot();

		$actions = $this->recorded_actions();
		$this->assertContains(
			array(
				'hook'     => 'wp_head',
				'priority' => 1,
			),
			$actions
		);
		$hooks = array_column( $actions, 'hook' );
		$this->assertContains( 'wp_enqueue_scripts', $hooks );
		$this->assertContains( Activator::PURGE_HOOK, $hooks );
	}

	public function test_boot_registers_the_textdomain_on_init(): void {
		Plugin::instance()->boot();

		$this->assertContains( 'init', array_column( $this->recorded_actions(), 'hook' ) );
	}

	/** @return list<mixed> */
	private function recorded_textdomains(): array {
		$loaded = $GLOBALS['consentful_test_textdomains'] ?? array();
		return is_array( $loaded ) ? array_values( $loaded ) : array();
	}

	public function test_load_textdomain_loads_the_bundled_languages(): void {
		$GLOBALS['consentful_test_textdomains'] = array();

		Plugin::instance()->load_textdomain();

		$loaded = $this->recorded_textdomains();
		$this->assertCount( 1, $loaded );
		$entry = $loaded[0];
		$this->assertIsArray( $entry );
		$this->assertSame( 'consentful', $entry['domain'] );
		$this->assertIsString( $entry['path'] );
		$this->assertStringEndsWith( '/languages', $entry['path'] );
		unset( $GLOBALS['consentful_test_textdomains'] );
	}

	public function test_boot_is_idempotent(): void {
		$plugin = Plugin::instance();

		$plugin->boot();
		$first = count( $this->recorded_actions() );

		$plugin->boot();
		$second = count( $this->recorded_actions() );

		$this->assertSame( $first, $second );
	}

	public function test_boot_defers_admin_registration_to_init_in_admin_context(): void {
		$GLOBALS['consentful_test_is_admin'] = true;

		Plugin::instance()->boot();

		$hooks = array_column( $this->recorded_actions(), 'hook' );
		$this->assertNotContains( 'admin_menu', $hooks );
		$this->assertCount( 2, array_keys( $hooks, 'init', true ) );
	}

	public function test_register_admin_registers_the_admin_hooks(): void {
		Plugin::instance()->register_admin();

		$hooks = array_column( $this->recorded_actions(), 'hook' );
		$this->assertContains( 'admin_menu', $hooks );
		$this->assertContains( 'admin_init', $hooks );
	}

	public function test_boot_skips_the_admin_hooks_outside_admin_context(): void {
		Plugin::instance()->boot();

		$hooks = array_column( $this->recorded_actions(), 'hook' );
		$this->assertNotContains( 'admin_menu', $hooks );
		$this->assertNotContains( 'admin_init', $hooks );
		$this->assertCount( 1, array_keys( $hooks, 'init', true ) );
	}
}

<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Admin;

use Consentful\Admin\Admin;
use Consentful\Admin\ConsentLogReader;
use Consentful\Admin\Settings;
use Consentful\Container\Container;
use Consentful\Frontend\BannerConfig;
use Consentful\Tag\TagRegistry;
use Consentful\Tests\Unit\Support\FakeWpdb;
use PHPUnit\Framework\TestCase;

/**
 * Admin is the thin WP shell of the constrained Site-owner UI: it registers the menu,
 * settings and export hooks (recorder-tested), the menu carries the `manage_options`
 * capability on every screen, and the export data path (`export_csv_body`) builds CSV from
 * the reader + exporter. The render/header-send shells stay out of the tested core.
 */
final class AdminTest extends TestCase {

	public static function setUpBeforeClass(): void {
		if ( ! defined( 'CONSENTFUL_OPTION' ) ) {
			define( 'CONSENTFUL_OPTION', 'consentful_settings' );
		}
	}

	protected function tearDown(): void {
		unset(
			$GLOBALS['consentful_test_actions'],
			$GLOBALS['consentful_test_menus'],
			$GLOBALS['consentful_test_settings'],
			$GLOBALS['consentful_test_enqueues'],
			$GLOBALS['consentful_test_styles'],
			$GLOBALS['consentful_test_inline_scripts']
		);
		parent::tearDown();
	}

	/**
	 * Narrow a recorder global to a list of associative arrays for typed offset access.
	 *
	 * @return list<array<array-key, mixed>>
	 */
	private function recorded( string $key ): array {
		$entries = $GLOBALS[ $key ] ?? array();
		$this->assertIsArray( $entries );

		$out = array();
		foreach ( $entries as $entry ) {
			$this->assertIsArray( $entry );
			$out[] = $entry;
		}
		return $out;
	}

	private function container( ?ConsentLogReader $reader = null, ?TagRegistry $tags = null ): Container {
		$container = new Container();
		$container->instance( TagRegistry::class, $tags ?? new TagRegistry() );
		$container->instance( BannerConfig::class, BannerConfig::defaults() );
		$container->instance( Settings::class, new Settings( array(), array() ) );
		$container->instance( ConsentLogReader::class, $reader ?? new ConsentLogReader( FakeWpdb::create(), 'wp_consentful_consent_log' ) );
		return $container;
	}

	public function test_register_records_the_admin_hooks(): void {
		$GLOBALS['consentful_test_actions'] = array();

		Admin::for_container( $this->container() )->register();

		$hooks = array_column( $this->recorded( 'consentful_test_actions' ), 'hook' );
		$this->assertContains( 'admin_menu', $hooks );
		$this->assertContains( 'admin_init', $hooks );
		$this->assertContains( 'admin_enqueue_scripts', $hooks );
		$this->assertContains( 'admin_post_consentful_export', $hooks );
	}

	public function test_enqueue_assets_loads_the_color_picker_on_the_settings_screen_only(): void {
		$GLOBALS['consentful_test_menus']          = array();
		$GLOBALS['consentful_test_enqueues']       = array();
		$GLOBALS['consentful_test_styles']         = array();
		$GLOBALS['consentful_test_inline_scripts'] = array();

		$admin = Admin::for_container( $this->container() );
		// register_menu records the settings-page hook (the stub returns the menu slug).
		$admin->register_menu();

		// A foreign admin screen enqueues nothing.
		$admin->enqueue_assets( 'edit.php' );
		$this->assertSame( array(), $this->recorded( 'consentful_test_styles' ) );
		$this->assertSame( array(), $this->recorded( 'consentful_test_inline_scripts' ) );

		// Our settings screen loads the color-picker style + script and the Iris init.
		$admin->enqueue_assets( 'consentful' );
		$this->assertContains( array( 'wp-color-picker' ), $this->recorded( 'consentful_test_styles' ) );
		$this->assertContains( array( 'wp-color-picker' ), $this->recorded( 'consentful_test_enqueues' ) );
		$this->assertCount( 1, $this->recorded( 'consentful_test_inline_scripts' ) );
	}

	public function test_register_menu_records_pages_with_manage_options(): void {
		$GLOBALS['consentful_test_menus'] = array();

		Admin::for_container( $this->container() )->register_menu();

		$menus = $this->recorded( 'consentful_test_menus' );
		$this->assertCount( 3, $menus );
		foreach ( $menus as $menu ) {
			$this->assertSame( 'manage_options', $menu['capability'] );
		}
		$this->assertSame( 'menu', $menus[0]['type'] );
		$this->assertSame( 'consentful', $menus[0]['slug'] );
		$this->assertSame( 'submenu', $menus[2]['type'] );
		$this->assertSame( 'consentful-log', $menus[2]['slug'] );
	}

	public function test_register_settings_uses_the_pure_sanitize_callback(): void {
		$GLOBALS['consentful_test_settings'] = array();

		Admin::for_container( $this->container() )->register_settings();

		$registered = $this->recorded( 'consentful_test_settings' )[0];
		$this->assertSame( 'consentful', $registered['group'] );
		$this->assertSame( 'consentful_settings', $registered['name'] );

		// The callback delegates to Settings::sanitize (drops unknown keys).
		$this->assertIsArray( $registered['args'] );
		$callback = $registered['args']['sanitize_callback'];
		$this->assertIsCallable( $callback );
		$this->assertSame(
			array( 'enabled' => true ),
			$callback(
				array(
					'enabled' => '1',
					'evil'    => 'x',
				)
			)
		);
	}

	public function test_export_csv_body_builds_csv_from_the_reader_and_exporter(): void {
		$db          = FakeWpdb::create();
		$db->results = array(
			array(
				'consent_id'     => 'cid-1',
				'created_at'     => '2024-12-05 12:00:00',
				'jurisdiction'   => 'US',
				'policy_version' => 1,
				'schema_version' => 1,
				'banner_version' => 1,
				'purposes'       => '{"necessary":true}',
				'ip_hash'        => 'iphash',
				'ua_hash'        => '',
			),
		);
		$reader = new ConsentLogReader( $db, 'wp_consentful_consent_log' );

		$csv = Admin::for_container( $this->container( $reader ) )->export_csv_body();

		$this->assertStringContainsString( '"consent_id"', $csv );
		$this->assertStringContainsString( '"cid-1"', $csv );
		$this->assertStringContainsString( '"necessary=1"', $csv );
	}
}

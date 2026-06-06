<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Frontend;

use Consentful\Adapter\AdapterRegistry;
use Consentful\Adapter\GoogleAdapter;
use Consentful\Consent\ProofConfig;
use Consentful\Consent\PurposeRegistry;
use Consentful\Container\Container;
use Consentful\Frontend\BannerConfig;
use Consentful\Frontend\Gate;
use Consentful\Frontend\GeoConfig;
use Consentful\Frontend\Manifest;
use Consentful\Jurisdiction\JurisdictionRegistry;
use Consentful\Tag\TagRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Gate is the only WP-coupled class: it registers hooks, prints the config bridge +
 * inlined decider into the head, and enqueues the hashed gate bundle. It must emit
 * identical HTML for every Visitor and fail safe (no output, no fatal) when build
 * artifacts are missing.
 */
final class GateTest extends TestCase {

	/** @var list<string> */
	private array $temp_paths = array();

	public static function setUpBeforeClass(): void {
		if ( ! defined( 'CONSENTFUL_VERSION' ) ) {
			define( 'CONSENTFUL_VERSION', '1.0.0' );
		}
	}

	protected function tearDown(): void {
		foreach ( $this->temp_paths as $path ) {
			if ( is_file( $path . '/decider.js' ) ) {
				unlink( $path . '/decider.js' );
			}
			if ( is_file( $path . '/.vite/manifest.json' ) ) {
				unlink( $path . '/.vite/manifest.json' );
			}
			if ( is_dir( $path . '/.vite' ) ) {
				rmdir( $path . '/.vite' );
			}
			if ( is_dir( $path ) ) {
				rmdir( $path );
			}
		}
		$this->temp_paths = array();
		unset( $GLOBALS['consentful_test_actions'], $GLOBALS['consentful_test_enqueues'], $GLOBALS['consentful_test_styles'] );
		parent::tearDown();
	}

	private function build_dir(): string {
		$dir = sys_get_temp_dir() . '/consentful-gate-' . uniqid();
		mkdir( $dir );
		$this->temp_paths[] = $dir;
		return $dir;
	}

	private function write_decider( string $dir, string $js ): void {
		file_put_contents( $dir . '/decider.js', $js );
	}

	private function write_manifest( string $dir, string $json ): string {
		mkdir( $dir . '/.vite' );
		file_put_contents( $dir . '/.vite/manifest.json', $json );
		return $dir . '/.vite/manifest.json';
	}

	private function container(): Container {
		$container = new Container();
		$container->instance( PurposeRegistry::class, PurposeRegistry::with_defaults() );
		$container->instance( TagRegistry::class, new TagRegistry() );
		$adapters = new AdapterRegistry();
		$adapters->add( new GoogleAdapter( array( 'G-XXXXXXX' ) ) );
		$container->instance( AdapterRegistry::class, $adapters );
		$container->instance( JurisdictionRegistry::class, JurisdictionRegistry::with_defaults( 1 ) );
		$container->instance( BannerConfig::class, BannerConfig::defaults() );
		$container->instance( GeoConfig::class, GeoConfig::defaults() );
		$container->instance( ProofConfig::class, ProofConfig::defaults() );
		return $container;
	}

	/**
	 * The actions recorded by the add_action stub for this test.
	 *
	 * @return list<mixed>
	 */
	private function recorded_actions(): array {
		$actions = $GLOBALS['consentful_test_actions'] ?? array();
		return is_array( $actions ) ? array_values( $actions ) : array();
	}

	/**
	 * The enqueues recorded by the wp_enqueue_script stub for this test.
	 *
	 * @return list<array<int, mixed>>
	 */
	private function recorded_enqueues(): array {
		$enqueues = $GLOBALS['consentful_test_enqueues'] ?? array();
		if ( ! is_array( $enqueues ) ) {
			return array();
		}
		$out = array();
		foreach ( $enqueues as $enqueue ) {
			$out[] = is_array( $enqueue ) ? array_values( $enqueue ) : array();
		}
		return $out;
	}

	/**
	 * The styles recorded by the wp_enqueue_style stub for this test.
	 *
	 * @return list<array<int, mixed>>
	 */
	private function recorded_styles(): array {
		$styles = $GLOBALS['consentful_test_styles'] ?? array();
		if ( ! is_array( $styles ) ) {
			return array();
		}
		$out = array();
		foreach ( $styles as $style ) {
			$out[] = is_array( $style ) ? array_values( $style ) : array();
		}
		return $out;
	}

	private function gate( string $dir, string $manifest_path ): Gate {
		return new Gate(
			$this->container(),
			new Manifest( $manifest_path ),
			$dir,
			'http://example.test/wp-content/plugins/consentful/build',
			1,
			1,
		);
	}

	public function test_register_hooks_head_at_priority_one_and_enqueue(): void {
		$GLOBALS['consentful_test_actions'] = array();
		$dir = $this->build_dir();

		$this->gate( $dir, $dir . '/.vite/manifest.json' )->register();

		$actions = $this->recorded_actions();
		$this->assertContains(
			array(
				'hook'     => 'wp_head',
				'priority' => 1,
			),
			$actions
		);
		$this->assertContains( 'wp_enqueue_scripts', array_column( $actions, 'hook' ) );
	}

	public function test_print_head_emits_config_and_inlined_decider(): void {
		$dir = $this->build_dir();
		$this->write_decider( $dir, 'window.__consentfulDecider = true;' );

		ob_start();
		$this->gate( $dir, $dir . '/.vite/manifest.json' )->print_head();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'window.consentfulConfig = {', $output );
		$this->assertStringContainsString( '"cookie":"consentful"', $output );
		$this->assertStringContainsString( '"defaultJurisdiction":"*"', $output );
		$this->assertStringContainsString( '"jurisdictions":', $output );
		$this->assertStringContainsString( '"geo":', $output );
		// The proof block carries the non-cached consent endpoint (config, not markup).
		$this->assertStringContainsString( '"proof":{"enabled":true', $output );
		$this->assertStringContainsString( '\/wp-json\/consentful\/v1\/consent', $output );
		$this->assertStringContainsString( '"google":{', $output );
		$this->assertStringContainsString( 'window.__consentfulDecider = true;', $output );
	}

	public function test_print_head_is_identical_for_every_visitor(): void {
		$dir = $this->build_dir();
		$this->write_decider( $dir, 'window.__consentfulDecider = true;' );

		ob_start();
		$this->gate( $dir, $dir . '/.vite/manifest.json' )->print_head();
		$first = (string) ob_get_clean();

		// A second render with the same wiring must be byte-identical (no per-visitor
		// variance — the cache-safety guarantee).
		ob_start();
		$this->gate( $dir, $dir . '/.vite/manifest.json' )->print_head();
		$second = (string) ob_get_clean();

		$this->assertSame( $first, $second );
	}

	public function test_print_head_emits_config_but_no_decider_when_file_missing(): void {
		$dir = $this->build_dir();
		// No decider written.

		ob_start();
		$this->gate( $dir, $dir . '/.vite/manifest.json' )->print_head();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'window.consentfulConfig = {', $output );
		// Exactly one inline script tag (the config), no decider tag.
		$this->assertSame( 1, substr_count( $output, '<script>' ) );
	}

	public function test_enqueue_uses_the_manifest_hashed_path(): void {
		$GLOBALS['consentful_test_enqueues'] = array();
		$dir = $this->build_dir();
		$manifest_path = $this->write_manifest(
			$dir,
			(string) wp_json_encode(
				array( 'assets/gate.js' => array( 'file' => 'assets/gate.abc123.js' ) )
			)
		);

		$this->gate( $dir, $manifest_path )->enqueue();

		$enqueues = $this->recorded_enqueues();
		$this->assertCount( 1, $enqueues );
		$enqueue = $enqueues[0];
		$this->assertSame( 'consentful-gate', $enqueue[0] );
		$this->assertSame(
			'http://example.test/wp-content/plugins/consentful/build/assets/gate.abc123.js',
			$enqueue[1]
		);
		$this->assertSame( array( 'in_footer' => true ), $enqueue[4] );
	}

	public function test_enqueue_emits_nothing_when_manifest_entry_absent(): void {
		$GLOBALS['consentful_test_enqueues'] = array();
		$dir = $this->build_dir();

		// No manifest file at all.
		$this->gate( $dir, $dir . '/.vite/manifest.json' )->enqueue();

		$this->assertSame( array(), $this->recorded_enqueues() );
	}

	public function test_enqueue_registers_the_aggregated_banner_stylesheet(): void {
		$GLOBALS['consentful_test_styles'] = array();
		$dir = $this->build_dir();
		$manifest_path = $this->write_manifest(
			$dir,
			(string) wp_json_encode(
				array(
					'assets/gate.js' => array( 'file' => 'assets/gate.abc123.js' ),
					'style.css'      => array(
						'file' => 'assets/style.def456.css',
						'src'  => 'style.css',
					),
				)
			)
		);

		$this->gate( $dir, $manifest_path )->enqueue();

		$styles = $this->recorded_styles();
		$this->assertCount( 1, $styles );
		$style = $styles[0];
		$this->assertSame( 'consentful-banner', $style[0] );
		$this->assertSame(
			'http://example.test/wp-content/plugins/consentful/build/assets/style.def456.css',
			$style[1]
		);
	}

	public function test_enqueue_emits_no_style_when_the_stylesheet_is_absent(): void {
		$GLOBALS['consentful_test_styles'] = array();
		$dir = $this->build_dir();
		$manifest_path = $this->write_manifest(
			$dir,
			(string) wp_json_encode(
				array( 'assets/gate.js' => array( 'file' => 'assets/gate.abc123.js' ) )
			)
		);

		$this->gate( $dir, $manifest_path )->enqueue();

		$this->assertSame( array(), $this->recorded_styles() );
	}
}

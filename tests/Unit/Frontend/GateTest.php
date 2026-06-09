<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Frontend;

use Consentful\Frontend\BannerConfig;
use Consentful\Frontend\Gate;
use Consentful\Frontend\Manifest;
use PHPUnit\Framework\TestCase;

final class GateTest extends TestCase {

	/** @var list<string> */
	private array $temp_paths = array();

	public static function setUpBeforeClass(): void {
		if ( ! defined( 'CONSENTFUL_VERSION' ) ) {
			define( 'CONSENTFUL_VERSION', '1.0.0' );
		}
		if ( ! defined( 'CONSENTFUL_OPTION' ) ) {
			define( 'CONSENTFUL_OPTION', 'consentful_settings' );
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
		unset(
			$GLOBALS['consentful_test_actions'],
			$GLOBALS['consentful_test_enqueues'],
			$GLOBALS['consentful_test_styles'],
			$GLOBALS['consentful_test_privacy_url'],
			$GLOBALS['consentful_test_options']
		);
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

	/** @param array<string, mixed> $settings */
	private function seed_settings( array $settings ): void {
		$GLOBALS['consentful_test_options'] = array( CONSENTFUL_OPTION => $settings );
	}

	/** @return list<mixed> */
	private function recorded_actions(): array {
		$actions = $GLOBALS['consentful_test_actions'] ?? array();
		return is_array( $actions ) ? array_values( $actions ) : array();
	}

	/** @return list<array<int, mixed>> */
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

	/** @return list<array<int, mixed>> */
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
		$this->seed_settings(
			array(
				'tags' => array(
					array(
						'id'       => 'ga4',
						'catalog'  => 'ga4',
						'purposes' => array( 'analytics' ),
						'fields'   => array( 'measurementId' => 'G-XXXXXXX' ),
					),
				),
			)
		);

		ob_start();
		$this->gate( $dir, $dir . '/.vite/manifest.json' )->print_head();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'window.consentfulConfig = {', $output );
		$this->assertStringContainsString( '"cookie":"consentful"', $output );
		$this->assertStringContainsString( '"defaultJurisdiction":"*"', $output );
		$this->assertStringContainsString( '"jurisdictions":', $output );
		$this->assertStringContainsString( '"geo":', $output );
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

		ob_start();
		$this->gate( $dir, $dir . '/.vite/manifest.json' )->print_head();
		$second = (string) ob_get_clean();

		$this->assertSame( $first, $second );
	}

	public function test_print_head_emits_config_but_no_decider_when_file_missing(): void {
		$dir = $this->build_dir();

		ob_start();
		$this->gate( $dir, $dir . '/.vite/manifest.json' )->print_head();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'window.consentfulConfig = {', $output );
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

	public function test_print_head_applies_a_saved_banner_override(): void {
		$dir = $this->build_dir();
		$this->seed_settings(
			array(
				'banner' => array(
					'position'     => 'modal',
					'primaryColor' => '#ff0000',
				),
			)
		);

		ob_start();
		$this->gate( $dir, $dir . '/.vite/manifest.json' )->print_head();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '"position":"modal"', $output );
		$this->assertStringContainsString( '"primaryColor":"#ff0000"', $output );
	}

	public function test_print_head_emits_a_tag_enabled_in_settings(): void {
		$dir = $this->build_dir();
		$this->seed_settings(
			array(
				'tags' => array(
					array(
						'id'       => 'meta-pixel',
						'catalog'  => 'meta-pixel',
						'enabled'  => true,
						'purposes' => array( 'marketing' ),
						'fields'   => array( 'pixelId' => '123456' ),
					),
				),
			)
		);

		ob_start();
		$this->gate( $dir, $dir . '/.vite/manifest.json' )->print_head();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '"id":"meta-pixel"', $output );
		$this->assertStringContainsString( '"meta-pixel":{', $output );
	}

	public function test_print_head_omits_a_tag_disabled_in_settings(): void {
		$dir = $this->build_dir();
		$this->seed_settings(
			array(
				'tags' => array(
					array(
						'id'       => 'meta-pixel',
						'catalog'  => 'meta-pixel',
						'enabled'  => false,
						'purposes' => array( 'marketing' ),
						'fields'   => array( 'pixelId' => '123456' ),
					),
				),
			)
		);

		ob_start();
		$this->gate( $dir, $dir . '/.vite/manifest.json' )->print_head();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '"tags":[]', $output );
		$this->assertStringNotContainsString( '"id":"meta-pixel"', $output );
	}

	public function test_print_head_with_settings_is_identical_for_every_visitor(): void {
		$dir = $this->build_dir();
		$this->write_decider( $dir, 'window.__consentfulDecider = true;' );
		$this->seed_settings( array( 'banner' => array( 'theme' => 'dark' ) ) );

		ob_start();
		$this->gate( $dir, $dir . '/.vite/manifest.json' )->print_head();
		$first = (string) ob_get_clean();

		ob_start();
		$this->gate( $dir, $dir . '/.vite/manifest.json' )->print_head();
		$second = (string) ob_get_clean();

		$this->assertSame( $first, $second );
		$this->assertStringContainsString( '"theme":"dark"', $first );
	}

	public function test_print_head_falls_back_to_the_site_privacy_page_when_blank(): void {
		$GLOBALS['consentful_test_privacy_url'] = 'https://example.test/privacy';
		$dir = $this->build_dir();

		ob_start();
		$this->gate( $dir, $dir . '/.vite/manifest.json' )->print_head();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '"privacyUrl":"https:\/\/example.test\/privacy"', $output );
	}

	public function test_print_head_keeps_an_explicit_privacy_url_over_the_site_default(): void {
		$GLOBALS['consentful_test_privacy_url'] = 'https://example.test/wp-privacy';
		$dir = $this->build_dir();
		$this->seed_settings( array( 'banner' => array( 'privacyUrl' => 'https://example.test/custom' ) ) );

		ob_start();
		$this->gate( $dir, $dir . '/.vite/manifest.json' )->print_head();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '"privacyUrl":"https:\/\/example.test\/custom"', $output );
		$this->assertStringNotContainsString( 'wp-privacy', $output );
	}

	public function test_print_head_degrades_to_today_with_no_saved_settings(): void {
		$dir = $this->build_dir();

		ob_start();
		$this->gate( $dir, $dir . '/.vite/manifest.json' )->print_head();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '"purposes":[{"key":"necessary","alwaysOn":true},{"key":"functional","alwaysOn":false},{"key":"analytics","alwaysOn":false},{"key":"marketing","alwaysOn":false}]', $output );
		$this->assertStringContainsString( '"defaultJurisdiction":"*"', $output );
		$this->assertStringContainsString( '"tags":[]', $output );
		$banner = (string) wp_json_encode( BannerConfig::defaults()->to_array() );
		$this->assertStringContainsString( '"banner":' . $banner, $output );
	}
}

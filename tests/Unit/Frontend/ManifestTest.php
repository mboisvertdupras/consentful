<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Frontend;

use Consentful\Frontend\Manifest;
use PHPUnit\Framework\TestCase;

final class ManifestTest extends TestCase {

	/** @var list<string> */
	private array $temp_files = array();

	protected function tearDown(): void {
		foreach ( $this->temp_files as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
		$this->temp_files = array();
		parent::tearDown();
	}

	private function write_fixture( string $contents ): string {
		$path = tempnam( sys_get_temp_dir(), 'consentful-manifest-' );
		if ( false === $path ) {
			$this->fail( 'Could not create a temp fixture.' );
		}
		file_put_contents( $path, $contents );
		$this->temp_files[] = $path;
		return $path;
	}

	public function test_resolves_a_hashed_path_for_a_known_entry(): void {
		$path = $this->write_fixture(
			(string) wp_json_encode(
				array(
					'assets/gate.js' => array(
						'file'    => 'assets/gate.abc123.js',
						'name'    => 'gate',
						'src'     => 'assets/gate.js',
						'isEntry' => true,
					),
				)
			)
		);

		$manifest = new Manifest( $path );

		$this->assertSame( 'assets/gate.abc123.js', $manifest->path_for( 'assets/gate.js' ) );
	}

	public function test_unknown_entry_is_null(): void {
		$path = $this->write_fixture(
			(string) wp_json_encode(
				array(
					'assets/gate.js' => array( 'file' => 'assets/gate.abc123.js' ),
				)
			)
		);

		$manifest = new Manifest( $path );

		$this->assertNull( $manifest->path_for( 'assets/missing.js' ) );
	}

	public function test_missing_file_is_null_without_throwing(): void {
		$manifest = new Manifest( sys_get_temp_dir() . '/consentful-does-not-exist-' . uniqid() . '.json' );

		$this->assertNull( $manifest->path_for( 'assets/gate.js' ) );
	}

	public function test_malformed_json_is_null_without_throwing(): void {
		$path = $this->write_fixture( '{ this is not valid json' );

		$manifest = new Manifest( $path );

		$this->assertNull( $manifest->path_for( 'assets/gate.js' ) );
	}

	public function test_entry_without_a_file_key_is_null(): void {
		$path = $this->write_fixture(
			(string) wp_json_encode(
				array(
					'assets/gate.js' => array( 'name' => 'gate' ),
				)
			)
		);

		$manifest = new Manifest( $path );

		$this->assertNull( $manifest->path_for( 'assets/gate.js' ) );
	}

	public function test_non_string_file_value_is_null(): void {
		$path = $this->write_fixture(
			(string) wp_json_encode(
				array(
					'assets/gate.js' => array( 'file' => array( 'nested' ) ),
				)
			)
		);

		$manifest = new Manifest( $path );

		$this->assertNull( $manifest->path_for( 'assets/gate.js' ) );
	}

	public function test_resolves_the_aggregated_stylesheet_by_its_style_key(): void {
		$path = $this->write_fixture(
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

		$manifest = new Manifest( $path );

		$this->assertSame( 'assets/style.def456.css', $manifest->path_for( 'style.css' ) );
	}

	public function test_decode_is_memoized_across_calls(): void {
		$path = $this->write_fixture(
			(string) wp_json_encode(
				array(
					'assets/gate.js' => array( 'file' => 'assets/gate.abc123.js' ),
				)
			)
		);

		$manifest = new Manifest( $path );
		$this->assertSame( 'assets/gate.abc123.js', $manifest->path_for( 'assets/gate.js' ) );

		unlink( $path );
		$this->assertSame( 'assets/gate.abc123.js', $manifest->path_for( 'assets/gate.js' ) );
	}
}

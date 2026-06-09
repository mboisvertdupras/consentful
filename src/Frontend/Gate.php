<?php
declare( strict_types = 1 );

namespace Consentful\Frontend;

use Consentful\Adapter\Adapter;
use Consentful\Admin\Settings;
use Consentful\Catalog\Catalog;
use Consentful\Consent\Purpose;
use Consentful\Tag\Tag;

final class Gate {

	private const HANDLE       = 'consentful-gate';
	private const STYLE_HANDLE = 'consentful-banner';
	private const GATE_ENTRY   = 'assets/gate.js';
	private const STYLE_ENTRY  = 'style.css';
	private const DECIDER_FILE = 'decider.js';

	public function __construct(
		private readonly Manifest $manifest,
		private readonly string $build_dir,
		private readonly string $build_url,
		private readonly int $schema_version,
		private readonly int $policy_version,
		private readonly string $cookie = 'consentful',
	) {}

	public function register(): void {
		add_action( 'wp_head', array( $this, 'print_head' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function print_head(): void {
		$config = $this->config()->to_array();
		$json   = wp_json_encode( $config );
		if ( false === $json ) {
			return;
		}

		wp_print_inline_script_tag( 'window.consentfulConfig = ' . $json . ';' );

		$decider = $this->decider_contents();
		if ( null !== $decider ) {
			wp_print_inline_script_tag( $decider );
		}
	}

	public function enqueue(): void {
		$path = $this->manifest->path_for( self::GATE_ENTRY );
		if ( null !== $path ) {
			wp_enqueue_script(
				self::HANDLE,
				rtrim( $this->build_url, '/' ) . '/' . ltrim( $path, '/' ),
				array(),
				CONSENTFUL_VERSION,
				array( 'in_footer' => true )
			);
		}

		$css = $this->manifest->path_for( self::STYLE_ENTRY );
		if ( null !== $css ) {
			wp_enqueue_style(
				self::STYLE_HANDLE,
				rtrim( $this->build_url, '/' ) . '/' . ltrim( $css, '/' ),
				array(),
				CONSENTFUL_VERSION
			);
		}
	}

	private function config(): ClientConfig {
		$hydrator = new SettingsHydrator(
			Settings::from_wp()->effective(),
			Catalog::with_defaults(),
			$this->extra_purposes(),
			$this->extra_adapters(),
			$this->extra_tags(),
		);

		return $hydrator->client_config(
			$this->schema_version,
			$this->policy_version,
			$this->cookie,
			rest_url( 'consentful/v1/geo' ),
			rest_url( 'consentful/v1/consent' ),
			get_privacy_policy_url(),
		);
	}

	/** @return list<Purpose> */
	private function extra_purposes(): array {
		/** @var mixed $filtered */
		$filtered = apply_filters( 'consentful_purposes', array() );
		return self::instances_of( $filtered, Purpose::class );
	}

	/** @return list<Adapter> */
	private function extra_adapters(): array {
		/** @var mixed $filtered */
		$filtered = apply_filters( 'consentful_adapters', array() );
		return self::instances_of( $filtered, Adapter::class );
	}

	/** @return list<Tag> */
	private function extra_tags(): array {
		/** @var mixed $filtered */
		$filtered = apply_filters( 'consentful_tags', array() );
		return self::instances_of( $filtered, Tag::class );
	}

	/**
	 * @template T of object
	 * @param  class-string<T> $type
	 * @return list<T>
	 */
	private static function instances_of( mixed $value, string $type ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $item ) {
			if ( $item instanceof $type ) {
				$out[] = $item;
			}
		}
		return $out;
	}

	private function decider_contents(): ?string {
		$path = rtrim( $this->build_dir, '/' ) . '/' . self::DECIDER_FILE;
		if ( ! is_readable( $path ) ) {
			return null;
		}

		$lines = file( $path, FILE_IGNORE_NEW_LINES );
		return false === $lines ? null : implode( "\n", $lines );
	}
}

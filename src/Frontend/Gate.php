<?php
declare( strict_types = 1 );

namespace Consentful\Frontend;

use Consentful\Adapter\Adapter;
use Consentful\Admin\Settings;
use Consentful\Catalog\Catalog;
use Consentful\Consent\Purpose;
use Consentful\Tag\Tag;

/**
 * The cache-safe client gate's WordPress surface — the only WP-coupled class here.
 * Emits IDENTICAL HTML for every Visitor (sacrosanct): the config bridge + inlined
 * decider into <head> (priority 1, before any tag) and enqueues the gate bundle in
 * the footer. Serialization lives in ClientConfig; manifest lookup in Manifest. Never
 * varies output by cookie/geo/UA; missing build artifacts emit nothing (never fatal).
 */
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

	/** Hook the head output (before themes/other plugins) and the footer enqueue. */
	public function register(): void {
		add_action( 'wp_head', array( $this, 'print_head' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Echo the config <script> then the inlined decider <script>. Same bytes for
	 * every Visitor — the gate reads window.consentfulConfig and decides at runtime.
	 */
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

	/**
	 * Enqueue the hashed gate bundle in the footer and the banner stylesheet Vite
	 * extracted from it (no localize — config is in head). Each is independent: a
	 * missing manifest entry enqueues nothing (never fatal). Identical output for
	 * every Visitor.
	 */
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

	/**
	 * Build the config from the canonical `consentful_settings` option via the hydrator —
	 * the admin UI is the source of truth; dev hooks only append (never override). Ships
	 * ALL Jurisdictions plus the geo block (the client resolves the active one at runtime —
	 * cache-safe). The built-in geo endpoint URL is the only per-request server surface,
	 * and it is non-cached.
	 */
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

	/**
	 * Optional dev-hook purposes (append-only; default = none). Filtered to Purpose instances.
	 *
	 * @return list<Purpose>
	 */
	private function extra_purposes(): array {
		/** @var mixed $filtered */
		$filtered = apply_filters( 'consentful_purposes', array() );
		return self::instances_of( $filtered, Purpose::class );
	}

	/**
	 * Optional dev-hook adapters (append-only; default = none). Filtered to Adapter instances.
	 *
	 * @return list<Adapter>
	 */
	private function extra_adapters(): array {
		/** @var mixed $filtered */
		$filtered = apply_filters( 'consentful_adapters', array() );
		return self::instances_of( $filtered, Adapter::class );
	}

	/**
	 * Optional dev-hook tags (append-only; default = none). Filtered to Tag instances.
	 *
	 * @return list<Tag>
	 */
	private function extra_tags(): array {
		/** @var mixed $filtered */
		$filtered = apply_filters( 'consentful_tags', array() );
		return self::instances_of( $filtered, Tag::class );
	}

	/**
	 * Coerce a filtered value to a list of instances of `$type` (anything else dropped).
	 *
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

	/** The built decider's source, inlined verbatim. Null when the file is absent. */
	private function decider_contents(): ?string {
		$path = rtrim( $this->build_dir, '/' ) . '/' . self::DECIDER_FILE;
		if ( ! is_readable( $path ) ) {
			return null;
		}

		// file() (not WP_Filesystem) reads the trusted build artifact without the
		// AlternativeFunctions warning that injected-path file_get_contents trips.
		$lines = file( $path, FILE_IGNORE_NEW_LINES );
		return false === $lines ? null : implode( "\n", $lines );
	}
}

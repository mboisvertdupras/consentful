<?php
declare( strict_types = 1 );

namespace Consentful\Frontend;

use Consentful\Adapter\AdapterRegistry;
use Consentful\Consent\PurposeRegistry;
use Consentful\Container\Container;
use Consentful\Jurisdiction\JurisdictionRegistry;
use Consentful\Tag\TagRegistry;

/**
 * The cache-safe client gate's WordPress surface — the only WP-coupled class here.
 * Emits IDENTICAL HTML for every Visitor (sacrosanct): the config bridge + inlined
 * decider into <head> (priority 1, before any tag) and enqueues the gate bundle in
 * the footer. Serialization lives in ClientConfig; manifest lookup in Manifest. Never
 * varies output by cookie/geo/UA; missing build artifacts emit nothing (never fatal).
 */
final class Gate {

	private const HANDLE       = 'consentful-gate';
	private const GATE_ENTRY   = 'assets/gate.js';
	private const DECIDER_FILE = 'decider.js';

	public function __construct(
		private readonly Container $container,
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

	/** Enqueue the hashed gate bundle in the footer (no localize — config is in head). */
	public function enqueue(): void {
		$path = $this->manifest->path_for( self::GATE_ENTRY );
		if ( null === $path ) {
			return;
		}

		wp_enqueue_script(
			self::HANDLE,
			rtrim( $this->build_url, '/' ) . '/' . ltrim( $path, '/' ),
			array(),
			CONSENTFUL_VERSION,
			array( 'in_footer' => true )
		);
	}

	/** Build the config from the live registries and the resolved (fallback) Jurisdiction. */
	private function config(): ClientConfig {
		/** @var PurposeRegistry $purposes */
		$purposes = $this->container->get( PurposeRegistry::class );
		/** @var TagRegistry $tags */
		$tags = $this->container->get( TagRegistry::class );
		/** @var AdapterRegistry $adapters */
		$adapters = $this->container->get( AdapterRegistry::class );
		/** @var JurisdictionRegistry $jurisdictions */
		$jurisdictions = $this->container->get( JurisdictionRegistry::class );

		return new ClientConfig(
			$purposes,
			$tags,
			$adapters,
			$jurisdictions->fallback(),
			$this->schema_version,
			$this->policy_version,
			cookie: $this->cookie,
		);
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

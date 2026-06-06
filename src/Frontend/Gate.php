<?php
declare( strict_types = 1 );

namespace Consentful\Frontend;

use Consentful\Adapter\AdapterRegistry;
use Consentful\Admin\Settings;
use Consentful\Consent\ProofConfig;
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
	private const STYLE_HANDLE = 'consentful-banner';
	private const GATE_ENTRY   = 'assets/gate.js';
	private const STYLE_ENTRY  = 'style.css';
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
	 * Build the config from the live registries. Ships ALL Jurisdictions plus the geo
	 * block (the client resolves the active one at runtime — cache-safe). The built-in
	 * geo endpoint URL is the only per-request server surface, and it is non-cached.
	 */
	private function config(): ClientConfig {
		/** @var PurposeRegistry $purposes */
		$purposes = $this->container->get( PurposeRegistry::class );
		/** @var TagRegistry $tags */
		$tags = $this->container->get( TagRegistry::class );
		/** @var AdapterRegistry $adapters */
		$adapters = $this->container->get( AdapterRegistry::class );
		/** @var JurisdictionRegistry $jurisdictions */
		$jurisdictions = $this->container->get( JurisdictionRegistry::class );
		/** @var BannerConfig $banner */
		$banner = $this->container->get( BannerConfig::class );
		/** @var GeoConfig $geo */
		$geo = $this->container->get( GeoConfig::class );
		/** @var ProofConfig $proof */
		$proof = $this->container->get( ProofConfig::class );
		/** @var Settings $settings */
		$settings = $this->container->get( Settings::class );

		// Overlay the Site owner's unlocked settings on the integrator's banner (Layer 1).
		// With no saved settings the overrides are empty and the result is identical to
		// today — and identical for every Visitor (settings are global, not per-visitor).
		$banner = $banner->with_overrides( $settings->banner_overrides(), Settings::locked_fields() );

		return new ClientConfig(
			$purposes,
			$tags,
			$adapters,
			$jurisdictions,
			$banner,
			$geo,
			geo_endpoint_url: rest_url( 'consentful/v1/geo' ),
			proof: $proof,
			proof_endpoint_url: rest_url( 'consentful/v1/consent' ),
			schema_version: $this->schema_version,
			policy_version: $this->policy_version,
			cookie: $this->cookie,
			hidden_tags: $this->hidden_tags( $tags, $settings ),
		);
	}

	/**
	 * The Site-owner-disabled Tag ids, intersected with the toggleable Tags — only
	 * toggleable Tags can be hidden, so a non-toggleable id in the option is ignored.
	 *
	 * @return list<string>
	 */
	private function hidden_tags( TagRegistry $tags, Settings $settings ): array {
		$disabled   = $settings->hidden_tag_ids();
		$toggleable = array();
		foreach ( $tags->all() as $tag ) {
			if ( $tag->site_toggleable ) {
				$toggleable[ $tag->id ] = true;
			}
		}

		return array_values(
			array_filter(
				$disabled,
				static fn ( string $id ): bool => isset( $toggleable[ $id ] )
			)
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

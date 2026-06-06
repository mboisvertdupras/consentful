<?php
declare( strict_types = 1 );

namespace Consentful;

use Consentful\Adapter\AdapterRegistry;
use Consentful\Consent\PurposeRegistry;
use Consentful\Container\Container;
use Consentful\Frontend\BannerConfig;
use Consentful\Frontend\Gate;
use Consentful\Frontend\Manifest;
use Consentful\Jurisdiction\JurisdictionRegistry;
use Consentful\Tag\TagRegistry;

/**
 * Singleton bootstrap. Wires the core registries into the container and lets
 * integrators register their own adapters, tags and policies via the
 * `consentful_register` hook (the source of truth).
 */
final class Plugin {

	private Container $container;

	private bool $booted = false;

	private static ?self $instance = null;

	private function __construct() {
		$this->container = new Container();
	}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function container(): Container {
		return $this->container;
	}

	/**
	 * Boot the plugin. Idempotent — wiring runs at most once.
	 *
	 * Fires `consentful_register` (on plugins_loaded) handing the integrator the
	 * DI container — a trusted, integrator-only surface per the two-tier model.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		$this->register_core_services();
		$this->register_frontend_services();

		// Integrators declare adapters, tags and policy here (the source of truth).
		do_action( 'consentful_register', $this->container );

		// Register the gate last, so it observes the integrator's final wiring.
		$gate = $this->container->get( Gate::class );
		if ( $gate instanceof Gate ) {
			$gate->register();
		}
	}

	/**
	 * Bind the four core registries as container singletons.
	 */
	private function register_core_services(): void {
		$this->container->singleton(
			PurposeRegistry::class,
			static function (): PurposeRegistry {
				return PurposeRegistry::with_defaults();
			}
		);
		$this->container->singleton(
			JurisdictionRegistry::class,
			static function (): JurisdictionRegistry {
				return JurisdictionRegistry::with_defaults( CONSENTFUL_POLICY_VERSION );
			}
		);
		$this->container->singleton(
			TagRegistry::class,
			static function (): TagRegistry {
				return new TagRegistry();
			}
		);
		$this->container->singleton(
			AdapterRegistry::class,
			static function (): AdapterRegistry {
				return new AdapterRegistry();
			}
		);
	}

	/**
	 * Bind the cache-safe client gate and its Vite manifest reader. The gate is
	 * resolved and registered after `consentful_register` (see boot()).
	 */
	private function register_frontend_services(): void {
		$build_dir = plugin_dir_path( CONSENTFUL_FILE ) . 'build';
		$build_url = plugins_url( 'build', CONSENTFUL_FILE );
		$manifest  = new Manifest( $build_dir . '/.vite/manifest.json' );

		$this->container->instance( Manifest::class, $manifest );
		// Default banner copy/appearance; Integrators override this binding in
		// `consentful_register` (the Gate resolves it after that fires).
		$this->container->singleton(
			BannerConfig::class,
			static function (): BannerConfig {
				return BannerConfig::defaults();
			}
		);
		$this->container->singleton(
			Gate::class,
			static function ( Container $container ) use ( $manifest, $build_dir, $build_url ): Gate {
				return new Gate(
					$container,
					$manifest,
					$build_dir,
					$build_url,
					CONSENTFUL_SCHEMA_VERSION,
					CONSENTFUL_POLICY_VERSION,
					CONSENTFUL_COOKIE
				);
			}
		);
	}
}

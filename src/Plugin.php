<?php
declare( strict_types = 1 );

namespace Consentful;

use Consentful\Adapter\AdapterRegistry;
use Consentful\Consent\PurposeRegistry;
use Consentful\Container\Container;
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

		do_action( 'consentful_register', $this->container );
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
}

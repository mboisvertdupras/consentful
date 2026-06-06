<?php
declare( strict_types = 1 );

namespace Consentful;

use Consentful\Adapter\AdapterRegistry;
use Consentful\Admin\Admin;
use Consentful\Admin\ConsentLogReader;
use Consentful\Admin\Settings;
use Consentful\Consent\ConsentLogPurger;
use Consentful\Consent\DatabaseSink;
use Consentful\Consent\ProofConfig;
use Consentful\Consent\PurposeRegistry;
use Consentful\Consent\Sink;
use Consentful\Container\Container;
use Consentful\Frontend\BannerConfig;
use Consentful\Frontend\Gate;
use Consentful\Frontend\GeoConfig;
use Consentful\Frontend\Manifest;
use Consentful\Jurisdiction\JurisdictionRegistry;
use Consentful\Rest\ConsentController;
use Consentful\Rest\GeoController;
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

		$this->ensure_database();
		$this->register_core_services();
		$this->register_frontend_services();

		// Integrators declare adapters, tags and policy here (the source of truth).
		do_action( 'consentful_register', $this->container );

		// Register the gate last, so it observes the integrator's final wiring.
		$gate = $this->container->get( Gate::class );
		if ( $gate instanceof Gate ) {
			$gate->register();
		}

		// The constrained Site-owner admin UI, only in admin context. Built after
		// consentful_register so the integrator's locks and toggleable Tags are in effect.
		if ( is_admin() ) {
			Admin::for_container( $this->container )->register();
		}

		// The separate, non-cached endpoints (register on rest_api_init).
		( new GeoController() )->register();
		$this->consent_controller()->register();

		// The daily Consent-log retention purge (scheduled by the Activator).
		add_action( Activator::PURGE_HOOK, array( $this, 'purge_consent_log' ) );
	}

	/**
	 * The scheduled Consent-log retention purge (ADR 0002). Deletes records past the
	 * integrator-configured ProofConfig::retention_days window; a non-positive window keeps
	 * records indefinitely. Public so the cron event can invoke it.
	 */
	public function purge_consent_log(): void {
		global $wpdb;
		/** @var \wpdb $wpdb */
		/** @var ProofConfig $proof */
		$proof = $this->container->get( ProofConfig::class );

		$purger = new ConsentLogPurger( $wpdb, DatabaseSink::table_name( $wpdb ) );
		$purger->purge( $proof->retention_days, time() );
	}

	/**
	 * Lightweight upgrade guard: run activation when the recorded DB version lags the
	 * code's. Covers installs where the activation hook never fired (must-use /
	 * symlinked dev) and schema bumps. Activator::activate() is idempotent.
	 */
	private function ensure_database(): void {
		// WordPress returns scalar options as strings, so compare numerically.
		$installed = get_option( Activator::VERSION_OPTION );
		$installed = is_scalar( $installed ) ? (int) $installed : 0;
		if ( CONSENTFUL_DB_VERSION !== $installed ) {
			Activator::activate();
		}
	}

	/**
	 * Build the proof-of-consent endpoint with the bound Sink and the per-site record
	 * salt. A missing/empty salt option falls back to a generated transient so hashing
	 * always has a salt (pseudonymization must never silently no-op).
	 */
	private function consent_controller(): ConsentController {
		/** @var Sink $sink */
		$sink = $this->container->get( Sink::class );

		return new ConsentController( $sink, $this->record_salt() );
	}

	/** The persisted record salt, or a generated transient when the option is empty. */
	private function record_salt(): string {
		$salt = get_option( Activator::SALT_OPTION );
		if ( is_string( $salt ) && '' !== $salt ) {
			return $salt;
		}

		$fallback = get_transient( Activator::SALT_OPTION );
		if ( is_string( $fallback ) && '' !== $fallback ) {
			return $fallback;
		}

		$fallback = wp_generate_password( 64, true, true );
		set_transient( Activator::SALT_OPTION, $fallback, DAY_IN_SECONDS );
		return $fallback;
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
		// The built-in proof Sink: the bundled Consent log table. The wpdb coupling is
		// resolved lazily in the factory; an Integrator rebinds this to their own store.
		$this->container->singleton(
			Sink::class,
			static function (): DatabaseSink {
				global $wpdb;
				/** @var \wpdb $wpdb */
				return new DatabaseSink( $wpdb, DatabaseSink::table_name( $wpdb ) );
			}
		);
		// Proof on by default; an Integrator overrides this binding to disable it.
		$this->container->singleton(
			ProofConfig::class,
			static function (): ProofConfig {
				return ProofConfig::defaults();
			}
		);
		// The Site-owner settings layer. Resolved lazily after consentful_register, so the
		// integrator's locked_fields filter is in effect when the option is read.
		$this->container->singleton(
			Settings::class,
			static function (): Settings {
				return Settings::from_wp();
			}
		);
		// The Consent-log reader for the admin screen + CSV export. wpdb coupling resolved
		// lazily in the factory.
		$this->container->singleton(
			ConsentLogReader::class,
			static function (): ConsentLogReader {
				return ConsentLogReader::for_wp();
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
		// Default geo resolution (degrades to today's strictest fallback). Integrators
		// override this binding in `consentful_register` to wire an edge signal.
		$this->container->singleton(
			GeoConfig::class,
			static function (): GeoConfig {
				return GeoConfig::defaults();
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

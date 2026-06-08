<?php
declare( strict_types = 1 );

namespace Consentful;

use Consentful\Admin\Admin;
use Consentful\Admin\ConsentLogReader;
use Consentful\Catalog\Catalog;
use Consentful\Consent\ConsentLogPurger;
use Consentful\Consent\ConsentLogSchema;
use Consentful\Consent\DatabaseSink;
use Consentful\Consent\ProofConfig;
use Consentful\Consent\Sink;
use Consentful\Frontend\Gate;
use Consentful\Frontend\Manifest;
use Consentful\Rest\ConsentController;
use Consentful\Rest\GeoController;

/**
 * Singleton bootstrap. Wires the cache-safe gate, the admin UI and the non-cached REST
 * endpoints. The admin UI (`consentful_settings`) is the canonical config source; the
 * Gate builds its client config from it via the hydrator. Optional dev hooks
 * (`consentful_purposes`/`_adapters`/`_tags`/`_sink`) append, never override.
 */
final class Plugin {

	private bool $booted = false;

	private static ?self $instance = null;

	private function __construct() {}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Boot the plugin. Idempotent — wiring runs at most once. */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		$this->ensure_database();

		$this->gate()->register();

		if ( is_admin() ) {
			$this->admin()->register();
		}

		// The separate, non-cached endpoints (register on rest_api_init).
		( new GeoController() )->register();
		$this->consent_controller()->register();

		// The daily Consent-log retention purge (scheduled by the Activator).
		add_action( Activator::PURGE_HOOK, array( $this, 'purge_consent_log' ) );
	}

	/**
	 * The scheduled Consent-log retention purge (ADR 0002). Deletes records past the
	 * server-only ProofConfig::retention_days window; a non-positive window keeps records
	 * indefinitely. Public so the cron event can invoke it.
	 */
	public function purge_consent_log(): void {
		global $wpdb;
		/** @var \wpdb $wpdb */
		$purger = new ConsentLogPurger( $wpdb, ConsentLogSchema::table( $wpdb ) );
		$purger->purge( ProofConfig::defaults()->retention_days, time() );
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

	/** The cache-safe client gate, reading its Vite manifest from the build dir. */
	private function gate(): Gate {
		$build_dir = plugin_dir_path( CONSENTFUL_FILE ) . 'build';
		$build_url = plugins_url( 'build', CONSENTFUL_FILE );

		return new Gate(
			new Manifest( $build_dir . '/.vite/manifest.json' ),
			$build_dir,
			$build_url,
			CONSENTFUL_SCHEMA_VERSION,
			CONSENTFUL_POLICY_VERSION,
			CONSENTFUL_COOKIE
		);
	}

	/** The admin UI, fed the catalog it renders and the Consent-log reader. */
	private function admin(): Admin {
		return new Admin( Catalog::with_defaults(), ConsentLogReader::for_wp() );
	}

	/**
	 * Build the proof-of-consent endpoint with the resolved Sink and the per-site record
	 * salt. A missing/empty salt option falls back to a generated transient so hashing
	 * always has a salt (pseudonymization must never silently no-op).
	 */
	private function consent_controller(): ConsentController {
		return new ConsentController( $this->sink(), $this->record_salt() );
	}

	/**
	 * The proof Sink: the bundled Consent log table by default, replaceable by a developer
	 * via the `consentful_sink` filter. A non-Sink filter return falls back to the default.
	 */
	private function sink(): Sink {
		global $wpdb;
		/** @var \wpdb $wpdb */
		$default = new DatabaseSink( $wpdb, ConsentLogSchema::table( $wpdb ) );

		$sink = apply_filters( 'consentful_sink', $default );
		return $sink instanceof Sink ? $sink : $default;
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
}

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

		( new GeoController() )->register();
		$this->consent_controller()->register();

		add_action( Activator::PURGE_HOOK, array( $this, 'purge_consent_log' ) );
	}

	public function purge_consent_log(): void {
		global $wpdb;
		/** @var \wpdb $wpdb */
		$purger = new ConsentLogPurger( $wpdb, ConsentLogSchema::table( $wpdb ) );
		$purger->purge( ProofConfig::defaults()->retention_days, time() );
	}

	private function ensure_database(): void {
		$installed = get_option( Activator::VERSION_OPTION );
		$installed = is_scalar( $installed ) ? (int) $installed : 0;
		if ( CONSENTFUL_DB_VERSION !== $installed ) {
			Activator::activate();
		}
	}

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

	private function admin(): Admin {
		return new Admin( Catalog::with_defaults(), ConsentLogReader::for_wp() );
	}

	private function consent_controller(): ConsentController {
		return new ConsentController( $this->sink(), $this->record_salt() );
	}

	private function sink(): Sink {
		global $wpdb;
		/** @var \wpdb $wpdb */
		$default = new DatabaseSink( $wpdb, ConsentLogSchema::table( $wpdb ) );

		$sink = apply_filters( 'consentful_sink', $default );
		return $sink instanceof Sink ? $sink : $default;
	}

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

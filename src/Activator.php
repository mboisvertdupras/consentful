<?php
declare( strict_types = 1 );

namespace Consentful;

use Consentful\Consent\ConsentLogSchema;

/**
 * Plugin activation. Creates the Consent log table, ensures the per-site record salt
 * (used to pseudonymize IP/UA), and records the DB version. Idempotent — safe to run
 * on the activation hook and again from the boot-time upgrade check (covers
 * must-use / symlinked dev installs where the hook never fires).
 *
 * A thin WP-entry shell: `global $wpdb` and the option/transient writes are confined
 * here; the SQL string itself is built by the pure ConsentLogSchema.
 */
final class Activator {

	public const SALT_OPTION    = 'consentful_record_salt';
	public const VERSION_OPTION = 'consentful_db_version';
	public const PURGE_HOOK     = 'consentful_purge_consent_log';

	/** Create the table, ensure the salt, schedule the retention purge, and stamp the DB version. */
	public static function activate(): void {
		global $wpdb;
		/** @var \wpdb $wpdb */

		ConsentLogSchema::create( $wpdb );

		$salt = get_option( self::SALT_OPTION );
		if ( ! is_string( $salt ) || '' === $salt ) {
			add_option( self::SALT_OPTION, wp_generate_password( 64, true, true ) );
		}

		// Daily Consent-log retention purge (ADR 0002). Guarded so re-activation / the
		// boot-time upgrade check never double-schedule it.
		if ( false === wp_next_scheduled( self::PURGE_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::PURGE_HOOK );
		}

		update_option( self::VERSION_OPTION, CONSENTFUL_DB_VERSION );
	}
}

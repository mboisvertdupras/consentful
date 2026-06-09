<?php
declare( strict_types = 1 );

namespace Consentful;

use Consentful\Consent\ConsentLogSchema;

final class Activator {

	public const SALT_OPTION    = 'consentful_record_salt';
	public const VERSION_OPTION = 'consentful_db_version';
	public const PURGE_HOOK     = 'consentful_purge_consent_log';

	public static function activate(): void {
		global $wpdb;
		/** @var \wpdb $wpdb */

		ConsentLogSchema::create( $wpdb );

		$salt = get_option( self::SALT_OPTION );
		if ( ! is_string( $salt ) || '' === $salt ) {
			add_option( self::SALT_OPTION, wp_generate_password( 64, true, true ) );
		}

		if ( false === wp_next_scheduled( self::PURGE_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::PURGE_HOOK );
		}

		update_option( self::VERSION_OPTION, CONSENTFUL_DB_VERSION );
	}
}

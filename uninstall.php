<?php
/**
 * Uninstall cleanup. Removes every Consentful trace — the settings, the per-site record
 * salt, the DB-version marker, and the Consent log table — per site on multisite.
 *
 * Runs standalone: WordPress loads this file directly without bootstrapping the plugin, so
 * the option names and the table suffix are inlined here rather than referenced from the
 * (un-loaded) autoloaded classes.
 */

declare( strict_types = 1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove all Consentful data from the current site: its three options and the Consent log
 * table. The table name is built from the site's own prefix (never user input) and dropped
 * via a prepared `%i` identifier.
 */
function consentful_uninstall_site(): void {
	global $wpdb;
	/** @var \wpdb $wpdb */

	delete_option( 'consentful_settings' );
	delete_option( 'consentful_record_salt' );
	delete_option( 'consentful_db_version' );

	// Clear the scheduled retention purge (literal matches Activator::PURGE_HOOK — this file
	// runs standalone, without the autoloaded classes).
	wp_clear_scheduled_hook( 'consentful_purge_consent_log' );

	// The table name is built from the site's own prefix (a safe wpdb property), never user
	// input, so the DROP needs no escaping — mirrors ConsentLogSchema::drop().
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'consentful_consent_log' );
}

if ( is_multisite() ) {
	$consentful_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $consentful_site_ids as $consentful_site_id ) {
		switch_to_blog( (int) $consentful_site_id );
		consentful_uninstall_site();
		restore_current_blog();
	}
} else {
	consentful_uninstall_site();
}

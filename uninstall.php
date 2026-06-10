<?php

declare( strict_types = 1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

function consentful_uninstall_site(): void {
	global $wpdb;
	/** @var \wpdb $wpdb */

	delete_option( 'consentful_settings' );
	delete_option( 'consentful_record_salt' );
	delete_option( 'consentful_db_version' );

	wp_clear_scheduled_hook( 'consentful_purge_consent_log' );

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

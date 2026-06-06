<?php
/**
 * Cleanup on uninstall: drop the plugin's stored settings.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'consentful_settings' );

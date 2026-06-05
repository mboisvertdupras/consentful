<?php
/**
 * Cleanup on uninstall. Note: this removes the Google tag from the site — if you
 * uninstall, re-add the Google tag somewhere else (and keep it consent-gated).
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'cmv2_settings' );

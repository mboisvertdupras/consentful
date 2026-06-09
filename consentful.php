<?php
/**
 * Plugin Name:       Consentful
 * Plugin URI:        https://github.com/tamarak/consentful
 * Description:        An open-source, universal consent layer that gates all non-essential third-party tags behind visitor consent, adapts to the visitor's jurisdiction, and keeps demonstrable proof of consent.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Tamarak
 * Author URI:        https://tamarak.dev
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       consentful
 * Domain Path:       /languages
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CONSENTFUL_VERSION', '1.0.0' );
define( 'CONSENTFUL_FILE', __FILE__ );
define( 'CONSENTFUL_SCHEMA_VERSION', 1 );
define( 'CONSENTFUL_POLICY_VERSION', 1 );
define( 'CONSENTFUL_DB_VERSION', 1 );
define( 'CONSENTFUL_OPTION', 'consentful_settings' );
define( 'CONSENTFUL_COOKIE', 'consentful' );

$consentful_autoload = __DIR__ . '/vendor/autoload.php';
if ( ! is_readable( $consentful_autoload ) ) {
	return;
}
require $consentful_autoload;

if ( class_exists( \Consentful\Activator::class ) ) {
	register_activation_hook( CONSENTFUL_FILE, array( '\\Consentful\\Activator', 'activate' ) );
}
if ( class_exists( \Consentful\Deactivator::class ) ) {
	register_deactivation_hook( CONSENTFUL_FILE, array( '\\Consentful\\Deactivator', 'deactivate' ) );
}

add_action(
	'plugins_loaded',
	static function (): void {
		if ( class_exists( \Consentful\Plugin::class ) ) {
			\Consentful\Plugin::instance()->boot();
		}
	}
);

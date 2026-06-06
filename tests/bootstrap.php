<?php
declare( strict_types = 1 );

require dirname( __DIR__ ) . '/vendor/autoload.php';
require __DIR__ . '/stubs.php';

if ( ! defined( 'CONSENTFUL_FILE' ) ) {
	define( 'CONSENTFUL_FILE', dirname( __DIR__ ) . '/consentful.php' );
}
if ( ! defined( 'CONSENTFUL_SCHEMA_VERSION' ) ) {
	define( 'CONSENTFUL_SCHEMA_VERSION', 1 );
}
if ( ! defined( 'CONSENTFUL_POLICY_VERSION' ) ) {
	define( 'CONSENTFUL_POLICY_VERSION', 1 );
}

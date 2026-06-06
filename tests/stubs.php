<?php
declare( strict_types = 1 );

// No-op WordPress shims so the domain core can run outside WordPress under PHPUnit.
//
// A few shims optionally record their calls into a global array when a test seeds
// it (e.g. $GLOBALS['consentful_test_actions'] = array();). Tests that don't seed
// the global keep the plain no-op behavior.

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

// A throwaway ABSPATH so the schema shell's `require_once ABSPATH . 'wp-admin/…'`
// resolves under PHPUnit (dbDelta itself is the recorder stub below). The upgrade.php
// fixture is created on demand; nothing real is loaded.
if ( ! defined( 'ABSPATH' ) ) {
	$consentful_test_abspath = sys_get_temp_dir() . '/consentful-abspath/';
	$consentful_upgrade_dir  = $consentful_test_abspath . 'wp-admin/includes';
	if ( ! is_dir( $consentful_upgrade_dir ) ) {
		mkdir( $consentful_upgrade_dir, 0777, true );
	}
	$consentful_upgrade_file = $consentful_upgrade_dir . '/upgrade.php';
	if ( ! is_file( $consentful_upgrade_file ) ) {
		file_put_contents( $consentful_upgrade_file, "<?php\n" );
	}
	define( 'ABSPATH', $consentful_test_abspath );
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		if ( isset( $GLOBALS['consentful_test_actions'] ) && is_array( $GLOBALS['consentful_test_actions'] ) ) {
			$GLOBALS['consentful_test_actions'][] = array(
				'hook'     => $hook,
				'priority' => $priority,
			);
		}
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook, ...$args ) {
		return null;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) {
		return $value;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return (string) $url;
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return (string) $url;
	}
}

if ( ! function_exists( 'esc_js' ) ) {
	function esc_js( $text ) {
		return (string) $text;
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( ...$args ) {
		if ( isset( $GLOBALS['consentful_test_enqueues'] ) && is_array( $GLOBALS['consentful_test_enqueues'] ) ) {
			$GLOBALS['consentful_test_enqueues'][] = $args;
		}
		return true;
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( ...$args ) {
		if ( isset( $GLOBALS['consentful_test_styles'] ) && is_array( $GLOBALS['consentful_test_styles'] ) ) {
			$GLOBALS['consentful_test_styles'][] = $args;
		}
		return true;
	}
}

if ( ! function_exists( 'wp_register_script' ) ) {
	function wp_register_script( ...$args ) {
		return true;
	}
}

if ( ! function_exists( 'plugins_url' ) ) {
	function plugins_url( $path = '', $plugin = '' ) {
		return 'http://example.test/wp-content/plugins/consentful/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return rtrim( dirname( (string) $file ), '/\\' ) . '/';
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		return 'http://example.test/wp-content/plugins/consentful/';
	}
}

if ( ! function_exists( 'wp_get_inline_script_tag' ) ) {
	function wp_get_inline_script_tag( $data, $attributes = array() ) {
		return '<script>' . $data . '</script>';
	}
}

if ( ! function_exists( 'wp_print_inline_script_tag' ) ) {
	function wp_print_inline_script_tag( $data, $attributes = array() ) {
		echo wp_get_inline_script_tag( $data, $attributes );
	}
}

if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route( $namespace, $route, $args = array() ) {
		if ( isset( $GLOBALS['consentful_test_rest_routes'] ) && is_array( $GLOBALS['consentful_test_rest_routes'] ) ) {
			$GLOBALS['consentful_test_rest_routes'][] = array(
				'namespace' => $namespace,
				'route'     => $route,
				'args'      => $args,
			);
		}
		return true;
	}
}

if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( $path = '' ) {
		return 'http://example.test/wp-json/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( '__return_true' ) ) {
	function __return_true() {
		return true;
	}
}

// Option store backed by a per-test global array. Tests seed/inspect
// $GLOBALS['consentful_test_options']; an unseeded global behaves as an empty store.
if ( ! function_exists( 'consentful_test_option_store' ) ) {
	function &consentful_test_option_store() {
		if ( ! isset( $GLOBALS['consentful_test_options'] ) || ! is_array( $GLOBALS['consentful_test_options'] ) ) {
			$GLOBALS['consentful_test_options'] = array();
		}
		return $GLOBALS['consentful_test_options'];
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default_value = false ) {
		$store = &consentful_test_option_store();
		return array_key_exists( $name, $store ) ? $store[ $name ] : $default_value;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value ) {
		$store          = &consentful_test_option_store();
		$store[ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	function add_option( $name, $value = '' ) {
		$store = &consentful_test_option_store();
		if ( array_key_exists( $name, $store ) ) {
			return false;
		}
		$store[ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $name ) {
		$store = &consentful_test_option_store();
		return array_key_exists( '_transient_' . $name, $store ) ? $store[ '_transient_' . $name ] : false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $name, $value, $expiration = 0 ) {
		$store                            = &consentful_test_option_store();
		$store[ '_transient_' . $name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
		// Deterministic-enough filler for tests; never used for real secrets here.
		return substr( str_repeat( 'aB3-', (int) ceil( $length / 4 ) ), 0, (int) $length );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		$value = (string) $value;
		$value = preg_replace( '/[\r\n\t ]+/', ' ', $value );
		return trim( (string) preg_replace( '/<[^>]*>/', '', (string) $value ) );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( $file, $callback ) {
		if ( isset( $GLOBALS['consentful_test_activation_hooks'] ) && is_array( $GLOBALS['consentful_test_activation_hooks'] ) ) {
			$GLOBALS['consentful_test_activation_hooks'][] = array(
				'file'     => $file,
				'callback' => $callback,
			);
		}
		return true;
	}
}

// Records dbDelta calls when a test seeds $GLOBALS['consentful_test_dbdelta']; the
// table-creation path is exercised by ActivatorTest with a fake wpdb + this recorder.
if ( ! function_exists( 'dbDelta' ) ) {
	function dbDelta( $queries = '', $execute = true ) {
		if ( isset( $GLOBALS['consentful_test_dbdelta'] ) && is_array( $GLOBALS['consentful_test_dbdelta'] ) ) {
			$GLOBALS['consentful_test_dbdelta'][] = $queries;
		}
		return array();
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {

		/** @var array<int, mixed> */
		public $errors = array();

		/** @var array<string, mixed> */
		public $error_data = array();

		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( '' !== $code ) {
				$this->errors[ $code ][] = $message;
				if ( '' !== $data ) {
					$this->error_data[ $code ] = $data;
				}
			}
		}

		public function get_error_code() {
			$codes = array_keys( $this->errors );
			return $codes[0] ?? '';
		}

		public function get_error_data( $code = '' ) {
			$code = '' !== $code ? $code : $this->get_error_code();
			return $this->error_data[ $code ] ?? null;
		}
	}
}

// Minimal wpdb base so the typed DatabaseSink / ConsentLogSchema / Activator shells
// accept an injected fake (a test subclass records inserts/queries). The real wpdb is
// never loaded under PHPUnit.
if ( ! class_exists( 'wpdb' ) ) {
	class wpdb {

		/** @var string */
		public $prefix = 'wp_';

		/** @return int|false */
		public function insert( $table, $data, $format = null ) {
			return 1;
		}

		/** @return int|bool */
		public function query( $query ) {
			return 0;
		}

		public function get_charset_collate() {
			return '';
		}
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {

		public function __construct( $method = '', $route = '', $attributes = array() ) {}

		/** @return array<string, mixed> */
		public function get_json_params() {
			return array();
		}
	}
}

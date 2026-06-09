<?php
declare( strict_types = 1 );

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

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
		if ( ! isset( $GLOBALS['consentful_test_filters'] ) || ! is_array( $GLOBALS['consentful_test_filters'] ) ) {
			$GLOBALS['consentful_test_filters'] = array();
		}
		$GLOBALS['consentful_test_filters'][ $hook ][] = $callback;
		return true;
	}
}

if ( ! function_exists( 'remove_all_filters' ) ) {
	function remove_all_filters( $hook, $priority = false ) {
		if ( isset( $GLOBALS['consentful_test_filters'][ $hook ] ) ) {
			unset( $GLOBALS['consentful_test_filters'][ $hook ] );
		}
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
		$callbacks = $GLOBALS['consentful_test_filters'][ $hook ] ?? array();
		if ( is_array( $callbacks ) ) {
			foreach ( $callbacks as $callback ) {
				$value = $callback( $value, ...$args );
			}
		}
		return $value;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = 'default' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
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

if ( ! function_exists( 'wp_add_inline_script' ) ) {
	function wp_add_inline_script( ...$args ) {
		if ( isset( $GLOBALS['consentful_test_inline_scripts'] ) && is_array( $GLOBALS['consentful_test_inline_scripts'] ) ) {
			$GLOBALS['consentful_test_inline_scripts'][] = $args;
		}
		return true;
	}
}

if ( ! function_exists( 'wp_register_style' ) ) {
	function wp_register_style( ...$args ) {
		return true;
	}
}

if ( ! function_exists( 'wp_add_inline_style' ) ) {
	function wp_add_inline_style( ...$args ) {
		if ( isset( $GLOBALS['consentful_test_inline_styles'] ) && is_array( $GLOBALS['consentful_test_inline_styles'] ) ) {
			$GLOBALS['consentful_test_inline_styles'][] = $args;
		}
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

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) {
		return basename( dirname( (string) $file ) ) . '/' . basename( (string) $file );
	}
}

if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain( $domain, $deprecated = false, $plugin_rel_path = false ) {
		if ( isset( $GLOBALS['consentful_test_textdomains'] ) && is_array( $GLOBALS['consentful_test_textdomains'] ) ) {
			$GLOBALS['consentful_test_textdomains'][] = array(
				'domain' => $domain,
				'path'   => $plugin_rel_path,
			);
		}
		return true;
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
		return substr( str_repeat( 'aB3-', (int) ceil( $length / 4 ) ), 0, (int) $length );
	}
}

if ( ! function_exists( 'add_menu_page' ) ) {
	function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon_url = '', $position = null ) {
		if ( isset( $GLOBALS['consentful_test_menus'] ) && is_array( $GLOBALS['consentful_test_menus'] ) ) {
			$GLOBALS['consentful_test_menus'][] = array(
				'type'       => 'menu',
				'slug'       => $menu_slug,
				'capability' => $capability,
			);
		}
		return $menu_slug;
	}
}

if ( ! function_exists( 'add_submenu_page' ) ) {
	function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null ) {
		if ( isset( $GLOBALS['consentful_test_menus'] ) && is_array( $GLOBALS['consentful_test_menus'] ) ) {
			$GLOBALS['consentful_test_menus'][] = array(
				'type'       => 'submenu',
				'parent'     => $parent_slug,
				'slug'       => $menu_slug,
				'capability' => $capability,
			);
		}
		return $menu_slug;
	}
}

if ( ! function_exists( 'register_setting' ) ) {
	function register_setting( $option_group, $option_name, $args = array() ) {
		if ( isset( $GLOBALS['consentful_test_settings'] ) && is_array( $GLOBALS['consentful_test_settings'] ) ) {
			$GLOBALS['consentful_test_settings'][] = array(
				'group' => $option_group,
				'name'  => $option_name,
				'args'  => $args,
			);
		}
		return true;
	}
}

if ( ! function_exists( 'settings_fields' ) ) {
	function settings_fields( $option_group ) {
		echo '';
	}
}

if ( ! function_exists( 'do_settings_sections' ) ) {
	function do_settings_sections( $page ) {
		echo '';
	}
}

if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( $text = '', $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = '' ) {
		echo '<input type="submit" />';
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return ! empty( $GLOBALS['consentful_test_is_admin'] );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability, ...$args ) {
		return ! array_key_exists( 'consentful_test_can', $GLOBALS ) || (bool) $GLOBALS['consentful_test_can'];
	}
}

if ( ! function_exists( 'check_admin_referer' ) ) {
	function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
		return 1;
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action = -1 ) {
		return 1;
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
		$field = '<input type="hidden" name="' . $name . '" value="nonce" />';
		if ( $display ) {
			echo $field;
		}
		return $field;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '', $scheme = 'admin' ) {
		return 'http://example.test/wp-admin/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'get_privacy_policy_url' ) ) {
	function get_privacy_policy_url() {
		$url = $GLOBALS['consentful_test_privacy_url'] ?? '';
		return is_string( $url ) ? $url : '';
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

if ( ! function_exists( 'sanitize_hex_color' ) ) {
	function sanitize_hex_color( $color ) {
		$color = (string) $color;
		return preg_match( '/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color ) ? $color : null;
	}
}

if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
}

if ( ! function_exists( 'selected' ) ) {
	function selected( $selected, $current = true, $display = true ) {
		$result = (string) $selected === (string) $current ? ' selected="selected"' : '';
		if ( $display ) {
			echo $result;
		}
		return $result;
	}
}

if ( ! function_exists( 'checked' ) ) {
	function checked( $checked, $current = true, $display = true ) {
		$result = (string) $checked === (string) $current ? ' checked="checked"' : '';
		if ( $display ) {
			echo $result;
		}
		return $result;
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

if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook( $file, $callback ) {
		if ( isset( $GLOBALS['consentful_test_deactivation_hooks'] ) && is_array( $GLOBALS['consentful_test_deactivation_hooks'] ) ) {
			$GLOBALS['consentful_test_deactivation_hooks'][] = array(
				'file'     => $file,
				'callback' => $callback,
			);
		}
		return true;
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( $hook, $args = array() ) {
		$cron = $GLOBALS['consentful_test_cron'] ?? array();
		return ( is_array( $cron ) && in_array( $hook, $cron, true ) ) ? 2000000000 : false;
	}
}
if ( ! function_exists( 'wp_schedule_event' ) ) {
	function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array() ) {
		if ( isset( $GLOBALS['consentful_test_cron'] ) && is_array( $GLOBALS['consentful_test_cron'] ) ) {
			$GLOBALS['consentful_test_cron'][] = $hook;
		}
		return true;
	}
}
if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	function wp_clear_scheduled_hook( $hook, $args = array() ) {
		if ( isset( $GLOBALS['consentful_test_cron'] ) && is_array( $GLOBALS['consentful_test_cron'] ) ) {
			$GLOBALS['consentful_test_cron'] = array_values(
				array_filter(
					$GLOBALS['consentful_test_cron'],
					static function ( $scheduled ) use ( $hook ) {
						return $scheduled !== $hook;
					}
				)
			);
		}
		return 0;
	}
}

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

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {

		/** @var mixed */
		public $data;

		/** @var int */
		public $status;

		/** @var array<string, string> */
		public $headers;

		public function __construct( $data = null, $status = 200, $headers = array() ) {
			$this->data    = $data;
			$this->status  = $status;
			$this->headers = $headers;
		}

		public function header( $key, $value ) {
			$this->headers[ $key ] = $value;
		}

		public function get_data() {
			return $this->data;
		}

		public function get_status() {
			return $this->status;
		}

		public function get_headers() {
			return $this->headers;
		}
	}
}

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

		/** @return string */
		public function prepare( $query, ...$args ) {
			return (string) $query;
		}

		/** @return int|string|null */
		public function get_var( $query = null, $column_offset = 0, $row_offset = 0 ) {
			return null;
		}

		/** @return array<int, mixed>|object|null */
		public function get_results( $query = null, $output = OBJECT ) {
			return array();
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

<?php
declare( strict_types = 1 );

// No-op WordPress shims so the domain core can run outside WordPress under PHPUnit.
//
// A few shims optionally record their calls into a global array when a test seeds
// it (e.g. $GLOBALS['consentful_test_actions'] = array();). Tests that don't seed
// the global keep the plain no-op behavior.

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

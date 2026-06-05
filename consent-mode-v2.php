<?php
/**
 * Plugin Name:       Consent Mode v2 — Loi 25 & RGPD
 * Plugin URI:        https://github.com/
 * Description:        Google Consent Mode v2 (block-before-consent) + a gated GA4 tag, with a consent banner that meets Québec Loi 25 / GDPR. The Google tag loads ONLY after the visitor makes a choice. Theme-independent and fully customizable (color, light/dark, position, copy).
 * Version:           2.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Tamarak
 * Author URI:        https://tamarak.dev
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       consent-mode-v2
 * Domain Path:       /languages
 *
 * Owns the single Google tag for the site. Do NOT also inject gtag.js via
 * Insert Headers & Footers, GLA, Site Kit, GTM, etc. — that would double-count
 * and bypass consent gating. See the admin notice for GLA / Facebook reactivation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CMV2_VER', '2.0.0' );
define( 'CMV2_FILE', __FILE__ );
// Bump when the set of consent CATEGORIES changes (e.g. adding a Facebook
// purpose) — stored cookies with an older schema are treated as no-consent and
// the banner is shown again (Loi 25: consent must be specific to each purpose).
define( 'CMV2_SCHEMA_V', 1 );
define( 'CMV2_COOKIE', 'cmv2_consent' );
define( 'CMV2_OPTION', 'cmv2_settings' );

/* -------------------------------------------------------------------------
 * i18n
 * ---------------------------------------------------------------------- */

/**
 * Force a specific locale for this plugin's text domain when the admin pins a
 * language ("fr"/"en"); "auto" leaves it to the site locale. Registered at load
 * time so it is in place before load_plugin_textdomain() runs on init.
 */
function cmv2_filter_locale( $locale, $domain ) {
	if ( 'consent-mode-v2' !== $domain ) {
		return $locale;
	}
	$pref = cmv2_settings()['lang'];
	if ( 'fr' === $pref ) {
		return 'fr_CA';
	}
	if ( 'en' === $pref ) {
		return 'en_US';
	}
	// auto: map French sub-locales we do not ship (fr_BE, fr_CH, fr_LU…) to fr_CA
	// so a French site never falls back to the English source.
	if ( 0 === strpos( (string) $locale, 'fr' ) && ! in_array( $locale, array( 'fr_CA', 'fr_FR' ), true ) ) {
		return 'fr_CA';
	}
	return $locale;
}
add_filter( 'plugin_locale', 'cmv2_filter_locale', 10, 2 );

function cmv2_load_textdomain() {
	load_plugin_textdomain( 'consent-mode-v2', false, dirname( plugin_basename( CMV2_FILE ) ) . '/languages' );
}
add_action( 'init', 'cmv2_load_textdomain' );

/* -------------------------------------------------------------------------
 * Settings
 * ---------------------------------------------------------------------- */

/**
 * Stored settings, merged over defaults. Constants/filters win at read time.
 */
function cmv2_settings() {
	$defaults = array(
		'ga4_id'      => '',     // empty -> nothing loads, banner hidden
		'privacy_url' => '',
		'ads'         => 0,      // manage the 3 Google Ads signals + personalization_storage
		'days'        => 180,    // re-ask after N days
		// Appearance.
		'primary'     => '#2563eb',
		'theme'       => 'auto', // light | dark | auto
		'position'    => 'bar',  // bar | corner | modal
		'radius'      => 8,      // button corner radius (px, 0..40)
		'show_reopen' => 1,      // floating "manage cookies" pill
		'title'       => '',     // banner heading override (blank -> i18n default)
		'body'        => '',     // banner copy override (blank -> i18n default)
		'lang'        => 'auto', // auto | fr | en
	);
	return wp_parse_args( get_option( CMV2_OPTION, array() ), $defaults );
}

function cmv2_ga4_id() {
	$id = cmv2_settings()['ga4_id'];
	return (string) apply_filters( 'cmv2_ga4_id', $id );
}

function cmv2_ads_enabled() {
	return (bool) apply_filters( 'cmv2_ads_enabled', (bool) cmv2_settings()['ads'] );
}

function cmv2_days() {
	$d = (int) cmv2_settings()['days'];
	return $d > 0 ? $d : 180;
}

function cmv2_privacy_url() {
	$url = cmv2_settings()['privacy_url'];
	if ( ! $url ) {
		$page = (int) get_option( 'wp_page_for_privacy_policy' );
		if ( $page ) {
			$url = get_permalink( $page );
		}
	}
	return $url ? esc_url( $url ) : '';
}

/* -------------------------------------------------------------------------
 * Color helpers (compute hover + readable text server-side so theming does
 * not depend on CSS color-mix support).
 * ---------------------------------------------------------------------- */

function cmv2_valid_hex( $hex ) {
	return (bool) preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', (string) $hex );
}

/** Normalize #abc / #aabbcc to array( r, g, b ) of ints, or null. */
function cmv2_hex_rgb( $hex ) {
	if ( ! cmv2_valid_hex( $hex ) ) {
		return null;
	}
	$h = ltrim( $hex, '#' );
	if ( 3 === strlen( $h ) ) {
		$h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
	}
	return array(
		hexdec( substr( $h, 0, 2 ) ),
		hexdec( substr( $h, 2, 2 ) ),
		hexdec( substr( $h, 4, 2 ) ),
	);
}

/** Darken a hex color by $f (0..1). Returns #rrggbb. */
function cmv2_darken( $hex, $f = 0.14 ) {
	$rgb = cmv2_hex_rgb( $hex );
	if ( ! $rgb ) {
		return $hex;
	}
	$out = '';
	foreach ( $rgb as $c ) {
		$out .= str_pad( dechex( (int) round( $c * ( 1 - $f ) ) ), 2, '0', STR_PAD_LEFT );
	}
	return '#' . $out;
}

/** sRGB relative luminance (WCAG) for an [ r, g, b ] 0..255 triplet. */
function cmv2_rel_luminance( $rgb ) {
	$lin = array();
	foreach ( $rgb as $c ) {
		$s     = $c / 255;
		$lin[] = $s <= 0.03928 ? $s / 12.92 : pow( ( $s + 0.055 ) / 1.055, 2.4 );
	}
	return 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
}

/** Readable text color (#111111 or #ffffff) for a background hex, chosen by
 * actual WCAG contrast — a YIQ heuristic mis-picks saturated hues (e.g. white
 * on bright green/red), which would break the "auto contrast" promise. */
function cmv2_on_color( $hex ) {
	$rgb = cmv2_hex_rgb( $hex );
	if ( ! $rgb ) {
		return '#ffffff';
	}
	$lbg    = cmv2_rel_luminance( $rgb );
	$c_dark = ( max( $lbg, cmv2_rel_luminance( array( 17, 17, 17 ) ) ) + 0.05 ) / ( min( $lbg, cmv2_rel_luminance( array( 17, 17, 17 ) ) ) + 0.05 );
	$c_lite = ( 1.0 + 0.05 ) / ( $lbg + 0.05 );
	return $c_dark >= $c_lite ? '#111111' : '#ffffff';
}

/* -------------------------------------------------------------------------
 * 1) Consent Mode v2 default-deny + a gated GA4 loader, as early as possible
 *    in <head> (priority 1). The tag is NOT loaded for a visitor who has not
 *    yet made a choice — that is what makes "prior consent" true under Loi 25.
 *    A returning visitor with a valid, unexpired cookie that grants at least
 *    one purpose gets the tag loaded immediately (accurate first hit).
 * ---------------------------------------------------------------------- */
function cmv2_head() {
	$id = cmv2_ga4_id();
	if ( ! $id ) {
		return;
	}
	$ads     = cmv2_ads_enabled();
	$ttl_ms  = cmv2_days() * 86400000;
	$ver     = (int) CMV2_SCHEMA_V;
	$cookie  = preg_replace( '/[^A-Za-z0-9_]/', '', CMV2_COOKIE );
	$load_if = $ads ? 'v.c.analytics||v.c.marketing' : 'v.c.analytics';
	?>
<!-- Consent Mode v2 (deny by default; tag blocked before consent) -->
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('consent','default',{
	ad_storage:'denied',
	ad_user_data:'denied',
	ad_personalization:'denied',
	analytics_storage:'denied',
	functionality_storage:'granted',
	personalization_storage:'denied',
	security_storage:'granted',
	wait_for_update:500
});
<?php if ( $ads ) : ?>
gtag('set','ads_data_redaction',true);
gtag('set','url_passthrough',true);
<?php endif; ?>
window.cmv2LoadTag=function(){
	if(window.__cmv2TagLoaded){return;}
	window.__cmv2TagLoaded=true;
	gtag('js',new Date());
	gtag('config','<?php echo esc_js( $id ); ?>');
	var s=document.createElement('script');
	s.async=true;
	s.src='https://www.googletagmanager.com/gtag/js?id='+encodeURIComponent('<?php echo esc_js( $id ); ?>');
	document.head.appendChild(s);
};
(function(){try{
	var m=document.cookie.match(/(?:^|;\s*)<?php echo $cookie; ?>=([^;]+)/);
	if(!m){return;}
	var v=JSON.parse(decodeURIComponent(m[1]));
	if(!v||!v.c){return;}
	if(v.v!==<?php echo $ver; ?>){return;}
	if(!v.t||(Date.now()-v.t)><?php echo $ttl_ms; ?>){return;}
	var u={analytics_storage:v.c.analytics?'granted':'denied'};
<?php if ( $ads ) : ?>
	u.ad_storage=v.c.marketing?'granted':'denied';
	u.ad_user_data=v.c.marketing?'granted':'denied';
	u.ad_personalization=v.c.marketing?'granted':'denied';
	u.personalization_storage=v.c.marketing?'granted':'denied';
<?php endif; ?>
	gtag('consent','update',u);
	if(<?php echo $load_if; ?>){window.cmv2LoadTag();}
}catch(e){}})();
</script>
<!-- /Consent Mode v2 -->
	<?php
}
add_action( 'wp_head', 'cmv2_head', 1 );

/* -------------------------------------------------------------------------
 * 2) Front-end assets (only when a tag exists to gate).
 * ---------------------------------------------------------------------- */
function cmv2_assets() {
	if ( ! cmv2_ga4_id() ) {
		return;
	}
	$s    = cmv2_settings();
	$base = plugins_url( 'assets/', CMV2_FILE );

	wp_enqueue_style( 'cmv2-consent', $base . 'consent.css', array(), CMV2_VER );

	// Server-sanitized theming variables (hex + clamped int only -> safe inline).
	$primary = cmv2_valid_hex( $s['primary'] ) ? $s['primary'] : '#2563eb';
	$radius  = max( 0, min( 40, (int) $s['radius'] ) );
	$inline  = sprintf(
		'.cmv2,.cmv2-open{--cmv2-primary:%1$s;--cmv2-primary-d:%2$s;--cmv2-on-primary:%3$s;--cmv2-radius:%4$dpx}',
		$primary,
		cmv2_darken( $primary, 0.14 ),
		cmv2_on_color( $primary ),
		$radius
	);
	wp_add_inline_style( 'cmv2-consent', $inline );

	wp_enqueue_script( 'cmv2-consent', $base . 'consent.js', array(), CMV2_VER, true );
	wp_localize_script(
		'cmv2-consent',
		'CMV2_CONSENT',
		array(
			'cookie'   => CMV2_COOKIE,
			'days'     => cmv2_days(),
			'ads'      => cmv2_ads_enabled() ? 1 : 0,
			'v'        => (int) CMV2_SCHEMA_V,
			'modal'    => ( 'modal' === $s['position'] ) ? 1 : 0,
		)
	);
}
add_action( 'wp_enqueue_scripts', 'cmv2_assets' );

/* -------------------------------------------------------------------------
 * 3) Banner markup (hidden until consent.js decides). Rendered for every theme.
 * ---------------------------------------------------------------------- */
function cmv2_banner() {
	if ( ! cmv2_ga4_id() ) {
		return;
	}
	$s        = cmv2_settings();
	$privacy  = cmv2_privacy_url();
	$ads      = cmv2_ads_enabled();
	$position = in_array( $s['position'], array( 'bar', 'corner', 'modal' ), true ) ? $s['position'] : 'bar';
	$theme    = in_array( $s['theme'], array( 'light', 'dark', 'auto' ), true ) ? $s['theme'] : 'auto';
	$is_modal = ( 'modal' === $position );

	$title = $s['title'] ? $s['title'] : __( 'Your privacy', 'consent-mode-v2' );
	$body  = $s['body'] ? $s['body'] : __( 'We use cookies to measure traffic and improve your experience. No non-essential cookie is set without your consent. You can change your choices at any time.', 'consent-mode-v2' );

	$classes = 'cmv2 cmv2--pos-' . $position . ' cmv2--theme-' . $theme;
	?>
<div id="cmv2-consent" class="<?php echo esc_attr( $classes ); ?>" role="dialog" aria-modal="<?php echo $is_modal ? 'true' : 'false'; ?>" aria-labelledby="cmv2-consent-title" aria-describedby="cmv2-consent-desc" hidden>
	<div class="cmv2__inner">
		<h2 id="cmv2-consent-title" class="cmv2__title"><?php echo esc_html( $title ); ?></h2>
		<p id="cmv2-consent-desc" class="cmv2__desc">
			<?php echo esc_html( $body ); ?>
			<?php if ( $privacy ) : ?>
				<a class="cmv2__link" href="<?php echo esc_url( $privacy ); ?>"><?php esc_html_e( 'Learn more', 'consent-mode-v2' ); ?></a>
			<?php endif; ?>
		</p>

		<div class="cmv2__prefs" id="cmv2-consent-prefs" role="group" aria-label="<?php esc_attr_e( 'Cookie preferences by category', 'consent-mode-v2' ); ?>" hidden>
			<label class="cmv2__cat">
				<input type="checkbox" checked disabled>
				<span><strong><?php esc_html_e( 'Necessary cookies', 'consent-mode-v2' ); ?></strong> — <?php esc_html_e( 'always on, essential to the operation of the site.', 'consent-mode-v2' ); ?></span>
			</label>
			<label class="cmv2__cat">
				<input type="checkbox" id="cmv2-cat-analytics">
				<span><strong><?php esc_html_e( 'Analytics', 'consent-mode-v2' ); ?></strong> — <?php esc_html_e( 'Google Analytics, to understand how the site is used.', 'consent-mode-v2' ); ?></span>
			</label>
			<?php if ( $ads ) : ?>
			<label class="cmv2__cat">
				<input type="checkbox" id="cmv2-cat-marketing">
				<span><strong><?php esc_html_e( 'Marketing & personalization', 'consent-mode-v2' ); ?></strong> — <?php esc_html_e( 'advertising, remarketing (Google Ads) and content personalization.', 'consent-mode-v2' ); ?></span>
			</label>
			<?php endif; ?>
		</div>

		<div class="cmv2__actions">
			<button type="button" class="cmv2__btn cmv2__btn--primary" data-cmv2="accept"><?php esc_html_e( 'Accept all', 'consent-mode-v2' ); ?></button>
			<button type="button" class="cmv2__btn cmv2__btn--reject" data-cmv2="reject"><?php esc_html_e( 'Reject all', 'consent-mode-v2' ); ?></button>
			<button type="button" class="cmv2__btn cmv2__btn--ghost" data-cmv2="customize" aria-expanded="false" aria-controls="cmv2-consent-prefs"><?php esc_html_e( 'Customize', 'consent-mode-v2' ); ?></button>
			<button type="button" class="cmv2__btn cmv2__btn--save" data-cmv2="save" hidden><?php esc_html_e( 'Save my choices', 'consent-mode-v2' ); ?></button>
		</div>
	</div>
</div>
<?php if ( $s['show_reopen'] ) : ?>
<button type="button" id="cmv2-consent-open" class="cmv2-open cmv2--theme-<?php echo esc_attr( $theme ); ?>" hidden aria-haspopup="dialog" aria-controls="cmv2-consent" aria-label="<?php esc_attr_e( 'Manage cookies', 'consent-mode-v2' ); ?>"><?php esc_html_e( 'Manage cookies', 'consent-mode-v2' ); ?></button>
<?php endif; ?>
	<?php
}
add_action( 'wp_footer', 'cmv2_banner', 20 );

/* -------------------------------------------------------------------------
 * 4) Admin warnings: other plugins that emit their OWN marketing tag, and a
 *    nudge to set the GA4 ID.
 * ---------------------------------------------------------------------- */
function cmv2_admin_notices() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	if ( ! cmv2_ga4_id() && current_user_can( 'manage_options' ) ) {
		$url = esc_url( admin_url( 'options-general.php?page=consent-mode-v2' ) );
		echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Consent Mode v2', 'consent-mode-v2' ) . ' :</strong> '
			. esc_html__( 'Add your GA4 measurement ID to activate the consent banner and the tag.', 'consent-mode-v2' )
			. ' <a href="' . $url . '">' . esc_html__( 'Settings', 'consent-mode-v2' ) . '</a></p></div>';
	}

	$warnings = array();
	if ( is_plugin_active( 'google-listings-and-ads/google-listings-and-ads.php' ) ) {
		$warnings[] = __( 'Google Listings &amp; Ads is active. If it is connected to a Google Ads conversion, it injects a second gtag.js with a default consent region limited to the EEA (Canada is not included) - a duplicate tag and advertising that is not blocked for Quebec. Keep the Ads conversion disconnected, or disable GLA tag injection.', 'consent-mode-v2' );
	}
	if ( is_plugin_active( 'facebook-for-woocommerce/facebook-for-woocommerce.php' ) ) {
		$warnings[] = __( 'Facebook for WooCommerce is active. The Meta pixel is NOT governed by this banner (Consent Mode does not control fbq). Wire its consent to the Marketing category via the "facebook_signals_held" filter, otherwise the pixel fires despite "Reject all".', 'consent-mode-v2' );
	}
	foreach ( $warnings as $w ) {
		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Consent Mode v2', 'consent-mode-v2' ) . ' :</strong> ' . wp_kses( $w, array() ) . '</p></div>';
	}
}
add_action( 'admin_notices', 'cmv2_admin_notices' );

/* -------------------------------------------------------------------------
 * 5) Settings screen: Settings → Consent Mode v2.
 * ---------------------------------------------------------------------- */
function cmv2_admin_menu() {
	add_options_page(
		__( 'Consent Mode v2', 'consent-mode-v2' ),
		__( 'Consent Mode v2', 'consent-mode-v2' ),
		'manage_options',
		'consent-mode-v2',
		'cmv2_settings_page'
	);
}
add_action( 'admin_menu', 'cmv2_admin_menu' );

/** "Settings" link on the Plugins list row. */
function cmv2_action_links( $links ) {
	$url = esc_url( admin_url( 'options-general.php?page=consent-mode-v2' ) );
	array_unshift( $links, '<a href="' . $url . '">' . esc_html__( 'Settings', 'consent-mode-v2' ) . '</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( CMV2_FILE ), 'cmv2_action_links' );

/** Enqueue the WP color picker on our settings screen only. */
function cmv2_admin_assets( $hook ) {
	if ( 'settings_page_consent-mode-v2' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );
	wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){$(".cmv2-color").wpColorPicker();});' );
}
add_action( 'admin_enqueue_scripts', 'cmv2_admin_assets' );

/** Sanitize the posted settings against the current values (keeps old on bad input). */
function cmv2_sanitize_post( array $current ) {
	$ga4 = isset( $_POST['ga4_id'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['ga4_id'] ) ) ) : '';
	$ga4_error = false;
	if ( $ga4 && ! preg_match( '/^G-[A-Z0-9]{4,}$/', $ga4 ) ) {
		$ga4       = $current['ga4_id'];
		$ga4_error = true;
	}

	$primary = isset( $_POST['primary'] ) ? sanitize_text_field( wp_unslash( $_POST['primary'] ) ) : '';
	if ( ! cmv2_valid_hex( $primary ) ) {
		$primary = $current['primary'];
	}

	$theme    = isset( $_POST['theme'] ) ? sanitize_key( wp_unslash( $_POST['theme'] ) ) : 'auto';
	$position = isset( $_POST['position'] ) ? sanitize_key( wp_unslash( $_POST['position'] ) ) : 'bar';
	$lang     = isset( $_POST['lang'] ) ? sanitize_key( wp_unslash( $_POST['lang'] ) ) : 'auto';

	$new = array(
		'ga4_id'      => $ga4,
		'privacy_url' => isset( $_POST['privacy_url'] ) ? esc_url_raw( wp_unslash( $_POST['privacy_url'] ) ) : '',
		'ads'         => isset( $_POST['ads'] ) ? 1 : 0,
		'days'        => isset( $_POST['days'] ) ? min( 390, max( 1, (int) $_POST['days'] ) ) : 180,
		'primary'     => $primary,
		'theme'       => in_array( $theme, array( 'light', 'dark', 'auto' ), true ) ? $theme : 'auto',
		'position'    => in_array( $position, array( 'bar', 'corner', 'modal' ), true ) ? $position : 'bar',
		'radius'      => isset( $_POST['radius'] ) ? min( 40, max( 0, (int) $_POST['radius'] ) ) : 8,
		'show_reopen' => isset( $_POST['show_reopen'] ) ? 1 : 0,
		'title'       => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
		'body'        => isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '',
		'lang'        => in_array( $lang, array( 'auto', 'fr', 'en' ), true ) ? $lang : 'auto',
	);

	return array( $new, $ga4_error );
}

function cmv2_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s = cmv2_settings();

	if ( isset( $_POST['cmv2_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['cmv2_nonce'] ) ), 'cmv2_save' ) ) {
		list( $new, $ga4_error ) = cmv2_sanitize_post( $s );
		if ( $ga4_error ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Invalid measurement ID (expected format: G-XXXXXXXXXX). Previous value kept.', 'consent-mode-v2' ) . '</p></div>';
		}
		update_option( CMV2_OPTION, $new );
		// Purge full-page + object caches so the head block reflects new settings.
		do_action( 'rt_nginx_helper_purge_all' );
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
		$s = $new;
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'consent-mode-v2' ) . '</p></div>';
	}

	$theme_opts = array(
		'auto'  => __( "Auto (follow the visitor's system)", 'consent-mode-v2' ),
		'light' => __( 'Light', 'consent-mode-v2' ),
		'dark'  => __( 'Dark', 'consent-mode-v2' ),
	);
	$pos_opts = array(
		'bar'    => __( 'Bottom bar (full width)', 'consent-mode-v2' ),
		'corner' => __( 'Floating card (bottom corner)', 'consent-mode-v2' ),
		'modal'  => __( 'Centered modal', 'consent-mode-v2' ),
	);
	$lang_opts = array(
		'auto' => __( 'Auto (site language)', 'consent-mode-v2' ),
		'fr'   => __( 'French', 'consent-mode-v2' ),
		'en'   => __( 'English', 'consent-mode-v2' ),
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Consent Mode v2', 'consent-mode-v2' ); ?></h1>
		<p><?php esc_html_e( "This plugin is the SINGLE source of the site's Google tag. Do not inject gtag.js anywhere else (Insert Headers & Footers, GLA, Site Kit, GTM). The tag loads only after the visitor makes a choice.", 'consent-mode-v2' ); ?></p>
		<form method="post">
			<?php wp_nonce_field( 'cmv2_save', 'cmv2_nonce' ); ?>

			<h2 class="title"><?php esc_html_e( 'Tag & consent', 'consent-mode-v2' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="ga4_id"><?php esc_html_e( 'GA4 measurement ID', 'consent-mode-v2' ); ?></label></th>
					<td><input name="ga4_id" id="ga4_id" type="text" class="regular-text" value="<?php echo esc_attr( $s['ga4_id'] ); ?>" placeholder="G-XXXXXXXXXX"></td>
				</tr>
				<tr>
					<th scope="row"><label for="privacy_url"><?php esc_html_e( 'Privacy policy URL', 'consent-mode-v2' ); ?></label></th>
					<td><input name="privacy_url" id="privacy_url" type="url" class="regular-text" value="<?php echo esc_attr( $s['privacy_url'] ); ?>" placeholder="<?php echo esc_attr( cmv2_privacy_url() ); ?>">
					<p class="description"><?php esc_html_e( 'Leave blank to use the privacy page configured in WordPress.', 'consent-mode-v2' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Advertising signals', 'consent-mode-v2' ); ?></th>
					<td><label><input name="ads" type="checkbox" value="1" <?php checked( $s['ads'], 1 ); ?>> <?php esc_html_e( 'Manage ad_storage / ad_user_data / ad_personalization (Google Ads). Adds a Marketing category to the banner.', 'consent-mode-v2' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><label for="days"><?php esc_html_e( 'Re-ask after (days)', 'consent-mode-v2' ); ?></label></th>
					<td><input name="days" id="days" type="number" min="1" max="390" value="<?php echo esc_attr( $s['days'] ); ?>" class="small-text">
					<p class="description"><?php esc_html_e( 'Maximum 390 days (~13 months). Consent expires and the banner reappears.', 'consent-mode-v2' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="lang"><?php esc_html_e( 'Banner language', 'consent-mode-v2' ); ?></label></th>
					<td><select name="lang" id="lang"><?php cmv2_options( $lang_opts, $s['lang'] ); ?></select></td>
				</tr>
			</table>

			<h2 class="title"><?php esc_html_e( 'Appearance', 'consent-mode-v2' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="primary"><?php esc_html_e( 'Primary color', 'consent-mode-v2' ); ?></label></th>
					<td><input name="primary" id="primary" type="text" class="cmv2-color" value="<?php echo esc_attr( $s['primary'] ); ?>" data-default-color="#2563eb">
					<p class="description"><?php esc_html_e( 'Used for the "Accept" button and links. Button text color is chosen automatically for contrast.', 'consent-mode-v2' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="theme"><?php esc_html_e( 'Theme', 'consent-mode-v2' ); ?></label></th>
					<td><select name="theme" id="theme"><?php cmv2_options( $theme_opts, $s['theme'] ); ?></select></td>
				</tr>
				<tr>
					<th scope="row"><label for="position"><?php esc_html_e( 'Position', 'consent-mode-v2' ); ?></label></th>
					<td><select name="position" id="position"><?php cmv2_options( $pos_opts, $s['position'] ); ?></select>
					<p class="description"><?php esc_html_e( 'The bottom bar is recommended for strict Loi 25 (it blocks no interaction). The centered modal covers the page until a choice is made, which may be treated as a cookie wall.', 'consent-mode-v2' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="radius"><?php esc_html_e( 'Button corner radius', 'consent-mode-v2' ); ?></label></th>
					<td><input name="radius" id="radius" type="number" min="0" max="40" value="<?php echo esc_attr( $s['radius'] ); ?>" class="small-text"> px
					<p class="description"><?php esc_html_e( '0 = square, 40 = pill.', 'consent-mode-v2' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Re-open button', 'consent-mode-v2' ); ?></th>
					<td><label><input name="show_reopen" type="checkbox" value="1" <?php checked( $s['show_reopen'], 1 ); ?>> <?php esc_html_e( 'Show the floating "Manage cookies" button after a choice is made.', 'consent-mode-v2' ); ?></label>
					<p class="description"><?php esc_html_e( 'If hidden, add a link with data-cmv2-open (e.g. in the footer menu) to let visitors re-open the manager.', 'consent-mode-v2' ); ?></p></td>
				</tr>
			</table>

			<h2 class="title"><?php esc_html_e( 'Banner text (optional override)', 'consent-mode-v2' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="title"><?php esc_html_e( 'Heading', 'consent-mode-v2' ); ?></label></th>
					<td><input name="title" id="title" type="text" class="regular-text" value="<?php echo esc_attr( $s['title'] ); ?>" placeholder="<?php esc_attr_e( 'Your privacy', 'consent-mode-v2' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="body"><?php esc_html_e( 'Description', 'consent-mode-v2' ); ?></label></th>
					<td><textarea name="body" id="body" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'We use cookies to measure traffic and improve your experience. No non-essential cookie is set without your consent. You can change your choices at any time.', 'consent-mode-v2' ); ?>"><?php echo esc_textarea( $s['body'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Leave blank to use the translated default. Plain text only.', 'consent-mode-v2' ); ?></p></td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/** Render <option> tags for a select. */
function cmv2_options( array $opts, $selected ) {
	foreach ( $opts as $value => $label ) {
		printf(
			'<option value="%1$s"%2$s>%3$s</option>',
			esc_attr( $value ),
			selected( $selected, $value, false ),
			esc_html( $label )
		);
	}
}

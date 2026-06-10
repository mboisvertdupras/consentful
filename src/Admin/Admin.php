<?php
declare( strict_types = 1 );

namespace Consentful\Admin;

use Consentful\Catalog\Catalog;
use Consentful\Catalog\CatalogEntry;
use Consentful\Consent\ConsentLogExporter;
use Consentful\Frontend\BannerConfig;

final class Admin {

	private const CAPABILITY    = 'manage_options';
	private const SETTINGS_PAGE = 'consentful';
	private const LOG_PAGE      = 'consentful-log';
	private const OPTION_GROUP  = 'consentful';
	private const NONCE_EXPORT  = 'consentful_export';
	private const PER_PAGE      = 50;

	private string $settings_hook = '';

	public function __construct(
		private readonly Catalog $catalog,
		private readonly ConsentLogReader $reader,
	) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_consentful_export', array( $this, 'handle_export' ) );
	}

	public function register_menu(): void {
		$this->settings_hook = (string) add_menu_page(
			__( 'Consentful', 'consentful' ),
			__( 'Consentful', 'consentful' ),
			self::CAPABILITY,
			self::SETTINGS_PAGE,
			array( $this, 'render_settings_page' ),
			'dashicons-privacy'
		);
		add_submenu_page(
			self::SETTINGS_PAGE,
			__( 'Settings', 'consentful' ),
			__( 'Settings', 'consentful' ),
			self::CAPABILITY,
			self::SETTINGS_PAGE,
			array( $this, 'render_settings_page' )
		);
		add_submenu_page(
			self::SETTINGS_PAGE,
			__( 'Consent log', 'consentful' ),
			__( 'Consent log', 'consentful' ),
			self::CAPABILITY,
			self::LOG_PAGE,
			array( $this, 'render_log_page' )
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( '' === $this->settings_hook || $this->settings_hook !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){$(".consentful-color").wpColorPicker();});' );

		wp_register_script( 'consentful-admin', false, array(), CONSENTFUL_VERSION, true );
		wp_enqueue_script( 'consentful-admin' );
		wp_add_inline_script( 'consentful-admin', self::repeater_js() );

		wp_register_style( 'consentful-admin', false, array(), CONSENTFUL_VERSION );
		wp_enqueue_style( 'consentful-admin' );
		wp_add_inline_style( 'consentful-admin', self::admin_css() );
	}

	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			CONSENTFUL_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => static fn ( $raw ): array => Settings::sanitize( is_array( $raw ) ? $raw : array() ),
				'default'           => array(),
			)
		);
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$settings = Settings::from_wp();

		echo '<div class="wrap consentful">';
		echo '<h1>' . esc_html__( 'Consentful settings', 'consentful' ) . '</h1>';
		echo '<p>' . esc_html__( 'Configure the consent banner, purposes, tags and jurisdiction posture for your site. Banner wording is translated through the language files.', 'consentful' ) . '</p>';
		echo '<form action="options.php" method="post">';
		settings_fields( self::OPTION_GROUP );
		$this->render_appearance_fields( $settings );
		$this->render_purpose_fields( $settings );
		$this->render_tag_fields( $settings );
		$this->render_geo_fields( $settings );
		submit_button();
		echo '</form>';
		echo '</div>';
	}

	private function render_appearance_fields( Settings $settings ): void {
		$banner = $settings->banner();

		echo '<h2 class="title">' . esc_html__( 'Banner appearance', 'consentful' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';

		$this->row(
			__( 'Banner', 'consentful' ),
			'banner][enabled',
			fn () => $this->checkbox_field( 'banner][enabled', $this->bool( $banner, 'enabled' ), __( 'Show the consent banner to visitors.', 'consentful' ) )
		);
		$this->row(
			__( 'Position', 'consentful' ),
			'banner][position',
			fn () => $this->select_field( 'banner][position', $this->position_choices(), $this->str( $banner, 'position' ) ),
			__( 'The bottom bar blocks no interaction (recommended for strict opt-in regimes such as Loi 25 / GDPR). A centered modal covers the page until a choice is made, which some regulators treat as a cookie wall.', 'consentful' )
		);
		$this->row(
			__( 'Theme', 'consentful' ),
			'banner][theme',
			fn () => $this->select_field( 'banner][theme', $this->theme_choices(), $this->str( $banner, 'theme' ) )
		);
		$this->row(
			__( 'Primary color', 'consentful' ),
			'banner][primaryColor',
			fn () => $this->color_field( 'banner][primaryColor', $this->str( $banner, 'primaryColor' ) ),
			__( 'Used for the primary button and links. The button text color is chosen automatically for contrast.', 'consentful' )
		);
		$this->row(
			__( 'Corner radius (px)', 'consentful' ),
			'banner][radius',
			fn () => $this->number_field( 'banner][radius', $this->int( $banner, 'radius' ) ),
			__( '0 = square, 32 = pill.', 'consentful' )
		);
		$this->row(
			__( 'Privacy policy URL', 'consentful' ),
			'banner][privacyUrl',
			fn () => $this->url_field( 'banner][privacyUrl', $this->str( $banner, 'privacyUrl' ), get_privacy_policy_url() ),
			__( 'Leave blank to use the privacy page configured in WordPress.', 'consentful' )
		);

		echo '</tbody></table>';
	}

	private function render_purpose_fields( Settings $settings ): void {
		$stored   = $settings->purposes();
		$defaults = BannerConfig::defaults()->purposes;

		echo '<h2 class="title">' . esc_html__( 'Purposes', 'consentful' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Override the label and description shown for each consent category. Leave a field blank to use the translated default.', 'consentful' ) . '</p>';

		foreach ( array( 'necessary', 'functional', 'analytics', 'marketing' ) as $key ) {
			$default = is_array( $defaults[ $key ] ?? null ) ? $defaults[ $key ] : array();
			$entry   = is_array( $stored[ $key ] ?? null ) ? $stored[ $key ] : array();

			echo '<h3>' . esc_html( $this->str( $default, 'label' ) ) . '</h3>';
			echo '<table class="form-table" role="presentation"><tbody>';
			$this->row(
				__( 'Label', 'consentful' ),
				'purposes][' . $key . '][label',
				fn () => $this->text_field( 'purposes][' . $key . '][label', $this->str( $entry, 'label' ), $this->str( $default, 'label' ) )
			);
			$this->row(
				__( 'Description', 'consentful' ),
				'purposes][' . $key . '][description',
				fn () => $this->text_field( 'purposes][' . $key . '][description', $this->str( $entry, 'description' ), $this->str( $default, 'description' ) )
			);
			echo '</tbody></table>';
		}

		$personalization = is_array( $stored['personalization'] ?? null ) ? $stored['personalization'] : array();
		echo '<table class="form-table" role="presentation"><tbody>';
		$this->row(
			__( 'Personalization', 'consentful' ),
			'purposes][personalization][enabled',
			fn () => $this->checkbox_field( 'purposes][personalization][enabled', $this->bool( $personalization, 'enabled' ), __( 'Add a Personalization category (tailors content and recommendations to the visitor).', 'consentful' ) )
		);
		echo '</tbody></table>';
	}

	private function render_tag_fields( Settings $settings ): void {
		$stored = $this->tags_by_id( $settings );

		echo '<h2 class="title">' . esc_html__( 'Tags', 'consentful' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Enable the integrations you use. Consentful gates each one behind the selected purposes.', 'consentful' ) . '</p>';

		foreach ( $this->catalog->entries() as $entry ) {
			if ( 'custom' === $entry->key() ) {
				continue;
			}
			$this->render_catalog_entry( $entry, $stored[ $entry->key() ] ?? array() );
		}

		$this->render_custom_tags( $stored );
	}

	/**
	 * @param array<string, mixed> $tag
	 */
	private function render_catalog_entry( CatalogEntry $entry, array $tag ): void {
		$key    = $entry->key();
		$prefix = 'tags][' . $key;

		echo '<h3>' . esc_html( $entry->label() ) . '</h3>';
		$this->hidden_field( $prefix . '][id', $key );
		$this->hidden_field( $prefix . '][catalog', $key );

		echo '<table class="form-table" role="presentation"><tbody>';

		$this->row(
			__( 'Enabled', 'consentful' ),
			$prefix . '][enabled',
			fn () => $this->checkbox_field( $prefix . '][enabled', $this->bool( $tag, 'enabled' ), __( 'Load this integration for consenting visitors.', 'consentful' ) )
		);

		$fields = is_array( $tag['fields'] ?? null ) ? $tag['fields'] : array();
		foreach ( $entry->fields() as $field => $schema ) {
			$this->render_tag_field( $prefix . '][fields][' . $field, $field, $schema, $fields );
		}

		$this->row(
			__( 'Purposes', 'consentful' ),
			$prefix . '][purposes',
			fn () => $this->purpose_checkboxes( $prefix, $this->tag_purposes( $tag, $entry->default_purposes() ) )
		);

		echo '</tbody></table>';
	}

	/**
	 * @param array<string, array<string, mixed>> $stored
	 */
	private function render_custom_tags( array $stored ): void {
		echo '<h3>' . esc_html__( 'Custom snippets', 'consentful' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Group one or more scripts under a name and gate them behind consent. Each script is injected only when the snippet\'s purposes are granted — never printed directly — at the head, body, or footer you choose.', 'consentful' ) . '</p>';
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Editing snippet code requires the unfiltered_html capability, so the code below is read-only.', 'consentful' ) . '</p></div>';
		}

		$next = 1;
		foreach ( $stored as $tag ) {
			if ( 'custom' === ( $tag['catalog'] ?? '' ) ) {
				$next = max( $next, $this->custom_index( $this->str( $tag, 'id' ) ) + 1 );
			}
		}

		echo '<div id="consentful-custom-rows" data-next-index="' . esc_attr( (string) ( $next + 1 ) ) . '">';
		foreach ( $stored as $tag ) {
			if ( 'custom' === ( $tag['catalog'] ?? '' ) ) {
				$this->render_custom_row( $this->str( $tag, 'id' ), $tag );
			}
		}
		$this->render_custom_row( 'custom-' . $next, array() );
		echo '</div>';

		echo '<p><button type="button" class="button consentful-add-snippet">' . esc_html__( 'Add snippet', 'consentful' ) . '</button></p>';

		echo '<template id="consentful-custom-template">';
		$this->render_custom_row( 'custom-__INDEX__', array() );
		echo '</template>';
	}

	/**
	 * @param array<string, mixed> $tag
	 */
	private function render_custom_row( string $id, array $tag ): void {
		$prefix    = 'tags][' . $id;
		$fields    = is_array( $tag['fields'] ?? null ) ? $tag['fields'] : array();
		$fragments = is_array( $fields['fragments'] ?? null ) ? array_values( $fields['fragments'] ) : array();

		echo '<div class="consentful-snippet">';
		$this->hidden_field( $prefix . '][id', $id );
		$this->hidden_field( $prefix . '][catalog', 'custom' );

		echo '<div class="consentful-snippet-head"><strong>' . esc_html__( 'Custom snippet', 'consentful' ) . '</strong>';
		echo '<button type="button" class="button-link button-link-delete consentful-remove-snippet">' . esc_html__( 'Remove snippet', 'consentful' ) . '</button></div>';

		echo '<table class="form-table" role="presentation"><tbody>';
		$this->row(
			__( 'Name', 'consentful' ),
			$prefix . '][label',
			fn () => $this->text_field( $prefix . '][label', $this->str( $tag, 'label' ), __( 'e.g. Hotjar', 'consentful' ) )
		);
		$this->row(
			__( 'Purposes', 'consentful' ),
			$prefix . '][purposes',
			fn () => $this->purpose_checkboxes( $prefix, $this->tag_purposes( $tag, array() ) )
		);
		echo '</tbody></table>';

		echo '<p class="consentful-scripts-label"><strong>' . esc_html__( 'Scripts', 'consentful' ) . '</strong></p>';
		echo '<div class="consentful-fragments" data-next-frag="' . esc_attr( (string) ( count( $fragments ) + 1 ) ) . '">';
		foreach ( $fragments as $index => $fragment ) {
			$this->render_fragment_row( $prefix, (string) $index, is_array( $fragment ) ? $fragment : array() );
		}
		$this->render_fragment_row( $prefix, (string) count( $fragments ), array() );
		echo '</div>';

		echo '<p><button type="button" class="button consentful-add-fragment">' . esc_html__( 'Add script', 'consentful' ) . '</button></p>';

		echo '<template class="consentful-fragment-template">';
		$this->render_fragment_row( $prefix, '__FRAG__', array() );
		echo '</template>';

		echo '</div>';
	}

	/**
	 * @param array<array-key, mixed> $fragment
	 */
	private function render_fragment_row( string $prefix, string $index, array $fragment ): void {
		$base = $prefix . '][fields][fragments][' . $index;

		echo '<div class="consentful-fragment">';
		$this->textarea_field( $base . '][code', $this->str( $fragment, 'code' ), '<script>…</script>', ! current_user_can( 'unfiltered_html' ) );
		echo '<div class="consentful-fragment-meta"><label>' . esc_html__( 'Location', 'consentful' ) . ' ';
		$this->select_field( $base . '][location', $this->location_choices(), $this->snippet_location( $fragment ) );
		echo '</label>';
		echo '<button type="button" class="button-link button-link-delete consentful-remove-fragment">' . esc_html__( 'Remove script', 'consentful' ) . '</button>';
		echo '</div></div>';
	}

	private static function repeater_js(): string {
		return <<<'JS'
( function () {
	var list = document.getElementById( 'consentful-custom-rows' );
	var snippetTpl = document.getElementById( 'consentful-custom-template' );
	if ( ! list || ! snippetTpl ) {
		return;
	}
	function appendClone( container, html ) {
		var holder = document.createElement( 'div' );
		holder.innerHTML = html;
		var node = holder.firstElementChild;
		if ( node ) {
			container.appendChild( node );
		}
	}
	document.addEventListener( 'click', function ( event ) {
		var el = event.target;
		if ( ! el || ! el.closest ) {
			return;
		}
		if ( el.closest( '.consentful-add-snippet' ) ) {
			event.preventDefault();
			var next = parseInt( list.getAttribute( 'data-next-index' ), 10 ) || 1;
			list.setAttribute( 'data-next-index', String( next + 1 ) );
			appendClone( list, snippetTpl.innerHTML.replace( /__INDEX__/g, String( next ) ) );
			return;
		}
		var removeSnippet = el.closest( '.consentful-remove-snippet' );
		if ( removeSnippet ) {
			event.preventDefault();
			var snippet = removeSnippet.closest( '.consentful-snippet' );
			if ( snippet && snippet.parentNode ) {
				snippet.parentNode.removeChild( snippet );
			}
			return;
		}
		var addFragment = el.closest( '.consentful-add-fragment' );
		if ( addFragment ) {
			event.preventDefault();
			var owner = addFragment.closest( '.consentful-snippet' );
			var frags = owner && owner.querySelector( '.consentful-fragments' );
			var fragTpl = owner && owner.querySelector( '.consentful-fragment-template' );
			if ( ! frags || ! fragTpl ) {
				return;
			}
			var fnext = parseInt( frags.getAttribute( 'data-next-frag' ), 10 ) || 0;
			frags.setAttribute( 'data-next-frag', String( fnext + 1 ) );
			appendClone( frags, fragTpl.innerHTML.replace( /__FRAG__/g, String( fnext ) ) );
			return;
		}
		var removeFragment = el.closest( '.consentful-remove-fragment' );
		if ( removeFragment ) {
			event.preventDefault();
			var fragment = removeFragment.closest( '.consentful-fragment' );
			if ( fragment && fragment.parentNode ) {
				fragment.parentNode.removeChild( fragment );
			}
		}
	} );
} )();
JS;
	}

	private static function admin_css(): string {
		return '.consentful-snippet{border:1px solid #c3c4c7;background:#fff;border-radius:4px;'
			. 'padding:0 16px 12px;margin:0 0 16px;max-width:820px}'
			. '.consentful-snippet-head{display:flex;align-items:center;justify-content:space-between;'
			. 'gap:12px;border-bottom:1px solid #f0f0f1;padding:10px 0;margin-bottom:4px}'
			. '.consentful-scripts-label{margin:12px 0 4px}'
			. '.consentful-fragment{border-left:3px solid #dcdcde;padding:8px 0 8px 12px;margin:8px 0}'
			. '.consentful-fragment textarea{width:100%}'
			. '.consentful-fragment-meta{display:flex;align-items:center;gap:12px;margin-top:6px;flex-wrap:wrap}';
	}

	private function render_geo_fields( Settings $settings ): void {
		$geo = $settings->geo();

		echo '<h2 class="title">' . esc_html__( 'Jurisdiction', 'consentful' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'By default Consentful adapts to each visitor\'s region — opt-in where required (Loi 25 / GDPR), opt-out in US states, and the strictest posture until the region is known. Turn this off to apply one posture everywhere.', 'consentful' ) . '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';

		$this->row(
			__( 'Region', 'consentful' ),
			'geo][adaptive',
			fn () => $this->checkbox_field( 'geo][adaptive', $this->bool( $geo, 'adaptive' ), __( 'Adapt to the visitor\'s region (recommended).', 'consentful' ) )
		);
		$this->row(
			__( 'Global posture', 'consentful' ),
			'geo][globalPolicy',
			fn () => $this->select_field( 'geo][globalPolicy', $this->policy_choices(), $this->str( $geo, 'globalPolicy' ) ),
			__( 'Applied to all visitors when region adaptation is off.', 'consentful' )
		);

		echo '</tbody></table>';
	}

	public function render_log_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$page  = $this->current_page();
		$total = $this->reader->count();
		$rows  = $this->reader->recent( self::PER_PAGE, ( $page - 1 ) * self::PER_PAGE );

		echo '<div class="wrap consentful">';
		echo '<h1>' . esc_html__( 'Consent log', 'consentful' ) . '</h1>';

		echo '<form action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post">';
		echo '<input type="hidden" name="action" value="consentful_export" />';
		wp_nonce_field( self::NONCE_EXPORT );
		submit_button( __( 'Export CSV', 'consentful' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<p class="description">' . esc_html(
			sprintf(
				/* translators: %d: total number of consent records. */
				__( '%d records', 'consentful' ),
				$total
			)
		) . '</p>';

		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( __( 'Recorded', 'consentful' ), __( 'Jurisdiction', 'consentful' ), __( 'Policy', 'consentful' ), __( 'Schema', 'consentful' ), __( 'Banner', 'consentful' ), __( 'Purposes', 'consentful' ), __( 'IP hash', 'consentful' ), __( 'UA hash', 'consentful' ) ) as $heading ) {
			echo '<th>' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $rows as $export ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) $export['created_at'] ) . '</td>';
			echo '<td>' . esc_html( (string) $export['jurisdiction'] ) . '</td>';
			echo '<td>' . esc_html( (string) $export['policy_version'] ) . '</td>';
			echo '<td>' . esc_html( (string) $export['schema_version'] ) . '</td>';
			echo '<td>' . esc_html( (string) $export['banner_version'] ) . '</td>';
			echo '<td>' . esc_html( (string) $export['purposes'] ) . '</td>';
			echo '<td><code>' . esc_html( $this->truncate( (string) $export['ip_hash'] ) ) . '</code></td>';
			echo '<td><code>' . esc_html( $this->truncate( (string) $export['ua_hash'] ) ) . '</code></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	public function handle_export(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to export the consent log.', 'consentful' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::NONCE_EXPORT );

		ConsentLogDownload::stream( $this->export_csv_body() );
	}

	public function export_csv_body(): string {
		return ConsentLogExporter::to_csv( $this->reader->all_export_rows() );
	}

	/**
	 * @param callable():void $control
	 */
	private function row( string $label, string $field, callable $control, string $description = '' ): void {
		echo '<tr><th scope="row"><label for="' . esc_attr( $this->control_id( $field ) ) . '">' . esc_html( $label ) . '</label></th><td>';
		$control();
		if ( '' !== $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
		echo '</td></tr>';
	}

	private function control_id( string $field ): string {
		return 'consentful-' . trim( str_replace( array( '][', '[', ']' ), '-', $field ), '-' );
	}

	private function render_tag_field( string $name, string $field, mixed $schema, mixed $fields ): void {
		$schema      = is_array( $schema ) ? $schema : array();
		$fields      = is_array( $fields ) ? $fields : array();
		$value       = $this->str( $fields, $field );
		$label       = $this->str( $schema, 'label' );
		$placeholder = $this->str( $schema, 'placeholder' );
		$type        = $this->str( $schema, 'type' );

		$this->row(
			$label,
			$name,
			fn () => 'url' === $type
				? $this->url_field( $name, $value, $placeholder )
				: $this->text_field( $name, $value, $placeholder )
		);
	}

	private function checkbox_field( string $field, bool $value, string $label = '' ): void {
		$id   = $this->control_id( $field );
		$name = CONSENTFUL_OPTION . '[' . $field . ']';
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0" />';
		echo '<label><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( $value, true, false ) . ' /> ' . esc_html( $label ) . '</label>';
	}

	/**
	 * @param array<string, string> $choices
	 */
	private function select_field( string $field, array $choices, string $value ): void {
		$id   = $this->control_id( $field );
		$name = CONSENTFUL_OPTION . '[' . $field . ']';
		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
		foreach ( $choices as $option => $label ) {
			echo '<option value="' . esc_attr( $option ) . '"' . selected( $value, $option, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	private function color_field( string $field, string $value ): void {
		$id      = $this->control_id( $field );
		$name    = CONSENTFUL_OPTION . '[' . $field . ']';
		$default = $this->str( Settings::defaults()['banner'] ?? array(), 'primaryColor' );
		echo '<input type="text" id="' . esc_attr( $id ) . '" class="consentful-color" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" data-default-color="' . esc_attr( $default ) . '" />';
	}

	private function number_field( string $field, int $value ): void {
		$id   = $this->control_id( $field );
		$name = CONSENTFUL_OPTION . '[' . $field . ']';
		echo '<input type="number" id="' . esc_attr( $id ) . '" class="small-text" min="0" max="32" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" />';
	}

	private function text_field( string $field, string $value, string $placeholder = '' ): void {
		$id   = $this->control_id( $field );
		$name = CONSENTFUL_OPTION . '[' . $field . ']';
		echo '<input type="text" id="' . esc_attr( $id ) . '" class="regular-text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '" />';
	}

	private function url_field( string $field, string $value, string $placeholder = '' ): void {
		$id   = $this->control_id( $field );
		$name = CONSENTFUL_OPTION . '[' . $field . ']';
		echo '<input type="url" id="' . esc_attr( $id ) . '" class="regular-text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '" />';
	}

	private function textarea_field( string $field, string $value, string $placeholder = '', bool $read_only = false ): void {
		$id   = $this->control_id( $field );
		$name = CONSENTFUL_OPTION . '[' . $field . ']';
		echo '<textarea id="' . esc_attr( $id ) . '" class="large-text code" rows="4" name="' . esc_attr( $name ) . '" placeholder="' . esc_attr( $placeholder ) . '"' . ( $read_only ? ' readonly' : '' ) . '>' . esc_textarea( $value ) . '</textarea>';
	}

	private function hidden_field( string $field, string $value ): void {
		$name = CONSENTFUL_OPTION . '[' . $field . ']';
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" />';
	}

	/**
	 * @param list<string> $checked
	 */
	private function purpose_checkboxes( string $prefix, array $checked ): void {
		$name = CONSENTFUL_OPTION . '[' . $prefix . '][purposes][]';
		foreach ( $this->purpose_choices() as $key => $label ) {
			echo '<label style="margin-right:1em;"><input type="checkbox" name="' . esc_attr( $name ) . '" value="' . esc_attr( $key ) . '"' . checked( in_array( $key, $checked, true ), true, false ) . ' /> ' . esc_html( $label ) . '</label>';
		}
	}

	/**
	 * @param array<string, mixed> $tag
	 * @param list<string>         $fallback
	 * @return list<string>
	 */
	private function tag_purposes( array $tag, array $fallback ): array {
		$stored = $tag['purposes'] ?? null;
		if ( ! is_array( $stored ) || array() === $stored ) {
			return $fallback;
		}
		return array_values( array_filter( $stored, 'is_string' ) );
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function tags_by_id( Settings $settings ): array {
		$out = array();
		foreach ( $settings->tags() as $tag ) {
			$id = $this->str( $tag, 'id' );
			if ( '' !== $id ) {
				$out[ $id ] = $tag;
			}
		}
		return $out;
	}

	private function custom_index( string $id ): int {
		return 1 === preg_match( '/^custom-(\d+)$/', $id, $m ) ? (int) $m[1] : 0;
	}

	/**
	 * @return array<string, string>
	 */
	private function position_choices(): array {
		return array(
			'bar'    => __( 'Bottom bar (full width)', 'consentful' ),
			'corner' => __( 'Floating card (bottom corner)', 'consentful' ),
			'modal'  => __( 'Centered modal', 'consentful' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	private function theme_choices(): array {
		return array(
			'auto'  => __( "Auto (match the visitor's system)", 'consentful' ),
			'light' => __( 'Light', 'consentful' ),
			'dark'  => __( 'Dark', 'consentful' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	private function policy_choices(): array {
		return array(
			'opt_in'      => __( 'Opt-in (deny by default, show banner)', 'consentful' ),
			'opt_out'     => __( 'Opt-out (allow by default, offer opt-out)', 'consentful' ),
			'notice_only' => __( 'Notice only', 'consentful' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	private function location_choices(): array {
		return array(
			'head'   => __( 'Head', 'consentful' ),
			'body'   => __( 'Body (top)', 'consentful' ),
			'footer' => __( 'Footer (end of body)', 'consentful' ),
		);
	}

	private function snippet_location( mixed $fields ): string {
		$location = $this->str( $fields, 'location' );
		return in_array( $location, array( 'head', 'body', 'footer' ), true ) ? $location : 'head';
	}

	/**
	 * @return array<string, string>
	 */
	private function purpose_choices(): array {
		return array(
			'functional'      => __( 'Functional', 'consentful' ),
			'analytics'       => __( 'Analytics', 'consentful' ),
			'marketing'       => __( 'Marketing', 'consentful' ),
			'personalization' => __( 'Personalization', 'consentful' ),
		);
	}

	private function str( mixed $map, string $key ): string {
		if ( ! is_array( $map ) ) {
			return '';
		}
		$value = $map[ $key ] ?? '';
		return is_scalar( $value ) ? (string) $value : '';
	}

	private function int( mixed $map, string $key ): int {
		if ( ! is_array( $map ) ) {
			return 0;
		}
		$value = $map[ $key ] ?? 0;
		return is_numeric( $value ) ? (int) $value : 0;
	}

	private function bool( mixed $map, string $key ): bool {
		return is_array( $map ) && (bool) ( $map[ $key ] ?? false );
	}

	private function truncate( string $hash ): string {
		return '' === $hash ? '' : substr( $hash, 0, 12 ) . '…';
	}

	private function current_page(): int {
		$raw  = isset( $_GET['paged'] ) ? wp_unslash( $_GET['paged'] ) : '1';
		$page = absint( is_scalar( $raw ) ? $raw : 1 );
		return max( 1, $page );
	}
}

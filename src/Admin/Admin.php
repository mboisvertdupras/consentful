<?php
declare( strict_types = 1 );

namespace Consentful\Admin;

use Consentful\Container\Container;
use Consentful\Consent\ConsentLogExporter;
use Consentful\Frontend\BannerConfig;
use Consentful\Tag\Tag;
use Consentful\Tag\TagRegistry;

/**
 * The Site-owner admin surface (the only admin-context WP coupling). A deliberately small
 * two-tier UI: the Integrator's code/config is the source of truth (Layer 1), the Site
 * owner edits only unlocked fields of the `consentful_settings` option (Layer 2). The
 * trust boundary is enforced here — `manage_options` on every screen and the export
 * action, a nonce on save + export, sanitize on input, escape on output.
 *
 * Logic lives in pure, unit-tested classes (Settings, ConsentLogReader, the
 * ConsentLogExporter); this shell only registers hooks and renders escaped markup. The
 * export body is built by `export_csv_body()` (tested) so the header-send / `wp_die`
 * stays out of the tested core.
 */
final class Admin {

	private const CAPABILITY    = 'manage_options';
	private const SETTINGS_PAGE = 'consentful';
	private const LOG_PAGE      = 'consentful-log';
	private const OPTION_GROUP  = 'consentful';
	private const NONCE_EXPORT  = 'consentful_export';
	private const PER_PAGE      = 50;

	public function __construct(
		private readonly Container $container,
	) {}

	/** Build from a plugin container. */
	public static function for_container( Container $container ): self {
		return new self( $container );
	}

	/** Wire the admin menu, settings registration and the export handler. */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_consentful_export', array( $this, 'handle_export' ) );
	}

	/** Register the Settings page and the Consent-log submenu (both `manage_options`). */
	public function register_menu(): void {
		add_menu_page(
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

	/** Register the single option with the pure `Settings::sanitize` as its callback. */
	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			CONSENTFUL_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => static fn ( $raw ): array => Settings::sanitize( is_array( $raw ) ? $raw : array(), Settings::locked_fields() ),
				'default'           => array(),
			)
		);
	}

	/** Render the constrained settings form. Capability is checked before any output. */
	public function render_settings_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$settings = Settings::from_wp();
		$base     = $this->banner_config();
		$tags     = $this->toggleable_tags();

		echo '<div class="wrap consentful">';
		echo '<h1>' . esc_html__( 'Consentful settings', 'consentful' ) . '</h1>';
		echo '<form action="options.php" method="post">';
		settings_fields( self::OPTION_GROUP );
		$this->render_appearance_fields( $settings, $base );
		$this->render_copy_fields( $settings, $base );
		$this->render_tag_fields( $settings, $tags );
		submit_button();
		echo '</form>';
		echo '</div>';
	}

	/** Render the appearance fields (enabled, position, theme, color, radius, privacy URL). */
	private function render_appearance_fields( Settings $settings, BannerConfig $base ): void {
		echo '<h2>' . esc_html__( 'Appearance', 'consentful' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';

		$this->row(
			__( 'Show banner', 'consentful' ),
			'enabled',
			$settings,
			fn () => $this->checkbox_field( 'enabled', $this->effective_bool( $settings, 'enabled', $base->enabled ), $settings->is_locked( 'enabled' ) )
		);
		$this->row(
			__( 'Position', 'consentful' ),
			'position',
			$settings,
			fn () => $this->select_field( 'position', array( 'bar', 'corner', 'modal' ), $this->effective_string( $settings, 'position', $base->position ), $settings->is_locked( 'position' ) )
		);
		$this->row(
			__( 'Theme', 'consentful' ),
			'theme',
			$settings,
			fn () => $this->select_field( 'theme', array( 'light', 'dark', 'auto' ), $this->effective_string( $settings, 'theme', $base->theme ), $settings->is_locked( 'theme' ) )
		);
		$this->row(
			__( 'Primary color', 'consentful' ),
			'primaryColor',
			$settings,
			fn () => $this->color_field( 'primaryColor', $this->effective_string( $settings, 'primaryColor', $base->primary_color ), $settings->is_locked( 'primaryColor' ) )
		);
		$this->row(
			__( 'Corner radius (px)', 'consentful' ),
			'radius',
			$settings,
			fn () => $this->number_field( 'radius', $this->effective_int( $settings, 'radius', $base->radius ), $settings->is_locked( 'radius' ) )
		);
		$this->row(
			__( 'Privacy policy URL', 'consentful' ),
			'privacyUrl',
			$settings,
			fn () => $this->url_field( 'privacyUrl', $this->effective_string( $settings, 'privacyUrl', $base->privacy_url ), $settings->is_locked( 'privacyUrl' ) )
		);

		echo '</tbody></table>';
	}

	/** Render the editable banner copy (description is a textarea; the rest are text). */
	private function render_copy_fields( Settings $settings, BannerConfig $base ): void {
		echo '<h2>' . esc_html__( 'Copy', 'consentful' ) . '</h2>';

		$locked = $settings->is_locked( 'copy' );
		if ( $locked ) {
			echo '<p class="description">' . esc_html__( 'Banner copy is locked by your developer.', 'consentful' ) . '</p>';
		}

		$stored = $settings->stored( 'copy' );
		$stored = is_array( $stored ) ? $stored : array();

		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( $base->copy as $key => $default ) {
			$value = isset( $stored[ $key ] ) && is_scalar( $stored[ $key ] ) ? (string) $stored[ $key ] : $default;
			$name  = CONSENTFUL_OPTION . '[copy][' . $key . ']';

			echo '<tr><th scope="row"><label for="' . esc_attr( 'consentful-copy-' . $key ) . '">' . esc_html( (string) $key ) . '</label></th><td>';
			if ( 'description' === $key || 'noticeDescription' === $key ) {
				echo '<textarea id="' . esc_attr( 'consentful-copy-' . $key ) . '" name="' . esc_attr( $name ) . '" rows="2" class="large-text"' . esc_attr( $this->disabled_attr( $locked ) ) . '>' . esc_textarea( $value ) . '</textarea>';
			} else {
				echo '<input type="text" id="' . esc_attr( 'consentful-copy-' . $key ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="regular-text"' . esc_attr( $this->disabled_attr( $locked ) ) . ' />';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Render one checkbox per toggleable Tag. A Site owner can disable a pre-approved Tag;
	 * the whole list is read-only when `tags` is locked.
	 *
	 * @param list<Tag> $tags
	 */
	private function render_tag_fields( Settings $settings, array $tags ): void {
		if ( array() === $tags ) {
			return;
		}

		echo '<h2>' . esc_html__( 'Tags', 'consentful' ) . '</h2>';

		$locked = $settings->is_locked( 'tags' );
		if ( $locked ) {
			echo '<p class="description">' . esc_html__( 'Tag visibility is locked by your developer.', 'consentful' ) . '</p>';
		}

		$stored = $settings->stored( 'tags' );
		$stored = is_array( $stored ) ? $stored : array();

		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( $tags as $tag ) {
			$enabled = ! array_key_exists( $tag->id, $stored ) || false !== $stored[ $tag->id ];
			$name    = CONSENTFUL_OPTION . '[tags][' . $tag->id . ']';

			echo '<tr><th scope="row">' . esc_html( $tag->label ) . '</th><td>';
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0" />';
			echo '<label><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( $enabled, true, false ) . esc_attr( $this->disabled_attr( $locked ) ) . ' /> ' . esc_html__( 'Enabled', 'consentful' ) . '</label>';
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/** Render the paginated Consent-log table + the Export CSV button. */
	public function render_log_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$reader = $this->reader();
		$page   = $this->current_page();
		$total  = $reader->count();
		$rows   = $reader->recent( self::PER_PAGE, ( $page - 1 ) * self::PER_PAGE );

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

	/**
	 * The export handler: verify capability + nonce, then stream the CSV via the isolated
	 * ConsentLogDownload shell (which holds the one non-HTML echo, keeping EscapeOutput
	 * enforced on every rendering screen here). The tested data path is `export_csv_body`.
	 */
	public function handle_export(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to export the consent log.', 'consentful' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::NONCE_EXPORT );

		ConsentLogDownload::stream( $this->export_csv_body() );
	}

	/** The CSV body for the whole Consent log — the pure, tested export data path. */
	public function export_csv_body(): string {
		return ConsentLogExporter::to_csv( $this->reader()->all_export_rows() );
	}

	/**
	 * Render a labeled form row, invoking `$control` to print the (inline-escaped) control
	 * and appending a lock note when the field is locked.
	 *
	 * @param string          $label   The field label.
	 * @param string          $field   The top-level field key.
	 * @param callable():void $control Prints the control markup, escaping inline.
	 */
	private function row( string $label, string $field, Settings $settings, callable $control ): void {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
		$control();
		if ( $settings->is_locked( $field ) ) {
			echo ' <span class="description">' . esc_html__( 'Locked by your developer.', 'consentful' ) . '</span>';
		}
		echo '</td></tr>';
	}

	/** The ` disabled` attribute fragment when locked, else empty. */
	private function disabled_attr( bool $locked ): string {
		return $locked ? ' disabled' : '';
	}

	private function checkbox_field( string $field, bool $value, bool $locked ): void {
		$name = CONSENTFUL_OPTION . '[' . $field . ']';
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0" />';
		echo '<input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( $value, true, false ) . esc_attr( $this->disabled_attr( $locked ) ) . ' />';
	}

	/**
	 * @param list<string> $options
	 */
	private function select_field( string $field, array $options, string $value, bool $locked ): void {
		$name = CONSENTFUL_OPTION . '[' . $field . ']';
		echo '<select name="' . esc_attr( $name ) . '"' . esc_attr( $this->disabled_attr( $locked ) ) . '>';
		foreach ( $options as $option ) {
			echo '<option value="' . esc_attr( $option ) . '"' . selected( $value, $option, false ) . '>' . esc_html( $option ) . '</option>';
		}
		echo '</select>';
	}

	private function color_field( string $field, string $value, bool $locked ): void {
		$name = CONSENTFUL_OPTION . '[' . $field . ']';
		echo '<input type="color" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . esc_attr( $this->disabled_attr( $locked ) ) . ' />';
	}

	private function number_field( string $field, int $value, bool $locked ): void {
		$name = CONSENTFUL_OPTION . '[' . $field . ']';
		echo '<input type="number" min="0" max="32" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '"' . esc_attr( $this->disabled_attr( $locked ) ) . ' />';
	}

	private function url_field( string $field, string $value, bool $locked ): void {
		$name = CONSENTFUL_OPTION . '[' . $field . ']';
		echo '<input type="url" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="regular-text"' . esc_attr( $this->disabled_attr( $locked ) ) . ' />';
	}

	/**
	 * The effective string value for a field: the stored override (when unlocked, scalar)
	 * else the integrator's base.
	 */
	private function effective_string( Settings $settings, string $field, string $base ): string {
		if ( $settings->is_locked( $field ) ) {
			return $base;
		}
		$stored = $settings->stored( $field );
		return is_scalar( $stored ) ? (string) $stored : $base;
	}

	/** The effective int value for a field (number controls need a definite int). */
	private function effective_int( Settings $settings, string $field, int $base ): int {
		if ( $settings->is_locked( $field ) ) {
			return $base;
		}
		$stored = $settings->stored( $field );
		return is_numeric( $stored ) ? (int) $stored : $base;
	}

	/** The effective boolean for a field (checkboxes need a definite bool). */
	private function effective_bool( Settings $settings, string $field, bool $base ): bool {
		if ( $settings->is_locked( $field ) ) {
			return $base;
		}
		$stored = $settings->stored( $field );
		return null === $stored ? $base : (bool) $stored;
	}

	/** Truncate a pseudonymous hash for compact display (full value is in the export). */
	private function truncate( string $hash ): string {
		return '' === $hash ? '' : substr( $hash, 0, 12 ) . '…';
	}

	/** The requested log page (1-based, clamped to ≥ 1). */
	private function current_page(): int {
		$raw  = isset( $_GET['paged'] ) ? wp_unslash( $_GET['paged'] ) : '1';
		$page = absint( is_scalar( $raw ) ? $raw : 1 );
		return max( 1, $page );
	}

	/**
	 * The toggleable Tags (only these appear in the admin Tag list).
	 *
	 * @return list<Tag>
	 */
	private function toggleable_tags(): array {
		/** @var TagRegistry $registry */
		$registry = $this->container->get( TagRegistry::class );

		$toggleable = array();
		foreach ( $registry->all() as $tag ) {
			if ( $tag->site_toggleable ) {
				$toggleable[] = $tag;
			}
		}
		return $toggleable;
	}

	private function banner_config(): BannerConfig {
		/** @var BannerConfig $banner */
		$banner = $this->container->get( BannerConfig::class );
		return $banner;
	}

	private function reader(): ConsentLogReader {
		/** @var ConsentLogReader $reader */
		$reader = $this->container->get( ConsentLogReader::class );
		return $reader;
	}
}

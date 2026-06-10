<?php
declare( strict_types = 1 );

namespace Consentful\Admin;

final class Settings {

	private const VERSION = 1;

	/** @var list<string> */
	private const POSITIONS = array( 'bar', 'corner', 'modal' );

	/** @var list<string> */
	private const THEMES = array( 'light', 'dark', 'auto' );

	private const MAX_RADIUS = 32;

	/** @var list<string> */
	private const PURPOSE_KEYS = array( 'necessary', 'functional', 'analytics', 'marketing', 'personalization' );

	/** @var list<string> */
	private const GLOBAL_POLICIES = array( 'opt_in', 'opt_out', 'notice_only' );

	/** @var list<string> */
	private const SNIPPET_LOCATIONS = array( 'head', 'body', 'footer' );

	/** @var array<string, list<string>> */
	private const CATALOG_FIELDS = array(
		'ga4'        => array( 'measurementId' ),
		'google-ads' => array( 'conversionId' ),
		'gtm'        => array( 'containerId' ),
		'meta-pixel' => array( 'pixelId' ),
		'custom'     => array(),
	);

	/**
	 * @param array<array-key, mixed> $stored
	 */
	public function __construct(
		private readonly array $stored,
	) {}

	public static function from_wp(): self {
		$stored = get_option( CONSENTFUL_OPTION, array() );
		return new self( is_array( $stored ) ? $stored : array() );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'version'  => self::VERSION,
			'banner'   => array(
				'enabled'      => true,
				'position'     => 'bar',
				'theme'        => 'auto',
				'primaryColor' => '#2563eb',
				'radius'       => 8,
				'privacyUrl'   => '',
			),
			'purposes' => array(
				'personalization' => array( 'enabled' => false ),
			),
			'tags'     => array(),
			'geo'      => array(
				'adaptive'     => true,
				'globalPolicy' => 'opt_in',
			),
			'proof'    => array(
				'enabled' => true,
			),
		);
	}

	/**
	 * @return array<array-key, mixed>
	 */
	public function all(): array {
		return $this->stored;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function effective(): array {
		return array(
			'banner'   => $this->banner(),
			'purposes' => $this->purposes(),
			'tags'     => $this->tags(),
			'geo'      => $this->geo(),
			'proof'    => $this->proof(),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function banner(): array {
		return $this->merged( 'banner' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function purposes(): array {
		/** @var array<string, mixed> $out */
		$out = self::default_section( 'purposes' );

		foreach ( self::array_value( $this->stored['purposes'] ?? null ) as $key => $value ) {
			$base        = self::array_value( $out[ $key ] ?? null );
			$out[ $key ] = array_merge( $base, self::array_value( $value ) );
		}
		return $out;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function tags(): array {
		$out = array();
		foreach ( self::array_value( $this->stored['tags'] ?? null ) as $tag ) {
			if ( is_array( $tag ) ) {
				$out[] = self::array_value( $tag );
			}
		}
		return $out;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function geo(): array {
		return $this->merged( 'geo' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function proof(): array {
		return $this->merged( 'proof' );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function merged( string $section ): array {
		return array_merge(
			self::default_section( $section ),
			self::array_value( $this->stored[ $section ] ?? null )
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function default_section( string $section ): array {
		return self::array_value( self::defaults()[ $section ] ?? null );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function array_value( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $key => $item ) {
			$out[ (string) $key ] = $item;
		}
		return $out;
	}

	/**
	 * @param array<array-key, mixed> $raw
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $raw ): array {
		$out = array( 'version' => self::VERSION );

		if ( array_key_exists( 'banner', $raw ) ) {
			$out['banner'] = self::sanitize_banner( $raw['banner'] );
		}
		if ( array_key_exists( 'purposes', $raw ) ) {
			$out['purposes'] = self::sanitize_purposes( $raw['purposes'] );
		}
		if ( array_key_exists( 'tags', $raw ) ) {
			$out['tags'] = self::sanitize_tags( $raw['tags'] );
		}
		if ( array_key_exists( 'geo', $raw ) ) {
			$out['geo'] = self::sanitize_geo( $raw['geo'] );
		}
		if ( array_key_exists( 'proof', $raw ) ) {
			$out['proof'] = self::sanitize_proof( $raw['proof'] );
		}

		return $out;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function sanitize_banner( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$out = array();
		if ( array_key_exists( 'enabled', $value ) ) {
			$out['enabled'] = (bool) $value['enabled'];
		}
		$position = self::in_list( $value['position'] ?? null, self::POSITIONS );
		if ( null !== $position ) {
			$out['position'] = $position;
		}
		$theme = self::in_list( $value['theme'] ?? null, self::THEMES );
		if ( null !== $theme ) {
			$out['theme'] = $theme;
		}
		$color = self::sanitize_color( $value['primaryColor'] ?? null );
		if ( null !== $color ) {
			$out['primaryColor'] = $color;
		}
		if ( array_key_exists( 'radius', $value ) ) {
			$out['radius'] = min( absint( self::to_string( $value['radius'] ) ), self::MAX_RADIUS );
		}
		if ( array_key_exists( 'privacyUrl', $value ) ) {
			$out['privacyUrl'] = esc_url_raw( self::to_string( $value['privacyUrl'] ) );
		}
		return $out;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function sanitize_purposes( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$out = array();
		foreach ( $value as $key => $purpose ) {
			if ( ! in_array( $key, self::PURPOSE_KEYS, true ) || ! is_array( $purpose ) ) {
				continue;
			}
			$entry = array();
			if ( array_key_exists( 'enabled', $purpose ) ) {
				$entry['enabled'] = (bool) $purpose['enabled'];
			}
			if ( array_key_exists( 'label', $purpose ) ) {
				$entry['label'] = sanitize_text_field( self::to_string( $purpose['label'] ) );
			}
			if ( array_key_exists( 'description', $purpose ) ) {
				$entry['description'] = sanitize_text_field( self::to_string( $purpose['description'] ) );
			}
			$out[ (string) $key ] = $entry;
		}
		return $out;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function sanitize_tags( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$out      = array();
		$seen     = array();
		$locked   = ! current_user_can( 'unfiltered_html' );
		$notified = false;
		foreach ( $value as $tag ) {
			if ( ! is_array( $tag ) ) {
				continue;
			}
			$catalog = sanitize_text_field( self::to_string( $tag['catalog'] ?? '' ) );
			$id      = sanitize_text_field( self::to_string( $tag['id'] ?? '' ) );
			if ( '' === $id || '' === $catalog || ! array_key_exists( $catalog, self::CATALOG_FIELDS ) || isset( $seen[ $id ] ) ) {
				continue;
			}

			if ( 'custom' === $catalog && $locked ) {
				$fields = self::previous_fields( $id );
				if ( ! $notified && self::sanitize_custom_fields( $tag['fields'] ?? null ) !== $fields ) {
					$notified = true;
					add_settings_error(
						CONSENTFUL_OPTION,
						'consentful_snippets_locked',
						__( 'Snippet code requires the unfiltered_html capability and was left unchanged. Other settings were saved.', 'consentful' )
					);
				}
			} else {
				$fields = self::sanitize_fields( $catalog, $tag['fields'] ?? null );
			}
			if ( 'meta-pixel' === $catalog ) {
				$fields = self::sanitize_pixel_id( $id, $fields );
			}
			if ( 'custom' === $catalog && array() === ( $fields['fragments'] ?? array() ) ) {
				continue;
			}
			$seen[ $id ] = true;

			$entry = array(
				'id'       => $id,
				'catalog'  => $catalog,
				'enabled'  => array_key_exists( 'enabled', $tag ) ? (bool) $tag['enabled'] : true,
				'purposes' => self::sanitize_purpose_keys( $tag['purposes'] ?? null ),
				'fields'   => $fields,
			);
			if ( array_key_exists( 'label', $tag ) ) {
				$entry['label'] = sanitize_text_field( self::to_string( $tag['label'] ) );
			}
			$out[] = $entry;
		}
		return $out;
	}

	/**
	 * @return list<string>
	 */
	private static function sanitize_purpose_keys( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $key ) {
			$key = self::to_string( $key );
			if ( in_array( $key, self::PURPOSE_KEYS, true ) && ! in_array( $key, $out, true ) ) {
				$out[] = $key;
			}
		}
		return $out;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function sanitize_fields( string $catalog, mixed $value ): array {
		if ( 'custom' === $catalog ) {
			return self::sanitize_custom_fields( $value );
		}

		$allowed = self::CATALOG_FIELDS[ $catalog ];
		if ( ! is_array( $value ) ) {
			return array();
		}

		$out = array();
		foreach ( $allowed as $field ) {
			if ( ! array_key_exists( $field, $value ) ) {
				continue;
			}
			$out[ $field ] = sanitize_text_field( self::to_string( $value[ $field ] ) );
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $fields
	 * @return array<string, mixed>
	 */
	private static function sanitize_pixel_id( string $id, array $fields ): array {
		$pixel = self::to_string( $fields['pixelId'] ?? '' );
		if ( '' === $pixel || 1 === preg_match( '/^\d+$/', $pixel ) ) {
			return $fields;
		}
		$fields['pixelId'] = self::to_string( self::previous_fields( $id )['pixelId'] ?? '' );
		add_settings_error(
			CONSENTFUL_OPTION,
			'consentful_pixel_id',
			__( 'The Meta Pixel ID must contain only digits. The previous value was kept.', 'consentful' )
		);
		return $fields;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function previous_fields( string $id ): array {
		foreach ( self::from_wp()->tags() as $tag ) {
			if ( ( $tag['id'] ?? '' ) === $id ) {
				return self::array_value( $tag['fields'] ?? null );
			}
		}
		return array();
	}

	/**
	 * @return array{fragments?: list<array{code: string, location: string}>}
	 */
	private static function sanitize_custom_fields( mixed $value ): array {
		if ( ! is_array( $value ) || ! is_array( $value['fragments'] ?? null ) ) {
			return array();
		}

		$fragments = array();
		foreach ( $value['fragments'] as $fragment ) {
			if ( ! is_array( $fragment ) ) {
				continue;
			}
			$code = self::to_string( $fragment['code'] ?? '' );
			if ( '' === $code ) {
				continue;
			}
			$fragments[] = array(
				'code'     => $code,
				'location' => self::in_list( self::to_string( $fragment['location'] ?? '' ), self::SNIPPET_LOCATIONS ) ?? 'head',
			);
		}

		return array() === $fragments ? array() : array( 'fragments' => $fragments );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function sanitize_geo( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		if ( array_key_exists( 'adaptive', $value ) ) {
			$out['adaptive'] = (bool) $value['adaptive'];
		}
		$policy = self::in_list( $value['globalPolicy'] ?? null, self::GLOBAL_POLICIES );
		if ( null !== $policy ) {
			$out['globalPolicy'] = $policy;
		}
		return $out;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function sanitize_proof( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		if ( array_key_exists( 'enabled', $value ) ) {
			$out['enabled'] = (bool) $value['enabled'];
		}
		return $out;
	}

	/**
	 * @param list<string> $allowed
	 */
	private static function in_list( mixed $value, array $allowed ): ?string {
		return is_string( $value ) && in_array( $value, $allowed, true ) ? $value : null;
	}

	private static function sanitize_color( mixed $value ): ?string {
		if ( null === $value ) {
			return null;
		}
		$color = sanitize_hex_color( self::to_string( $value ) );
		return is_string( $color ) && '' !== $color ? $color : null;
	}

	private static function to_string( mixed $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}
}

<?php
declare( strict_types = 1 );

namespace Consentful\Frontend;

use Consentful\Adapter\Adapter;
use Consentful\Adapter\AdapterRegistry;
use Consentful\Adapter\ConfiguredAdapter;
use Consentful\Adapter\GoogleAdapter;
use Consentful\Catalog\Catalog;
use Consentful\Catalog\CatalogEntry;
use Consentful\Consent\DefaultPurpose;
use Consentful\Consent\ProofConfig;
use Consentful\Consent\Purpose;
use Consentful\Consent\PurposeRegistry;
use Consentful\Jurisdiction\Jurisdiction;
use Consentful\Jurisdiction\JurisdictionRegistry;
use Consentful\Jurisdiction\Policy;
use Consentful\Tag\Delivery;
use Consentful\Tag\Tag;
use Consentful\Tag\TagRegistry;

final class SettingsHydrator {

	/**
	 * @param array<string, mixed> $settings
	 * @param list<Purpose>        $extra_purposes
	 * @param list<Adapter>        $extra_adapters
	 * @param list<Tag>            $extra_tags
	 */
	public function __construct(
		private readonly array $settings,
		private readonly Catalog $catalog,
		private readonly array $extra_purposes = array(),
		private readonly array $extra_adapters = array(),
		private readonly array $extra_tags = array(),
	) {}

	public function client_config(
		int $schema_version,
		int $policy_version,
		string $cookie,
		string $geo_endpoint_url,
		string $proof_endpoint_url,
		string $privacy_fallback_url,
	): ClientConfig {
		$purposes = $this->purpose_registry();

		$adapters = new AdapterRegistry();
		$tags     = new TagRegistry();
		$this->build_tags_and_adapters( $purposes, $tags, $adapters );

		return new ClientConfig(
			$purposes,
			$tags,
			$adapters,
			$this->jurisdiction_registry( $policy_version, $purposes ),
			$this->banner_config( $privacy_fallback_url ),
			$this->geo_config(),
			geo_endpoint_url: $geo_endpoint_url,
			proof: new ProofConfig( (bool) ( $this->section( 'proof' )['enabled'] ?? true ) ),
			proof_endpoint_url: $proof_endpoint_url,
			schema_version: $schema_version,
			policy_version: $policy_version,
			cookie: $cookie,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function section( string $key ): array {
		return self::map( $this->settings[ $key ] ?? null );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function map( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $key => $item ) {
			$out[ (string) $key ] = $item;
		}
		return $out;
	}

	private function purpose_registry(): PurposeRegistry {
		$purposes        = DefaultPurpose::defaults();
		$personalization = $this->section( 'purposes' )['personalization'] ?? null;
		if ( is_array( $personalization ) && (bool) ( $personalization['enabled'] ?? false ) ) {
			$purposes[] = DefaultPurpose::Personalization;
		}

		$registry = new PurposeRegistry( $purposes );
		foreach ( $this->extra_purposes as $purpose ) {
			$registry->add( $purpose );
		}
		return $registry;
	}

	private function build_tags_and_adapters( PurposeRegistry $purposes, TagRegistry $tags, AdapterRegistry $adapters ): void {
		$active_keys = $this->purpose_keys( $purposes );

		/** @var list<array{0: CatalogEntry, 1: array<string, mixed>}> $google */
		$google = array();

		foreach ( $this->stored_tags() as $tag ) {
			if ( ! (bool) ( $tag['enabled'] ?? true ) ) {
				continue;
			}
			$entry = $this->catalog->get( $this->str( $tag, 'catalog' ) );
			if ( null === $entry ) {
				continue;
			}

			if ( 'google' === $entry->handler() ) {
				$google[] = array( $entry, $tag );
				continue;
			}

			$id = $this->str( $tag, 'id' );
			$adapters->add( new ConfiguredAdapter( $id, $this->adapter_config( $entry, $tag ) ) );
			$tags->add(
				new Tag(
					$id,
					$this->tag_label( $entry, $tag ),
					$this->resolve_purposes( $purposes, $this->tag_purpose_keys( $entry, $tag, $active_keys ) ),
					$entry->delivery(),
					$id,
				)
			);
		}

		if ( array() !== $google ) {
			$this->add_google( $purposes, $tags, $adapters, $google, $active_keys );
		}

		foreach ( $this->extra_adapters as $adapter ) {
			$adapters->add( $adapter );
		}
		foreach ( $this->extra_tags as $tag ) {
			$tags->add( $tag );
		}
	}

	/**
	 * @param list<array{0: CatalogEntry, 1: array<string, mixed>}> $google
	 * @param list<string>                                          $active_keys
	 */
	private function add_google( PurposeRegistry $purposes, TagRegistry $tags, AdapterRegistry $adapters, array $google, array $active_keys ): void {
		$products = array();
		foreach ( $google as list( $entry, $tag ) ) {
			$id      = $this->str( $tag, 'id' );
			$product = array(
				'measurementIds' => array(),
				'containerIds'   => array(),
			);
			if ( 'gtm' === $entry->key() ) {
				$container_id = $this->str( $this->fields( $tag ), 'containerId' );
				if ( '' !== $container_id ) {
					$product['containerIds'][] = $container_id;
				}
			} else {
				$product['measurementIds'] = $this->google_ids( $tag );
			}
			$products[ $id ] = $product;

			$tags->add(
				new Tag(
					$id,
					$this->tag_label( $entry, $tag ),
					$this->resolve_purposes( $purposes, $this->tag_purpose_keys( $entry, $tag, $active_keys ) ),
					Delivery::Direct,
					GoogleAdapter::ID,
				)
			);
		}

		$adapters->add( new GoogleAdapter( $products ) );
	}

	/**
	 * @param array<string, mixed> $tag
	 * @return list<string>
	 */
	private function google_ids( array $tag ): array {
		$fields = $this->fields( $tag );
		$ids    = array();
		foreach ( array( 'measurementId', 'conversionId' ) as $field ) {
			$value = $this->str( $fields, $field );
			if ( '' !== $value ) {
				$ids[] = $value;
			}
		}
		return $ids;
	}

	/**
	 * @param array<string, mixed> $tag
	 * @return array<string, mixed>
	 */
	private function adapter_config( CatalogEntry $entry, array $tag ): array {
		$fields = $this->fields( $tag );
		if ( 'meta-pixel' === $entry->key() ) {
			return array(
				'handler'   => 'script',
				'fragments' => array(
					array(
						'code'     => self::meta_pixel_code( $this->str( $fields, 'pixelId' ) ),
						'location' => 'head',
					),
				),
			);
		}

		return array(
			'handler'   => 'script',
			'fragments' => $this->custom_fragments( $fields ),
		);
	}

	/**
	 * @param array<string, mixed> $fields
	 * @return list<array{code: string, location: string}>
	 */
	private function custom_fragments( array $fields ): array {
		$raw = $fields['fragments'] ?? null;
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();
		foreach ( $raw as $fragment ) {
			$fragment = self::map( $fragment );
			$code     = $this->str( $fragment, 'code' );
			if ( '' === $code ) {
				continue;
			}
			$location = $this->str( $fragment, 'location' );
			$out[]    = array(
				'code'     => $code,
				'location' => '' !== $location ? $location : 'head',
			);
		}
		return $out;
	}

	private static function meta_pixel_code( string $pixel_id ): string {
		return '!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?'
			. 'n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;'
			. "n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;"
			. 't.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}'
			. "(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');"
			. "fbq('init','" . $pixel_id . "');fbq('track','PageView');";
	}

	private function jurisdiction_registry( int $policy_version, PurposeRegistry $purposes ): JurisdictionRegistry {
		$geo = $this->section( 'geo' );
		if ( (bool) ( $geo['adaptive'] ?? true ) ) {
			return JurisdictionRegistry::with_defaults( $policy_version );
		}

		$non_always_on = array_values(
			array_filter(
				$purposes->all(),
				static fn ( Purpose $purpose ): bool => ! $purpose->is_always_on(),
			)
		);

		$policy = match ( $this->str( $geo, 'globalPolicy' ) ) {
			'opt_out'     => Policy::opt_out( $policy_version, $non_always_on ),
			'notice_only' => Policy::notice_only( $policy_version, $non_always_on ),
			default       => Policy::opt_in( $policy_version ),
		};

		return new JurisdictionRegistry(
			new Jurisdiction( '*', __( 'All visitors', 'consentful' ), $policy )
		);
	}

	private function geo_config(): GeoConfig {
		return (bool) ( $this->section( 'geo' )['adaptive'] ?? true )
			? GeoConfig::defaults()
			: new GeoConfig( '', '', false, '', array() );
	}

	private function banner_config( string $privacy_fallback_url ): BannerConfig {
		$banner = BannerConfig::defaults();
		$banner = $banner->with_overrides( $this->section( 'banner' ) );
		$banner = $banner->with_purpose_overrides( $this->purpose_copy() );

		return $banner->with_privacy_fallback( $privacy_fallback_url );
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function purpose_copy(): array {
		$out = array();
		foreach ( $this->section( 'purposes' ) as $key => $copy ) {
			if ( is_array( $copy ) ) {
				$out[ $key ] = self::map( $copy );
			}
		}
		return $out;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function stored_tags(): array {
		$out = array();
		foreach ( $this->section( 'tags' ) as $tag ) {
			if ( is_array( $tag ) ) {
				$out[] = self::map( $tag );
			}
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $tag
	 * @param list<string>         $active_keys
	 * @return list<string>
	 */
	private function tag_purpose_keys( CatalogEntry $entry, array $tag, array $active_keys ): array {
		$stored = $tag['purposes'] ?? null;
		$keys   = is_array( $stored ) && array() !== $stored
			? array_values( array_filter( $stored, 'is_string' ) )
			: $entry->default_purposes();

		return array_values(
			array_filter( $keys, static fn ( string $key ): bool => in_array( $key, $active_keys, true ) )
		);
	}

	/**
	 * @param list<string> $keys
	 * @return list<Purpose>
	 */
	private function resolve_purposes( PurposeRegistry $purposes, array $keys ): array {
		$by_key = array();
		foreach ( $purposes->all() as $purpose ) {
			$by_key[ $purpose->key() ] = $purpose;
		}

		$out = array();
		foreach ( $keys as $key ) {
			if ( isset( $by_key[ $key ] ) ) {
				$out[] = $by_key[ $key ];
			}
		}
		return $out;
	}

	/**
	 * @return list<string>
	 */
	private function purpose_keys( PurposeRegistry $purposes ): array {
		return array_map( static fn ( Purpose $purpose ): string => $purpose->key(), $purposes->all() );
	}

	/**
	 * @param array<string, mixed> $tag
	 */
	private function tag_label( CatalogEntry $entry, array $tag ): string {
		$label = $this->str( $tag, 'label' );
		return '' !== $label ? $label : $entry->label();
	}

	/**
	 * @param array<string, mixed> $tag
	 * @return array<string, mixed>
	 */
	private function fields( array $tag ): array {
		return self::map( $tag['fields'] ?? null );
	}

	/**
	 * @param array<string, mixed> $map
	 */
	private function str( array $map, string $key ): string {
		$value = $map[ $key ] ?? '';
		return is_scalar( $value ) ? (string) $value : '';
	}
}

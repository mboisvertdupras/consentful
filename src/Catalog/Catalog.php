<?php
declare( strict_types = 1 );

namespace Consentful\Catalog;

use Consentful\Tag\Delivery;

/**
 * The built-in registry of integrations the Administrator picks from in the admin UI.
 * Pure (gettext only for labels). `with_defaults()` seeds the v1 catalog; the hydrator
 * resolves a stored tag entry's catalog key to an entry to build the client config.
 */
final class Catalog {

	/** @var array<string, CatalogEntry> Keyed by catalog key, in display order. */
	private array $entries = array();

	/**
	 * @param iterable<CatalogEntry> $entries
	 */
	public function __construct( iterable $entries ) {
		foreach ( $entries as $entry ) {
			$this->entries[ $entry->key() ] = $entry;
		}
	}

	/** Seed the shipped v1 catalog. */
	public static function with_defaults(): self {
		return new self(
			array(
				new CatalogEntry(
					'ga4',
					__( 'Google Analytics 4', 'consentful' ),
					'google',
					Delivery::Direct,
					array( 'analytics' ),
					array(
						'measurementId' => array(
							'label'       => __( 'Measurement ID', 'consentful' ),
							'placeholder' => 'G-XXXXXXXXXX',
							'type'        => 'text',
						),
					),
				),
				new CatalogEntry(
					'google-ads',
					__( 'Google Ads', 'consentful' ),
					'google',
					Delivery::Direct,
					array( 'marketing' ),
					array(
						'conversionId' => array(
							'label'       => __( 'Conversion ID', 'consentful' ),
							'placeholder' => 'AW-XXXXXXXXX',
							'type'        => 'text',
						),
					),
				),
				new CatalogEntry(
					'gtm',
					__( 'Google Tag Manager', 'consentful' ),
					'gtm',
					Delivery::Delegated,
					array( 'analytics', 'marketing' ),
					array(),
				),
				new CatalogEntry(
					'meta-pixel',
					__( 'Meta Pixel', 'consentful' ),
					'script',
					Delivery::Direct,
					array( 'marketing' ),
					array(
						'pixelId' => array(
							'label'       => __( 'Pixel ID', 'consentful' ),
							'placeholder' => '000000000000000',
							'type'        => 'text',
						),
					),
				),
				new CatalogEntry(
					'custom',
					__( 'Custom HTML / snippet', 'consentful' ),
					'script',
					Delivery::Direct,
					array(),
					array(
						'code'       => array(
							'label'       => __( 'Snippet', 'consentful' ),
							'placeholder' => '<script>…</script>',
							'type'        => 'textarea',
						),
						'src'        => array(
							'label'       => __( 'Script URL', 'consentful' ),
							'placeholder' => 'https://example.com/tag.js',
							'type'        => 'url',
						),
						'attributes' => array(
							'label'       => __( 'Attributes', 'consentful' ),
							'placeholder' => '',
							'type'        => 'text',
						),
					),
				),
			)
		);
	}

	public function get( string $key ): ?CatalogEntry {
		return $this->entries[ $key ] ?? null;
	}

	/**
	 * @return list<CatalogEntry>
	 */
	public function entries(): array {
		return array_values( $this->entries );
	}
}

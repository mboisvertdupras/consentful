<?php
declare( strict_types = 1 );

namespace Consentful\Catalog;

use Consentful\Tag\Delivery;

final class Catalog {

	/** @var array<string, CatalogEntry> */
	private array $entries = array();

	/** @param iterable<CatalogEntry> $entries */
	public function __construct( iterable $entries ) {
		foreach ( $entries as $entry ) {
			$this->entries[ $entry->key() ] = $entry;
		}
	}

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
					'google',
					Delivery::Direct,
					array( 'analytics', 'marketing' ),
					array(
						'containerId' => array(
							'label'       => __( 'Container ID', 'consentful' ),
							'placeholder' => 'GTM-XXXXXXX',
							'type'        => 'text',
						),
					),
				),
				new CatalogEntry(
					'meta-pixel',
					__( 'Meta Pixel', 'consentful' ),
					'meta',
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
						'code'     => array(
							'label'       => __( 'Snippet', 'consentful' ),
							'placeholder' => '<script>…</script>',
							'type'        => 'textarea',
						),
						'location' => array(
							'label'       => __( 'Location', 'consentful' ),
							'placeholder' => '',
							'type'        => 'select',
						),
					),
				),
			)
		);
	}

	public function get( string $key ): ?CatalogEntry {
		return $this->entries[ $key ] ?? null;
	}

	/** @return list<CatalogEntry> */
	public function entries(): array {
		return array_values( $this->entries );
	}
}

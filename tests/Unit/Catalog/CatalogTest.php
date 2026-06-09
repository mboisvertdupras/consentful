<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Catalog;

use Consentful\Catalog\Catalog;
use Consentful\Tag\Delivery;
use PHPUnit\Framework\TestCase;

final class CatalogTest extends TestCase {

	public function test_default_entries_are_present_in_order(): void {
		$keys = array_map(
			static fn ( $entry ): string => $entry->key(),
			Catalog::with_defaults()->entries()
		);

		$this->assertSame( array( 'ga4', 'google-ads', 'gtm', 'meta-pixel', 'custom' ), $keys );
	}

	public function test_get_returns_null_for_unknown_key(): void {
		$this->assertNull( Catalog::with_defaults()->get( 'nope' ) );
	}

	public function test_ga4_entry_shape(): void {
		$entry = Catalog::with_defaults()->get( 'ga4' );

		$this->assertNotNull( $entry );
		$this->assertSame( 'Google Analytics 4', $entry->label() );
		$this->assertSame( 'google', $entry->handler() );
		$this->assertSame( Delivery::Direct, $entry->delivery() );
		$this->assertSame( array( 'analytics' ), $entry->default_purposes() );
		$this->assertSame( array( 'measurementId' ), array_keys( $entry->fields() ) );
		$this->assertSame( 'text', $entry->fields()['measurementId']['type'] );
	}

	public function test_google_ads_entry_shape(): void {
		$entry = Catalog::with_defaults()->get( 'google-ads' );

		$this->assertNotNull( $entry );
		$this->assertSame( 'google', $entry->handler() );
		$this->assertSame( Delivery::Direct, $entry->delivery() );
		$this->assertSame( array( 'marketing' ), $entry->default_purposes() );
		$this->assertSame( array( 'conversionId' ), array_keys( $entry->fields() ) );
	}

	public function test_gtm_loads_a_container_via_the_google_handler(): void {
		$entry = Catalog::with_defaults()->get( 'gtm' );

		$this->assertNotNull( $entry );
		$this->assertSame( 'google', $entry->handler() );
		$this->assertSame( Delivery::Direct, $entry->delivery() );
		$this->assertSame( array( 'analytics', 'marketing' ), $entry->default_purposes() );
		$this->assertSame( array( 'containerId' ), array_keys( $entry->fields() ) );
		$this->assertSame( 'text', $entry->fields()['containerId']['type'] );
	}

	public function test_meta_pixel_is_a_script_handler_with_pixel_id(): void {
		$entry = Catalog::with_defaults()->get( 'meta-pixel' );

		$this->assertNotNull( $entry );
		$this->assertSame( 'script', $entry->handler() );
		$this->assertSame( Delivery::Direct, $entry->delivery() );
		$this->assertSame( array( 'marketing' ), $entry->default_purposes() );
		$this->assertSame( array( 'pixelId' ), array_keys( $entry->fields() ) );
	}

	public function test_custom_is_a_script_handler_with_code_and_location(): void {
		$entry = Catalog::with_defaults()->get( 'custom' );

		$this->assertNotNull( $entry );
		$this->assertSame( 'script', $entry->handler() );
		$this->assertSame( Delivery::Direct, $entry->delivery() );
		$this->assertSame( array(), $entry->default_purposes() );
		$this->assertSame( array( 'code', 'location' ), array_keys( $entry->fields() ) );
		$this->assertSame( 'textarea', $entry->fields()['code']['type'] );
		$this->assertSame( 'select', $entry->fields()['location']['type'] );
	}
}

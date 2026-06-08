<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Catalog;

use Consentful\Catalog\Catalog;
use Consentful\Tag\Delivery;
use PHPUnit\Framework\TestCase;

/**
 * The built-in Catalog: the v1 entries, their handler/delivery/default-purposes, and the
 * field schema the admin UI renders. Pure (gettext labels round-trip as the source string
 * under the test shim).
 */
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

	public function test_gtm_is_delegated_with_no_fields(): void {
		$entry = Catalog::with_defaults()->get( 'gtm' );

		$this->assertNotNull( $entry );
		$this->assertSame( 'gtm', $entry->handler() );
		$this->assertSame( Delivery::Delegated, $entry->delivery() );
		$this->assertSame( array( 'analytics', 'marketing' ), $entry->default_purposes() );
		$this->assertSame( array(), $entry->fields() );
	}

	public function test_meta_pixel_is_a_script_handler_with_pixel_id(): void {
		$entry = Catalog::with_defaults()->get( 'meta-pixel' );

		$this->assertNotNull( $entry );
		$this->assertSame( 'script', $entry->handler() );
		$this->assertSame( Delivery::Direct, $entry->delivery() );
		$this->assertSame( array( 'marketing' ), $entry->default_purposes() );
		$this->assertSame( array( 'pixelId' ), array_keys( $entry->fields() ) );
	}

	public function test_custom_is_a_script_handler_with_code_src_attributes(): void {
		$entry = Catalog::with_defaults()->get( 'custom' );

		$this->assertNotNull( $entry );
		$this->assertSame( 'script', $entry->handler() );
		$this->assertSame( Delivery::Direct, $entry->delivery() );
		$this->assertSame( array(), $entry->default_purposes() );
		$this->assertSame( array( 'code', 'src', 'attributes' ), array_keys( $entry->fields() ) );
		$this->assertSame( 'textarea', $entry->fields()['code']['type'] );
		$this->assertSame( 'url', $entry->fields()['src']['type'] );
	}
}

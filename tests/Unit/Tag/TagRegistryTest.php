<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Tag;

use Consentful\Consent\DefaultPurpose;
use Consentful\Tag\Delivery;
use Consentful\Tag\Tag;
use Consentful\Tag\TagRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Covers TagRegistry: add/get/has/all (insertion order) and for_adapter.
 */
final class TagRegistryTest extends TestCase {

	private function tag( string $id, string $adapter_id ): Tag {
		return new Tag( $id, ucfirst( $id ), array( DefaultPurpose::Analytics ), Delivery::Direct, $adapter_id );
	}

	public function test_add_then_get_returns_the_same_tag(): void {
		$registry = new TagRegistry();
		$tag      = $this->tag( 'ga4', 'google' );
		$registry->add( $tag );

		$this->assertSame( $tag, $registry->get( 'ga4' ) );
	}

	public function test_get_returns_null_for_an_unknown_id(): void {
		$registry = new TagRegistry();

		$this->assertNull( $registry->get( 'missing' ) );
	}

	public function test_has_reports_known_and_unknown_ids(): void {
		$registry = new TagRegistry();
		$registry->add( $this->tag( 'ga4', 'google' ) );

		$this->assertTrue( $registry->has( 'ga4' ) );
		$this->assertFalse( $registry->has( 'missing' ) );
	}

	public function test_add_is_keyed_by_id_and_dedupes(): void {
		$registry = new TagRegistry();
		$first    = $this->tag( 'ga4', 'google' );
		$second   = $this->tag( 'ga4', 'gtm' );

		$registry->add( $first );
		$registry->add( $second );

		$this->assertCount( 1, $registry->all() );
		$this->assertSame( $second, $registry->get( 'ga4' ) );
	}

	public function test_all_preserves_insertion_order(): void {
		$registry = new TagRegistry();
		$ga4      = $this->tag( 'ga4', 'google' );
		$pixel    = $this->tag( 'pixel', 'meta' );
		$registry->add( $ga4 );
		$registry->add( $pixel );

		$this->assertSame( array( $ga4, $pixel ), $registry->all() );
	}

	public function test_for_adapter_returns_only_matching_tags(): void {
		$registry = new TagRegistry();
		$ga4      = $this->tag( 'ga4', 'google' );
		$ads      = $this->tag( 'ads', 'google' );
		$pixel    = $this->tag( 'pixel', 'meta' );
		$registry->add( $ga4 );
		$registry->add( $pixel );
		$registry->add( $ads );

		$this->assertSame( array( $ga4, $ads ), $registry->for_adapter( 'google' ) );
		$this->assertSame( array( $pixel ), $registry->for_adapter( 'meta' ) );
		$this->assertSame( array(), $registry->for_adapter( 'unknown' ) );
	}
}

<?php
declare( strict_types = 1 );

namespace Consentful\Tag;

use Consentful\Consent\Consent;
use Consentful\Consent\Purpose;

/**
 * A concrete gated thing (GA4, a pixel, a snippet). Fires only when every one of
 * its Purposes is granted. Immutable; the registry keys it by id.
 */
final class Tag {

	/**
	 * `$site_toggleable` opts a Tag into the constrained Site-owner admin UI: only
	 * toggleable Tags appear in the admin Tag list and may be hidden by the Site owner.
	 *
	 * @param list<Purpose> $purposes
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $label,
		public readonly array $purposes,
		public readonly Delivery $delivery,
		public readonly string $adapter_id,
		public readonly bool $site_toggleable = false
	) {}

	/**
	 * Fail-closed: no purposes means never granted; otherwise every Purpose must
	 * be granted for the Tag to fire.
	 */
	public function is_granted( Consent $consent ): bool {
		if ( array() === $this->purposes ) {
			return false;
		}
		foreach ( $this->purposes as $purpose ) {
			if ( ! $consent->granted( $purpose ) ) {
				return false;
			}
		}
		return true;
	}
}

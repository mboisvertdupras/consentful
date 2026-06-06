<?php
declare( strict_types = 1 );

namespace Consentful\Container;

use RuntimeException;

/**
 * Thrown when the container is asked to resolve an id it knows nothing about.
 */
final class NotFoundException extends RuntimeException {

	public static function for_id( string $id ): self {
		return new self( sprintf( 'No binding registered for "%s".', $id ) );
	}
}

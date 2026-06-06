<?php
declare( strict_types = 1 );

namespace Consentful\Tag;

/**
 * How a Tag reaches the page: Direct (a Consentful adapter injects it) or
 * Delegated (an external tag manager fires it, gated via a consent push).
 */
enum Delivery {

	case Direct;
	case Delegated;
}

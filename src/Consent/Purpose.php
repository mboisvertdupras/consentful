<?php
declare( strict_types = 1 );

namespace Consentful\Consent;

/**
 * A category of data use a Visitor consents to — the legal unit of consent. The
 * default set is the DefaultPurpose enum; integrators add their own by
 * implementing this interface (CustomPurpose), which is why Purpose is a
 * contract rather than a fixed enum. `key()` is the stable identity used in the
 * consent cookie and records.
 */
interface Purpose {

	public function key(): string;

	/** Whether this Purpose is always granted and cannot be refused (Necessary). */
	public function is_always_on(): bool;
}

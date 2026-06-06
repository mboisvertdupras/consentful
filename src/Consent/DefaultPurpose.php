<?php
declare( strict_types = 1 );

namespace Consentful\Consent;

/**
 * The default Purpose taxonomy. Necessary is always on; the rest are gateable.
 * The backing value is the stable key stored in the cookie and records.
 */
enum DefaultPurpose: string implements Purpose {

	case Necessary       = 'necessary';
	case Functional      = 'functional';
	case Analytics       = 'analytics';
	case Marketing       = 'marketing';
	case Personalization = 'personalization';

	public function key(): string {
		return $this->value;
	}

	public function is_always_on(): bool {
		return self::Necessary === $this;
	}

	/**
	 * The default-set Purposes, in display order.
	 *
	 * @return list<self>
	 */
	public static function defaults(): array {
		return self::cases();
	}
}

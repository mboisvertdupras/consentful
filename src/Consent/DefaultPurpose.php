<?php
declare( strict_types = 1 );

namespace Consentful\Consent;

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

	/** @return list<self> */
	public static function defaults(): array {
		return array(
			self::Necessary,
			self::Functional,
			self::Analytics,
			self::Marketing,
		);
	}
}

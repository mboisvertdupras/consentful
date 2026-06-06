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
	 * The shipped default-set Purposes, in display order. Personalization is the OPTIONAL
	 * member (ADR 0002): it exists as a case with a key/copy, but is NOT shipped by default
	 * — an Integrator opts in by adding `DefaultPurpose::Personalization` to the
	 * PurposeRegistry. Keeping the default set to the four universal categories avoids a
	 * banner toggle / Consent Mode signal most sites never use.
	 *
	 * @return list<self>
	 */
	public static function defaults(): array {
		return array(
			self::Necessary,
			self::Functional,
			self::Analytics,
			self::Marketing,
		);
	}
}

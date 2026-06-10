<?php
declare( strict_types = 1 );

namespace Consentful\Jurisdiction;

use Consentful\Consent\Purpose;

final class Policy {

	/**
	 * @param list<Purpose> $default_granted
	 */
	public function __construct(
		public readonly PolicyType $type,
		public readonly int $version,
		public readonly array $default_granted = array(),
	) {}

	public function denies_by_default(): bool {
		return PolicyType::OptIn === $this->type;
	}

	public function blocks_before_consent(): bool {
		return PolicyType::OptIn === $this->type;
	}

	public function shows_banner(): bool {
		return PolicyType::NoticeOnly !== $this->type;
	}

	public function grants_by_default( Purpose $purpose ): bool {
		if ( $purpose->is_always_on() ) {
			return true;
		}
		foreach ( $this->default_granted as $granted ) {
			if ( $granted->key() === $purpose->key() ) {
				return true;
			}
		}
		return false;
	}

	public static function opt_in( int $version ): self {
		return new self( PolicyType::OptIn, $version, array() );
	}

	/**
	 * @param list<Purpose> $default_granted
	 */
	public static function opt_out( int $version, array $default_granted ): self {
		return new self( PolicyType::OptOut, $version, $default_granted );
	}

	/**
	 * @param list<Purpose> $default_granted
	 */
	public static function notice_only( int $version, array $default_granted ): self {
		return new self( PolicyType::NoticeOnly, $version, $default_granted );
	}
}

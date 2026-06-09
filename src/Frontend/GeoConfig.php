<?php
declare( strict_types = 1 );

namespace Consentful\Frontend;

final class GeoConfig {

	/** @var array<string, string> */
	private const DEFAULT_MAP = array(
		'AT'    => 'EU',
		'BE'    => 'EU',
		'BG'    => 'EU',
		'HR'    => 'EU',
		'CY'    => 'EU',
		'CZ'    => 'EU',
		'DK'    => 'EU',
		'EE'    => 'EU',
		'FI'    => 'EU',
		'FR'    => 'EU',
		'DE'    => 'EU',
		'GR'    => 'EU',
		'HU'    => 'EU',
		'IE'    => 'EU',
		'IT'    => 'EU',
		'LV'    => 'EU',
		'LT'    => 'EU',
		'LU'    => 'EU',
		'MT'    => 'EU',
		'NL'    => 'EU',
		'PL'    => 'EU',
		'PT'    => 'EU',
		'RO'    => 'EU',
		'SK'    => 'EU',
		'SI'    => 'EU',
		'ES'    => 'EU',
		'SE'    => 'EU',
		'IS'    => 'EU',
		'LI'    => 'EU',
		'NO'    => 'EU',
		'GB'    => 'UK',
		'US'    => 'US',
		'CA-QC' => 'QC',
	);

	/**
	 * @param array<string, string> $map
	 */
	public function __construct(
		public readonly string $cookie_name,
		public readonly string $var_name,
		public readonly bool $use_builtin_endpoint,
		public readonly string $endpoint,
		public readonly array $map,
	) {}

	/** @return array{cookie: string, var: string, endpoint: string, map: array<string, string>} */
	public function to_array( string $resolved_endpoint_url ): array {
		$endpoint = '' !== $this->endpoint
			? $this->endpoint
			: ( $this->use_builtin_endpoint ? $resolved_endpoint_url : '' );

		return array(
			'cookie'   => $this->cookie_name,
			'var'      => $this->var_name,
			'endpoint' => $endpoint,
			'map'      => $this->map,
		);
	}

	public static function defaults(): self {
		return new self( '', '', true, '', self::DEFAULT_MAP );
	}
}

<?php
declare( strict_types = 1 );

namespace Consentful\Frontend;

/**
 * The geo-resolution config the client gate consumes: how to read a per-Visitor
 * region (edge cookie, window var, or the non-cached REST endpoint) and the
 * region-code → jurisdiction-id map. A pure value object — no consent logic and no
 * WordPress. Resolution stays 100% client/edge-side so HTML is identical for every
 * Visitor (cache-safe); unmapped regions fall through to the strictest '*' fallback.
 */
final class GeoConfig {

	/**
	 * Region-code → jurisdiction-id. EU-27 + EEA-3 → EU, GB → UK, US → US, CA-QC → QC.
	 * Region codes are upper-case CC (ISO-3166-1 alpha-2) or CC-RR (alpha-2 + subdivision).
	 *
	 * @var array<string, string>
	 */
	private const DEFAULT_MAP = array(
		// EU-27.
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
		// EEA-3.
		'IS'    => 'EU',
		'LI'    => 'EU',
		'NO'    => 'EU',
		// United Kingdom.
		'GB'    => 'UK',
		// United States.
		'US'    => 'US',
		// Québec.
		'CA-QC' => 'QC',
	);

	/**
	 * @param array<string, string> $map Region-code → jurisdiction-id.
	 */
	public function __construct(
		public readonly string $cookie_name,
		public readonly string $var_name,
		public readonly bool $use_builtin_endpoint,
		public readonly string $endpoint,
		public readonly array $map,
	) {}

	/**
	 * The frozen `geo` block. The endpoint resolves to the explicit integrator URL when
	 * set; else the built-in REST URL when the built-in endpoint is enabled; else ''.
	 *
	 * @return array{cookie: string, var: string, endpoint: string, map: array<string, string>}
	 */
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

	/**
	 * Degrade-to-today defaults: no edge cookie or window var, the built-in endpoint
	 * wired, and the default region map. With no signal every Visitor resolves to '*'.
	 */
	public static function defaults(): self {
		return new self( '', '', true, '', self::DEFAULT_MAP );
	}
}

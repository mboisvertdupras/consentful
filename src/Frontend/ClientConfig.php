<?php
declare( strict_types = 1 );

namespace Consentful\Frontend;

use Consentful\Adapter\AdapterRegistry;
use Consentful\Consent\ProofConfig;
use Consentful\Consent\Purpose;
use Consentful\Consent\PurposeRegistry;
use Consentful\Jurisdiction\JurisdictionRegistry;
use Consentful\Jurisdiction\Policy;
use Consentful\Jurisdiction\PolicyType;
use Consentful\Tag\Tag;
use Consentful\Tag\TagRegistry;

final class ClientConfig {

	public function __construct(
		private readonly PurposeRegistry $purposes,
		private readonly TagRegistry $tags,
		private readonly AdapterRegistry $adapters,
		private readonly JurisdictionRegistry $jurisdictions,
		private readonly BannerConfig $banner,
		private readonly GeoConfig $geo,
		private readonly string $geo_endpoint_url = '',
		private readonly ProofConfig $proof = new ProofConfig( true ),
		private readonly string $proof_endpoint_url = '',
		private readonly int $schema_version = 1,
		private readonly int $policy_version = 1,
		private readonly int $max_age_days = 180,
		private readonly string $cookie = 'consentful',
	) {}

	/** @return array<string, mixed> */
	public function to_array(): array {
		return array(
			'cookie'              => $this->cookie,
			'schemaVersion'       => $this->schema_version,
			'policyVersion'       => $this->policy_version,
			'maxAgeDays'          => $this->max_age_days,
			'defaultJurisdiction' => $this->jurisdictions->fallback()->id,
			'purposes'            => $this->purposes_array(),
			'jurisdictions'       => $this->jurisdictions_array(),
			'geo'                 => $this->geo->to_array( $this->geo_endpoint_url ),
			'proof'               => $this->proof->to_array( $this->proof_endpoint_url, $this->banner->version ),
			'tags'                => $this->tags_array(),
			'adapters'            => $this->adapters_array(),
			'banner'              => $this->banner->to_array(),
		);
	}

	/** @return array<string, array{id: string, policy: array<string, mixed>}> */
	private function jurisdictions_array(): array {
		$out = array();
		foreach ( $this->jurisdictions->all() as $jurisdiction ) {
			$out[ $jurisdiction->id ] = array(
				'id'     => $jurisdiction->id,
				'policy' => $this->policy_array( $jurisdiction->policy ),
			);
		}
		return $out;
	}

	/**
	 * @return list<array{key: string, alwaysOn: bool}>
	 */
	private function purposes_array(): array {
		$out = array();
		foreach ( $this->purposes->all() as $purpose ) {
			$out[] = array(
				'key'      => $purpose->key(),
				'alwaysOn' => $purpose->is_always_on(),
			);
		}
		return $out;
	}

	/**
	 * @return array{type: string, showsBanner: bool, defaultGranted: list<string>}
	 */
	private function policy_array( Policy $policy ): array {
		return array(
			'type'           => $this->policy_type( $policy->type ),
			'showsBanner'    => $policy->shows_banner(),
			'defaultGranted' => $this->default_granted( $policy ),
		);
	}

	/** @return list<string> */
	private function default_granted( Policy $policy ): array {
		$out = array();
		foreach ( $this->purposes->all() as $purpose ) {
			if ( $purpose->is_always_on() ) {
				continue;
			}
			if ( $policy->grants_by_default( $purpose ) ) {
				$out[] = $purpose->key();
			}
		}
		return $out;
	}

	/**
	 * @return list<array{id: string, purposes: list<string>, adapter: string}>
	 */
	private function tags_array(): array {
		$out = array();
		foreach ( $this->tags->all() as $tag ) {
			$out[] = array(
				'id'       => $tag->id,
				'purposes' => $this->purpose_keys( $tag ),
				'adapter'  => $tag->adapter_id,
			);
		}
		return $out;
	}

	/**
	 * @return list<string>
	 */
	private function purpose_keys( Tag $tag ): array {
		return array_map(
			static fn ( Purpose $purpose ): string => $purpose->key(),
			$tag->purposes
		);
	}

	/** @return array<string, array<string, mixed>> */
	private function adapters_array(): array {
		$out = array();
		foreach ( $this->adapters->all() as $adapter ) {
			$out[ $adapter->id() ] = $adapter->client_config();
		}
		return $out;
	}

	private function policy_type( PolicyType $type ): string {
		return match ( $type ) {
			PolicyType::OptIn      => 'opt_in',
			PolicyType::OptOut     => 'opt_out',
			PolicyType::NoticeOnly => 'notice_only',
		};
	}
}

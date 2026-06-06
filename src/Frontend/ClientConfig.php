<?php
declare( strict_types = 1 );

namespace Consentful\Frontend;

use Consentful\Adapter\AdapterRegistry;
use Consentful\Consent\Purpose;
use Consentful\Consent\PurposeRegistry;
use Consentful\Jurisdiction\Jurisdiction;
use Consentful\Jurisdiction\Policy;
use Consentful\Jurisdiction\PolicyType;
use Consentful\Tag\Delivery;
use Consentful\Tag\Tag;
use Consentful\Tag\TagRegistry;

/**
 * Serializes the registries, resolved Jurisdiction and BannerConfig into the camelCase
 * config the client gate consumes from `window.consentfulConfig`. Pure: no WordPress
 * functions (it serializes the BannerConfig it is handed, never calls gettext itself),
 * so it runs under PHPUnit without WordPress. The same value feeds the cache-safe,
 * identical-for-every-visitor head output.
 */
final class ClientConfig {

	public function __construct(
		private readonly PurposeRegistry $purposes,
		private readonly TagRegistry $tags,
		private readonly AdapterRegistry $adapters,
		private readonly Jurisdiction $resolved,
		private readonly BannerConfig $banner,
		private readonly int $schema_version,
		private readonly int $policy_version,
		private readonly int $max_age_days = 180,
		private readonly string $cookie = 'consentful',
	) {}

	/**
	 * @return array<string, mixed> The frozen config shape (camelCase keys).
	 */
	public function to_array(): array {
		return array(
			'cookie'        => $this->cookie,
			'schemaVersion' => $this->schema_version,
			'policyVersion' => $this->policy_version,
			'maxAgeDays'    => $this->max_age_days,
			'jurisdiction'  => $this->resolved->id,
			'purposes'      => $this->purposes_array(),
			'policy'        => $this->policy_array( $this->resolved->policy ),
			'tags'          => $this->tags_array(),
			'adapters'      => $this->adapters_array(),
			'banner'        => $this->banner->to_array(),
		);
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
	 * @return array{type: string, version: int, denyByDefault: bool, blocksBeforeConsent: bool, showsBanner: bool, defaultGranted: list<string>}
	 */
	private function policy_array( Policy $policy ): array {
		return array(
			'type'                => $this->policy_type( $policy->type ),
			'version'             => $policy->version,
			'denyByDefault'       => $policy->denies_by_default(),
			'blocksBeforeConsent' => $policy->blocks_before_consent(),
			'showsBanner'         => $policy->shows_banner(),
			'defaultGranted'      => $this->default_granted( $policy ),
		);
	}

	/**
	 * Default-granted keys are the non-always-on Purposes the Policy grants before
	 * the Visitor acts (always-on Purposes are implicit, never listed here).
	 *
	 * @return list<string>
	 */
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
	 * @return list<array{id: string, purposes: list<string>, delivery: string, adapter: string}>
	 */
	private function tags_array(): array {
		$out = array();
		foreach ( $this->tags->all() as $tag ) {
			$out[] = array(
				'id'       => $tag->id,
				'purposes' => $this->purpose_keys( $tag ),
				'delivery' => $this->delivery( $tag->delivery ),
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

	/**
	 * Adapter client config verbatim — ClientConfig never reshapes it; each adapter
	 * emits its own camelCase shape.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function adapters_array(): array {
		$out = array();
		foreach ( $this->adapters->all() as $adapter ) {
			$out[ $adapter->id() ] = $adapter->client_config();
		}
		return $out;
	}

	/** Lowercase backing string — kept out of the pure PolicyType enum. */
	private function policy_type( PolicyType $type ): string {
		return match ( $type ) {
			PolicyType::OptIn      => 'opt_in',
			PolicyType::OptOut     => 'opt_out',
			PolicyType::NoticeOnly => 'notice_only',
		};
	}

	/** Lowercase backing string for the Tag delivery. */
	private function delivery( Delivery $delivery ): string {
		return match ( $delivery ) {
			Delivery::Direct    => 'direct',
			Delivery::Delegated => 'delegated',
		};
	}
}

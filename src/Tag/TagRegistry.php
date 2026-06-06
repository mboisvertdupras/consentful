<?php
declare( strict_types = 1 );

namespace Consentful\Tag;

/**
 * The active Tag set, keyed by id and preserving insertion order.
 */
final class TagRegistry {

	/** @var array<string, Tag> */
	private array $tags = array();

	public function add( Tag $tag ): void {
		$this->tags[ $tag->id ] = $tag;
	}

	public function has( string $id ): bool {
		return isset( $this->tags[ $id ] );
	}

	public function get( string $id ): ?Tag {
		return $this->tags[ $id ] ?? null;
	}

	/**
	 * @return list<Tag>
	 */
	public function all(): array {
		return array_values( $this->tags );
	}

	/**
	 * @return list<Tag>
	 */
	public function for_adapter( string $adapter_id ): array {
		$matches = array();
		foreach ( $this->tags as $tag ) {
			if ( $adapter_id === $tag->adapter_id ) {
				$matches[] = $tag;
			}
		}
		return $matches;
	}
}

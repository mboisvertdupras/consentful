<?php
declare( strict_types = 1 );

namespace Consentful\Catalog;

use Consentful\Tag\Delivery;

final class CatalogEntry {

	/**
	 * @param list<string>                                                         $default_purposes
	 * @param array<string, array{label: string, placeholder: string, type: string}> $fields
	 */
	public function __construct(
		private readonly string $key,
		private readonly string $label,
		private readonly string $handler,
		private readonly Delivery $delivery,
		private readonly array $default_purposes,
		private readonly array $fields,
	) {}

	public function key(): string {
		return $this->key;
	}

	public function label(): string {
		return $this->label;
	}

	public function handler(): string {
		return $this->handler;
	}

	public function delivery(): Delivery {
		return $this->delivery;
	}

	/** @return list<string> */
	public function default_purposes(): array {
		return $this->default_purposes;
	}

	/** @return array<string, array{label: string, placeholder: string, type: string}> */
	public function fields(): array {
		return $this->fields;
	}
}

<?php
declare( strict_types = 1 );

namespace Consentful\Catalog;

use Consentful\Tag\Delivery;

/**
 * A built-in integration the Administrator picks from in the admin UI. A pure value
 * object declaring how an integration is presented (label, field schema) and shaped
 * (handler, delivery, default purposes). The hydrator reads these to turn a stored tag
 * entry's field values into a client-config adapter + Tag; the per-handler specifics
 * (the Google merge, the Meta-pixel snippet template) live in the hydrator.
 */
final class CatalogEntry {

	/**
	 * @param list<string>                                                         $default_purposes Suggested purpose keys.
	 * @param array<string, array{label: string, placeholder: string, type: string}> $fields           Field schema for the UI.
	 */
	public function __construct(
		private readonly string $key,
		private readonly string $label,
		private readonly string $handler,
		private readonly Delivery $delivery,
		private readonly array $default_purposes,
		private readonly array $fields,
	) {}

	/** Stable catalog key (`ga4`, `google-ads`, `gtm`, `meta-pixel`, `custom`). */
	public function key(): string {
		return $this->key;
	}

	/** Gettext display label. */
	public function label(): string {
		return $this->label;
	}

	/** JS handler: `google`, `gtm` or `script`. */
	public function handler(): string {
		return $this->handler;
	}

	public function delivery(): Delivery {
		return $this->delivery;
	}

	/**
	 * @return list<string>
	 */
	public function default_purposes(): array {
		return $this->default_purposes;
	}

	/**
	 * @return array<string, array{label: string, placeholder: string, type: string}>
	 */
	public function fields(): array {
		return $this->fields;
	}
}

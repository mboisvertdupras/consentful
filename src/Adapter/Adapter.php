<?php
declare( strict_types = 1 );

namespace Consentful\Adapter;

interface Adapter {

	public function id(): string;

	/** @return array<string, mixed> */
	public function client_config(): array;
}

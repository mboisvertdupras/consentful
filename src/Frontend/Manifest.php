<?php
declare( strict_types = 1 );

namespace Consentful\Frontend;

final class Manifest {

	/** @var array<array-key, mixed>|null */
	private ?array $entries = null;

	public function __construct(
		private readonly string $manifest_path,
	) {}

	public function path_for( string $entry ): ?string {
		$entries = $this->entries();

		$record = $entries[ $entry ] ?? null;
		if ( ! is_array( $record ) ) {
			return null;
		}

		$file = $record['file'] ?? null;
		return is_string( $file ) ? $file : null;
	}

	/** @return array<array-key, mixed> */
	private function entries(): array {
		if ( null !== $this->entries ) {
			return $this->entries;
		}

		$this->entries = array();

		if ( ! is_readable( $this->manifest_path ) ) {
			return $this->entries;
		}

		$lines = file( $this->manifest_path, FILE_IGNORE_NEW_LINES );
		if ( false === $lines ) {
			return $this->entries;
		}

		$decoded = json_decode( implode( "\n", $lines ), true );
		if ( is_array( $decoded ) ) {
			$this->entries = $decoded;
		}

		return $this->entries;
	}
}

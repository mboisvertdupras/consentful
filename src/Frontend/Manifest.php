<?php
declare( strict_types = 1 );

namespace Consentful\Frontend;

/**
 * Resolves a hashed public sub-path from the Vite `build/.vite/manifest.json` (used
 * for the enqueued gate bundle). Fail-safe: a missing file, malformed JSON, or an
 * unknown entry yields null rather than throwing — the Gate then emits nothing.
 * WP-free so it unit-tests against a fixture manifest; joining the base dir/URL is
 * the Gate's concern.
 */
final class Manifest {

	/**
	 * Decoded manifest, memoized. Null means "not yet read"; an empty array means
	 * "read but unusable" (missing/bad), so the file is read at most once.
	 *
	 * @var array<array-key, mixed>|null
	 */
	private ?array $entries = null;

	public function __construct(
		private readonly string $manifest_path,
	) {}

	/**
	 * Hashed public sub-path for an entry, e.g. 'assets/gate.js' →
	 * 'assets/gate.<hash>.js'. Null when the manifest or entry is absent/malformed.
	 */
	public function path_for( string $entry ): ?string {
		$entries = $this->entries();

		$record = $entries[ $entry ] ?? null;
		if ( ! is_array( $record ) ) {
			return null;
		}

		$file = $record['file'] ?? null;
		return is_string( $file ) ? $file : null;
	}

	/**
	 * Read + decode the manifest once. Any failure memoizes an empty map.
	 *
	 * @return array<array-key, mixed>
	 */
	private function entries(): array {
		if ( null !== $this->entries ) {
			return $this->entries;
		}

		$this->entries = array();

		if ( ! is_readable( $this->manifest_path ) ) {
			return $this->entries;
		}

		// file() (not WP_Filesystem) keeps this class WordPress-free for unit tests.
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

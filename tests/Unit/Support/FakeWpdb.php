<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Support;

/**
 * A test double for `wpdb` that records insert/query calls instead of touching a
 * database. The DatabaseSink / ConsentLogSchema / Activator shells type their wpdb
 * dependency as `\wpdb`, so this extends it; instances are built via `create()`
 * (newInstanceWithoutConstructor) because the real constructor opens a connection.
 *
 * Method signatures mirror the parent's (no native param/return types) so the override
 * is contravariant-safe; recorded calls are exposed on dedicated public arrays.
 */
final class FakeWpdb extends \wpdb {

	/** @var list<array{table: string, data: array<string, mixed>, format: string[]|string|null}> */
	public array $recorded_inserts = array();

	/** @var list<string> */
	public array $recorded_queries = array();

	/** @var list<string> Queries passed to get_var / get_results (post-prepare). */
	public array $recorded_reads = array();

	/** @var int|string The value get_var returns. */
	public int|string $var_result = 0;

	/** @var list<array<string, mixed>> The rows get_results returns. */
	public array $results = array();

	/** Build without invoking the real (connecting) wpdb constructor. */
	public static function create( string $prefix = 'wp_' ): self {
		$db         = ( new \ReflectionClass( self::class ) )->newInstanceWithoutConstructor();
		$db->prefix = $prefix;
		return $db;
	}

	/**
	 * A sprintf-style stand-in for the real prepare: it binds %i (identifier), %d and %s
	 * placeholders so the test can assert the prepared table + LIMIT/OFFSET. Not real SQL
	 * escaping.
	 *
	 * @param string $query
	 * @param mixed  ...$args
	 * @return string
	 */
	public function prepare( $query, ...$args ) {
		$query = str_replace( array( '%i', '%d' ), '%s', $query );
		$values = array_map(
			static fn ( mixed $arg ): string => is_scalar( $arg ) ? (string) $arg : '',
			$args
		);
		return vsprintf( $query, $values );
	}

	/**
	 * @param string $query
	 * @return int|string The seeded scalar result.
	 */
	public function get_var( $query = null, $column_offset = 0, $row_offset = 0 ) {
		$this->recorded_reads[] = (string) $query;
		return $this->var_result;
	}

	/**
	 * @param string $query
	 * @param string $output
	 * @return list<array<string, mixed>> The seeded rows (ARRAY_A shape).
	 */
	public function get_results( $query = null, $output = OBJECT ) {
		$this->recorded_reads[] = (string) $query;
		return $this->results;
	}

	/**
	 * @param string               $table  Table name.
	 * @param array<string, mixed> $data   Column => value pairs.
	 * @param string[]|string      $format Formats mapped to each value.
	 * @return int The fake always "succeeds".
	 */
	public function insert( $table, $data, $format = null ) {
		$this->recorded_inserts[] = array(
			'table'  => $table,
			'data'   => $data,
			'format' => $format,
		);
		return 1;
	}

	/**
	 * @param string $query
	 * @return int The fake reports zero affected rows.
	 */
	public function query( $query ) {
		$this->recorded_queries[] = $query;
		return 0;
	}

	public function get_charset_collate() {
		return '';
	}
}

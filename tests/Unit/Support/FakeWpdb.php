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

	/** Build without invoking the real (connecting) wpdb constructor. */
	public static function create( string $prefix = 'wp_' ): self {
		$db         = ( new \ReflectionClass( self::class ) )->newInstanceWithoutConstructor();
		$db->prefix = $prefix;
		return $db;
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

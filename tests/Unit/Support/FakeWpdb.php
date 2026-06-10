<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Support;

final class FakeWpdb extends \wpdb {

	/** @var list<array{table: string, data: array<string, mixed>, format: string[]|string|null}> */
	public array $recorded_inserts = array();

	/** @var list<string> */
	public array $recorded_queries = array();

	/** @var list<string> */
	public array $recorded_reads = array();

	/** @var int|string */
	public int|string $var_result = 0;

	/** @var list<array<string, mixed>> */
	public array $results = array();

	public int $query_result = 0;

	public static function create( string $prefix = 'wp_' ): self {
		$db         = ( new \ReflectionClass( self::class ) )->newInstanceWithoutConstructor();
		$db->prefix = $prefix;
		return $db;
	}

	/** @return string */
	public function prepare( $query, ...$args ) {
		$query = str_replace( array( '%i', '%d' ), '%s', $query );
		$values = array_map(
			static fn ( mixed $arg ): string => is_scalar( $arg ) ? (string) $arg : '',
			$args
		);
		return vsprintf( $query, $values );
	}

	/** @return int|string */
	public function get_var( $query = null, $column_offset = 0, $row_offset = 0 ) {
		$this->recorded_reads[] = (string) $query;
		return $this->var_result;
	}

	/** @return list<array<string, mixed>> */
	public function get_results( $query = null, $output = OBJECT ) {
		$this->recorded_reads[] = (string) $query;
		return $this->results;
	}

	/**
	 * @param array<string, mixed> $data
	 * @param string[]|string      $format
	 * @return int
	 */
	public function insert( $table, $data, $format = null ) {
		$this->recorded_inserts[] = array(
			'table'  => $table,
			'data'   => $data,
			'format' => $format,
		);
		return 1;
	}

	/** @return int */
	public function query( $query ) {
		$this->recorded_queries[] = $query;
		return $this->query_result;
	}

	public function get_charset_collate() {
		return '';
	}
}

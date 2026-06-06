<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Consent;

use Consentful\Consent\ConsentLogSchema;
use PHPUnit\Framework\TestCase;

/**
 * The pure DDL builder: create_table_sql carries every column, the two secondary KEYs
 * and the PRIMARY KEY, and honors the passed table name + charset/collation.
 */
final class ConsentLogSchemaTest extends TestCase {

	private function sql(): string {
		return ConsentLogSchema::create_table_sql(
			'wp_consentful_consent_log',
			'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci'
		);
	}

	public function test_uses_the_passed_table_name_and_charset(): void {
		$sql = $this->sql();

		$this->assertStringContainsString( 'CREATE TABLE wp_consentful_consent_log (', $sql );
		$this->assertStringContainsString( 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci', $sql );
	}

	public function test_has_every_column_with_its_type(): void {
		$sql = $this->sql();

		$this->assertStringContainsString( 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT', $sql );
		$this->assertStringContainsString( 'consent_id VARCHAR(64) NOT NULL', $sql );
		$this->assertStringContainsString( 'created_at DATETIME NOT NULL', $sql );
		$this->assertStringContainsString( 'jurisdiction VARCHAR(16) NOT NULL', $sql );
		$this->assertStringContainsString( 'policy_version SMALLINT UNSIGNED NOT NULL', $sql );
		$this->assertStringContainsString( 'schema_version SMALLINT UNSIGNED NOT NULL', $sql );
		$this->assertStringContainsString( 'banner_version SMALLINT UNSIGNED NOT NULL', $sql );
		$this->assertStringContainsString( 'purposes TEXT NOT NULL', $sql );
		$this->assertStringContainsString( 'ip_hash CHAR(64) NULL', $sql );
		$this->assertStringContainsString( 'ua_hash CHAR(64) NULL', $sql );
	}

	public function test_has_the_primary_key_and_both_secondary_keys(): void {
		$sql = $this->sql();

		$this->assertStringContainsString( 'PRIMARY KEY', $sql );
		$this->assertStringContainsString( 'KEY consent_id (consent_id)', $sql );
		$this->assertStringContainsString( 'KEY created_at (created_at)', $sql );
	}
}

<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit;

use Consentful\Activator;
use Consentful\Tests\Unit\Support\FakeWpdb;
use PHPUnit\Framework\TestCase;

/**
 * Activation creates the Consent log table (via a recorded dbDelta), ensures the
 * record salt, and stamps the DB version. Idempotent. The dbDelta + option writes are
 * exercised through recorder stubs and a fake wpdb (no real database).
 */
final class ActivatorTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['consentful_test_options'] = array();
		$GLOBALS['consentful_test_dbdelta'] = array();
		$GLOBALS['wpdb']                    = FakeWpdb::create();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['consentful_test_options'], $GLOBALS['consentful_test_dbdelta'], $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	public function test_activate_creates_the_table_via_dbdelta(): void {
		Activator::activate();

		$calls = $this->recorded_dbdelta();
		$this->assertCount( 1, $calls );
		$this->assertIsString( $calls[0] );
		$this->assertStringContainsString( 'CREATE TABLE wp_consentful_consent_log', $calls[0] );
	}

	public function test_activate_sets_the_record_salt(): void {
		Activator::activate();

		$salt = get_option( Activator::SALT_OPTION );
		$this->assertIsString( $salt );
		$this->assertNotSame( '', $salt );
	}

	public function test_activate_stamps_the_db_version(): void {
		Activator::activate();

		$this->assertSame( CONSENTFUL_DB_VERSION, get_option( Activator::VERSION_OPTION ) );
	}

	public function test_activate_does_not_overwrite_an_existing_salt(): void {
		update_option( Activator::SALT_OPTION, 'existing-salt' );

		Activator::activate();

		$this->assertSame( 'existing-salt', get_option( Activator::SALT_OPTION ) );
	}

	public function test_activate_is_idempotent(): void {
		Activator::activate();
		$salt = get_option( Activator::SALT_OPTION );

		Activator::activate();

		// A second run keeps the same salt and just re-runs the (harmless) dbDelta.
		$this->assertSame( $salt, get_option( Activator::SALT_OPTION ) );
		$this->assertCount( 2, $this->recorded_dbdelta() );
	}

	/**
	 * The dbDelta calls recorded by the stub for this test.
	 *
	 * @return list<mixed>
	 */
	private function recorded_dbdelta(): array {
		$calls = $GLOBALS['consentful_test_dbdelta'] ?? array();
		return is_array( $calls ) ? array_values( $calls ) : array();
	}
}

<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit;

use Consentful\Activator;
use Consentful\Deactivator;
use PHPUnit\Framework\TestCase;

/**
 * Deactivation clears the scheduled retention purge so a deactivated plugin leaves no
 * phantom cron event — without touching the stored data (that is uninstall's job).
 */
final class DeactivatorTest extends TestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['consentful_test_cron'] );
		parent::tearDown();
	}

	public function test_deactivate_clears_the_scheduled_purge(): void {
		$GLOBALS['consentful_test_cron'] = array( Activator::PURGE_HOOK );

		Deactivator::deactivate();

		$this->assertFalse( wp_next_scheduled( Activator::PURGE_HOOK ) );
	}
}

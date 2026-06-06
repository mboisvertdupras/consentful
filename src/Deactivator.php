<?php
declare( strict_types = 1 );

namespace Consentful;

/**
 * Plugin deactivation: clear the scheduled Consent-log retention purge so a deactivated
 * plugin leaves no phantom cron event. Data (the log table, options) is removed only on
 * uninstall — deactivation is reversible and must not destroy proof of consent.
 */
final class Deactivator {

	public static function deactivate(): void {
		wp_clear_scheduled_hook( Activator::PURGE_HOOK );
	}
}

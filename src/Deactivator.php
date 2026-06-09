<?php
declare( strict_types = 1 );

namespace Consentful;

final class Deactivator {

	public static function deactivate(): void {
		wp_clear_scheduled_hook( Activator::PURGE_HOOK );
	}
}

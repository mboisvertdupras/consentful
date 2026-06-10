<?php
declare( strict_types = 1 );

namespace Consentful\Admin;

final class ConsentLogDownload {

	public static function stream( string $body, string $filename = 'consentful-consent-log.csv' ): void {
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo $body;
		exit;
	}
}

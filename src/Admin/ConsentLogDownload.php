<?php
declare( strict_types = 1 );

namespace Consentful\Admin;

/**
 * Streams the Consent-log CSV as a file download. Isolated in its own shell ON PURPOSE:
 * sending a `text/csv` attachment body is the one place output is not HTML, so the
 * EscapeOutput sniff (an HTML/XSS check) cannot apply — and confining that single
 * unescaped echo here keeps the EscapeOutput sniff fully enforced on every HTML-rendering
 * admin screen (Admin.php). The body is built by the unit-tested ConsentLogExporter, which
 * RFC-4180-quotes every field. Capability + nonce are verified by the caller (Admin) before
 * this runs; the header-send / exit stay out of any tested core.
 */
final class ConsentLogDownload {

	/** Send the CSV body as an attachment and end the request. */
	public static function stream( string $body, string $filename = 'consentful-consent-log.csv' ): void {
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo $body;
		exit;
	}
}

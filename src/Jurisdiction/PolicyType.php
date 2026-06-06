<?php
declare( strict_types = 1 );

namespace Consentful\Jurisdiction;

/**
 * The legal posture a Policy enforces. OptIn denies by default, shows a banner and
 * blocks tags before consent (Loi 25 / GDPR); OptOut allows by default with notice,
 * a Do-Not-Sell control and GPC honored (US); NoticeOnly informs without a banner.
 */
enum PolicyType {

	case OptIn;
	case OptOut;
	case NoticeOnly;
}

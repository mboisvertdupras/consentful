<?php
declare( strict_types = 1 );

namespace Consentful\Jurisdiction;

enum PolicyType {

	case OptIn;
	case OptOut;
	case NoticeOnly;
}

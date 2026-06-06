<?php
declare( strict_types = 1 );

namespace Consentful\Consent;

/**
 * The Google Consent Mode v2 signal vocabulary. Google-adapter-specific, but
 * placed in the core enums per ADR 0001. The backing value is the gtag key.
 */
enum Signal: string {

	case AdStorage              = 'ad_storage';
	case AdUserData             = 'ad_user_data';
	case AdPersonalization      = 'ad_personalization';
	case AnalyticsStorage       = 'analytics_storage';
	case FunctionalityStorage   = 'functionality_storage';
	case PersonalizationStorage = 'personalization_storage';
	case SecurityStorage        = 'security_storage';
}

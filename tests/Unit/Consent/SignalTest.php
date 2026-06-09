<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Consent;

use Consentful\Consent\Signal;
use PHPUnit\Framework\TestCase;

final class SignalTest extends TestCase {

	public function test_backing_values_equal_the_gtag_keys(): void {
		$this->assertSame( 'ad_storage', Signal::AdStorage->value );
		$this->assertSame( 'ad_user_data', Signal::AdUserData->value );
		$this->assertSame( 'ad_personalization', Signal::AdPersonalization->value );
		$this->assertSame( 'analytics_storage', Signal::AnalyticsStorage->value );
		$this->assertSame( 'functionality_storage', Signal::FunctionalityStorage->value );
		$this->assertSame( 'personalization_storage', Signal::PersonalizationStorage->value );
		$this->assertSame( 'security_storage', Signal::SecurityStorage->value );
	}

	public function test_enum_declares_exactly_seven_signals(): void {
		$this->assertCount( 7, Signal::cases() );
	}

	public function test_signals_resolve_from_their_gtag_keys(): void {
		$this->assertSame( Signal::AdStorage, Signal::from( 'ad_storage' ) );
		$this->assertSame( Signal::SecurityStorage, Signal::from( 'security_storage' ) );
	}
}

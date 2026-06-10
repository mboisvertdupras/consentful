<?php
declare( strict_types = 1 );

namespace Consentful\Adapter;

use Consentful\Consent\DefaultPurpose;
use Consentful\Consent\Signal;

final class GoogleAdapter implements Adapter {

	public const ID = 'google';

	/**
	 * @param array<string, array{measurementIds: list<string>, containerIds: list<string>}> $products
	 * @param array<string, list<Signal>>                                                    $purpose_signals
	 */
	public function __construct(
		private readonly array $products = array(),
		private readonly array $purpose_signals = array(),
		private readonly bool $ads_data_redaction = true,
		private readonly bool $url_passthrough = true,
		private readonly int $wait_for_update = 500,
	) {}

	public function id(): string {
		return self::ID;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function client_config(): array {
		$signals = array();
		foreach ( $this->signal_map() as $key => $list ) {
			$signals[ $key ] = array_map(
				static fn ( Signal $signal ): string => $signal->value,
				$list
			);
		}

		return array(
			'handler'          => self::ID,
			'products'         => $this->products,
			'purposeSignals'   => $signals,
			'adsDataRedaction' => $this->ads_data_redaction,
			'urlPassthrough'   => $this->url_passthrough,
			'waitForUpdate'    => $this->wait_for_update,
		);
	}

	/**
	 * @return array<string, list<Signal>>
	 */
	private function signal_map(): array {
		return array() === $this->purpose_signals
			? self::default_signal_map()
			: $this->purpose_signals;
	}

	/**
	 * @return array<string, list<Signal>>
	 */
	public static function default_signal_map(): array {
		return array(
			DefaultPurpose::Necessary->key()       => array( Signal::SecurityStorage ),
			DefaultPurpose::Functional->key()      => array( Signal::FunctionalityStorage ),
			DefaultPurpose::Analytics->key()       => array( Signal::AnalyticsStorage ),
			DefaultPurpose::Marketing->key()       => array( Signal::AdStorage, Signal::AdUserData, Signal::AdPersonalization ),
			DefaultPurpose::Personalization->key() => array( Signal::PersonalizationStorage ),
		);
	}
}

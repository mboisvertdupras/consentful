<?php
declare( strict_types = 1 );

namespace Consentful\Adapter;

use Consentful\Consent\DefaultPurpose;
use Consentful\Consent\Signal;
use Consentful\Tag\Tag;

final class GoogleAdapter implements Adapter {

	public const ID = 'google';

	/**
	 * @param list<string>                $measurement_ids
	 * @param array<string, list<Signal>> $purpose_signals
	 * @param list<string>                $container_ids
	 */
	public function __construct(
		private readonly array $measurement_ids,
		private readonly array $purpose_signals = array(),
		private readonly bool $ads_data_redaction = true,
		private readonly bool $url_passthrough = true,
		private readonly int $wait_for_update = 500,
		private readonly array $container_ids = array(),
	) {}

	public function id(): string {
		return self::ID;
	}

	public function handles( Tag $tag ): bool {
		return self::ID === $tag->adapter_id;
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
			'measurementIds'   => $this->measurement_ids,
			'containerIds'     => $this->container_ids,
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

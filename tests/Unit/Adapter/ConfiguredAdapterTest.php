<?php
declare( strict_types = 1 );

namespace Consentful\Tests\Unit\Adapter;

use Consentful\Adapter\ConfiguredAdapter;
use PHPUnit\Framework\TestCase;

final class ConfiguredAdapterTest extends TestCase {

	public function test_id_and_client_config_round_trip(): void {
		$config  = array(
			'handler' => 'script',
			'code'    => 'console.log(1);',
		);
		$adapter = new ConfiguredAdapter( 'custom-hotjar', $config );

		$this->assertSame( 'custom-hotjar', $adapter->id() );
		$this->assertSame( $config, $adapter->client_config() );
	}
}

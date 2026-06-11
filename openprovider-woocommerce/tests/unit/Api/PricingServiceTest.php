<?php
/**
 * PricingService Test class for OpenProvider WooCommerce
 *
 * @package OpenProviderWooCommerce\Tests\Api
 */

namespace OpenProviderWooCommerce\Tests\Api;

use OpenProviderWooCommerce\Api\PricingService;
use OpenProviderWooCommerce\Api\AuthService;
use OpenProviderWooCommerce\Support\Logger;
use OpenProviderWooCommerce\Tests\Fixtures\HttpClientStub;
use PHPUnit\Framework\TestCase;

/**
 * PricingService unit tests.
 */
class PricingServiceTest extends TestCase {

	/**
	 * Test get_price builds correct query params.
	 */
	public function test_get_price_builds_query_params(): void {
		$http = new HttpClientStub();
		$http->add_response( 200, array(
			'data' => array(
				'price' => 12.99,
				'currency' => 'EUR',
				'premium' => false,
			),
		));

		$auth = $this->createMock( AuthService::class );
		$logger = $this->createMock( Logger::class );

		$pricing = new PricingService( $http, $auth, $logger );

		$result = $pricing->get_price( 'example', 'com', 'create', 2 );

		$this->assertEquals( 12.99, $result['price'] );
		$this->assertEquals( 'EUR', $result['currency'] );
		$this->assertFalse( $result['premium'] );

		// Verify request was made.
		$this->assertNotEmpty( $http->requests );
		$request = $http->requests[0];
		$this->assertStringContainsString( 'domain.name=example', $request['path'] );
		$this->assertStringContainsString( 'domain.extension=com', $request['path'] );
		$this->assertStringContainsString( 'period=2', $request['path'] );
	}

	/**
	 * Test get_price with IDN script.
	 */
	public function test_get_price_with_idn_script(): void {
		$http = new HttpClientStub();
		$http->add_response( 200, array(
			'data' => array(
				'price' => 15.00,
				'currency' => 'USD',
			),
		));

		$auth = $this->createMock( AuthService::class );
		$logger = $this->createMock( Logger::class );

		$pricing = new PricingService( $http, $auth, $logger );

		$result = $pricing->get_price( 'example', 'com', 'create', 1, 'xn--px' );

		$this->assertEquals( 15.00, $result['price'] );
	}
}

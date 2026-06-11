<?php
/**
 * DomainService Test class for OpenProvider WooCommerce
 *
 * @package OpenProviderWooCommerce\Tests\Api
 */

namespace OpenProviderWooCommerce\Tests\Api;

use OpenProviderWooCommerce\Api\DomainService;
use OpenProviderWooCommerce\Api\AuthService;
use OpenProviderWooCommerce\Support\Settings;
use OpenProviderWooCommerce\Support\Logger;
use OpenProviderWooCommerce\Tests\Fixtures\HttpClientStub;
use PHPUnit\Framework\TestCase;

/**
 * DomainService unit tests.
 */
class DomainServiceTest extends TestCase {

	/**
	 * Test check_bulk maps response correctly.
	 */
	public function test_check_bulk_maps_response(): void {
		$http = new HttpClientStub();
		$http->add_response( 200, array(
			'data' => array(
				'domains' => array(
					array(
						'name' => 'example',
						'extension' => 'com',
						'status' => 'free',
						'premium' => false,
						'price' => array(
							'value' => 9.99,
							'currency' => 'EUR',
						),
					),
					array(
						'name' => 'premium-domain',
						'extension' => 'net',
						'status' => 'free',
						'premium' => true,
						'price' => array(
							'value' => 999.99,
							'currency' => 'EUR',
						),
					),
				),
			),
		));

		$auth = $this->createMock( AuthService::class );
		$settings = $this->createMock( Settings::class );
		$logger = $this->createMock( Logger::class );

		$domain = new DomainService( $http, $auth, $settings, $logger );

		$reflection = new \ReflectionClass( $domain );
		$method = $reflection->getMethod( 'check_bulk' );
		$method->setAccessible( true );

		$result = $method->invoke( $domain, array(
			array( 'name' => 'example', 'extension' => 'com' ),
		));

		$this->assertCount( 2, $result );
		$this->assertTrue( $result[0]['available'] );
		$this->assertFalse( $result[0]['premium'] );
		$this->assertEquals( 9.99, $result[0]['price'] );
		$this->assertTrue( $result[1]['premium'] );
	}

	/**
	 * Test check delegates to check_bulk.
	 */
	public function test_check_delegates_to_check_bulk(): void {
		$http = new HttpClientStub();
		$http->add_response( 200, array(
			'data' => array(
				'domains' => array(
					array(
						'name' => 'single',
						'extension' => 'org',
						'status' => 'free',
						'premium' => false,
					),
				),
			),
		));

		$auth = $this->createMock( AuthService::class );
		$settings = $this->createMock( Settings::class );
		$logger = $this->createMock( Logger::class );

		$domain = new DomainService( $http, $auth, $settings, $logger );

		$reflection = new \ReflectionClass( $domain );
		$method = $reflection->getMethod( 'check' );
		$method->setAccessible( true );

		$result = $method->invoke( $domain, 'single', 'org' );

		$this->assertTrue( $result['available'] );
	}
}

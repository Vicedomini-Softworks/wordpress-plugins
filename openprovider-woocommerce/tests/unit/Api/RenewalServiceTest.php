<?php
/**
 * RenewalService Test class for OpenProvider WooCommerce
 *
 * @package OpenProviderWooCommerce\Tests\Api
 */

namespace OpenProviderWooCommerce\Tests\Api;

use OpenProviderWooCommerce\Api\RenewalService;
use OpenProviderWooCommerce\Api\AuthService;
use OpenProviderWooCommerce\Api\ApiException;
use OpenProviderWooCommerce\Support\Settings;
use OpenProviderWooCommerce\Support\Logger;
use OpenProviderWooCommerce\Tests\Fixtures\HttpClientStub;
use PHPUnit\Framework\TestCase;

/**
 * RenewalService unit tests.
 */
class RenewalServiceTest extends TestCase {

	/**
	 * Test get_renewal_price maps response correctly.
	 */
	public function test_get_renewal_price_maps_response(): void {
		$http = new HttpClientStub();
		$http->add_response(
			200,
			array(
				'data' => array(
					'price'          => array(
						'value'    => 14.99,
						'currency' => 'eur',
					),
					'expirationDate' => '2027-01-01T00:00:00Z',
				),
			)
		);

		$auth     = $this->createMock( AuthService::class );
		$settings = $this->createMock( Settings::class );
		$logger   = $this->createMock( Logger::class );

		$renewal = new RenewalService( $http, $auth, $settings, $logger );

		$result = $renewal->get_renewal_price( 'example', 'com', 1 );

		$this->assertEquals( 14.99, $result['price'] );
		$this->assertEquals( 'EUR', $result['currency'] );
		$this->assertEquals( '2027-01-01T00:00:00Z', $result['current_expiry'] );

		$this->assertSame( 'GET', $http->requests[0]['method'] );
		$this->assertStringContainsString( '/domains/prices', $http->requests[0]['path'] );
		$this->assertStringContainsString( 'operation=renew', $http->requests[0]['path'] );
	}

	/**
	 * Test renew_domain maps response correctly.
	 */
	public function test_renew_domain_maps_response(): void {
		$http = new HttpClientStub();
		$http->add_response(
			200,
			array(
				'data' => array(
					'expirationDate' => '2027-06-01T00:00:00Z',
					'orderId'        => '12345',
				),
			)
		);

		$auth     = $this->createMock( AuthService::class );
		$settings = $this->createMock( Settings::class );
		$logger   = $this->createMock( Logger::class );

		$renewal = new RenewalService( $http, $auth, $settings, $logger );

		$result = $renewal->renew_domain(
			array(
				'domain_id' => '999',
				'period'    => 2,
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertEquals( '2027-06-01T00:00:00Z', $result['new_expiry'] );
		$this->assertEquals( '12345', $result['order_id'] );

		$this->assertSame( 'POST', $http->requests[0]['method'] );
		$this->assertSame( '/domains/999/renew', $http->requests[0]['path'] );
		$this->assertSame( array( 'period' => 2 ), $http->requests[0]['options']['body'] );
	}

	/**
	 * Test get_domain_details maps response correctly.
	 */
	public function test_get_domain_details_maps_response(): void {
		$http = new HttpClientStub();
		$http->add_response(
			200,
			array(
				'data' => array(
					'domain'         => array(
						'name'      => 'example',
						'extension' => 'com',
					),
					'expirationDate' => '2027-01-01T00:00:00Z',
					'status'         => 'ACT',
				),
			)
		);

		$auth     = $this->createMock( AuthService::class );
		$settings = $this->createMock( Settings::class );
		$logger   = $this->createMock( Logger::class );

		$renewal = new RenewalService( $http, $auth, $settings, $logger );

		$result = $renewal->get_domain_details( '999' );

		$this->assertEquals( 'example', $result['name'] );
		$this->assertEquals( 'com', $result['extension'] );
		$this->assertEquals( '2027-01-01T00:00:00Z', $result['expiry_date'] );
		$this->assertEquals( 'ACT', $result['status'] );

		$this->assertSame( 'GET', $http->requests[0]['method'] );
		$this->assertSame( '/domains/999', $http->requests[0]['path'] );
	}

	/**
	 * Test enable_auto_renewal sends correct request.
	 */
	public function test_enable_auto_renewal(): void {
		$http = new HttpClientStub();
		$http->add_response( 200, array( 'data' => array() ) );

		$auth     = $this->createMock( AuthService::class );
		$settings = $this->createMock( Settings::class );
		$logger   = $this->createMock( Logger::class );

		$renewal = new RenewalService( $http, $auth, $settings, $logger );

		$result = $renewal->enable_auto_renewal( '999' );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'PUT', $http->requests[0]['method'] );
		$this->assertSame( '/domains/999', $http->requests[0]['path'] );
		$this->assertSame( array( 'autorenew' => true ), $http->requests[0]['options']['body'] );
	}

	/**
	 * Test disable_auto_renewal sends correct request.
	 */
	public function test_disable_auto_renewal(): void {
		$http = new HttpClientStub();
		$http->add_response( 200, array( 'data' => array() ) );

		$auth     = $this->createMock( AuthService::class );
		$settings = $this->createMock( Settings::class );
		$logger   = $this->createMock( Logger::class );

		$renewal = new RenewalService( $http, $auth, $settings, $logger );

		$result = $renewal->disable_auto_renewal( '999' );

		$this->assertTrue( $result['success'] );
		$this->assertSame( array( 'autorenew' => false ), $http->requests[0]['options']['body'] );
	}

	/**
	 * Test ApiException is propagated for non-401 errors.
	 */
	public function test_propagates_non_401_exception(): void {
		$http = new class() implements \OpenProviderWooCommerce\Api\HttpClientInterface {
			public array $requests = array();

			public function request( string $method, string $path, array $options = array() ): array {
				$this->requests[] = array(
					'method'  => $method,
					'path'    => $path,
					'options' => $options,
				);
				throw new ApiException( 'Bad Request', 400, array() );
			}
		};

		$auth     = $this->createMock( AuthService::class );
		$settings = $this->createMock( Settings::class );
		$logger   = $this->createMock( Logger::class );

		$renewal = new RenewalService( $http, $auth, $settings, $logger );

		$this->expectException( ApiException::class );
		$renewal->get_renewal_price( 'example', 'com', 1 );
	}

	/**
	 * Test 401 invalidates token and retries successfully.
	 */
	public function test_invalidates_token_and_retries_on_401(): void {
		$http = new class() implements \OpenProviderWooCommerce\Api\HttpClientInterface {
			public int $calls = 0;

			public function request( string $method, string $path, array $options = array() ): array {
				++$this->calls;
				if ( 1 === $this->calls ) {
					throw new ApiException( 'Unauthorized', 401, array() );
				}

				return array(
					'status'  => 200,
					'body'    => array(
						'data' => array(
							'price' => array(
								'value'    => 9.99,
								'currency' => 'EUR',
							),
						),
					),
					'headers' => array(),
				);
			}
		};

		$auth = $this->createMock( AuthService::class );
		$auth->expects( $this->once() )->method( 'invalidate_token' );

		$settings = $this->createMock( Settings::class );
		$logger   = $this->createMock( Logger::class );

		$renewal = new RenewalService( $http, $auth, $settings, $logger );

		$result = $renewal->get_renewal_price( 'example', 'com', 1 );

		$this->assertEquals( 9.99, $result['price'] );
		$this->assertSame( 2, $http->calls );
	}
}

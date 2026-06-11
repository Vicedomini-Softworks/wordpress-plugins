<?php
/**
 * TransferService Test class for OpenProvider WooCommerce
 *
 * @package OpenProviderWooCommerce\Tests\Api
 */

namespace OpenProviderWooCommerce\Tests\Api;

use OpenProviderWooCommerce\Api\TransferService;
use OpenProviderWooCommerce\Api\AuthService;
use OpenProviderWooCommerce\Api\ApiException;
use OpenProviderWooCommerce\Support\Settings;
use OpenProviderWooCommerce\Support\Logger;
use OpenProviderWooCommerce\Tests\Fixtures\HttpClientStub;
use PHPUnit\Framework\TestCase;

/**
 * TransferService unit tests.
 */
class TransferServiceTest extends TestCase {

	/**
	 * Test check_transfer maps response correctly.
	 */
	public function test_check_transfer_returns_eligibility(): void {
		$http = new HttpClientStub();
		$http->add_response(
			200,
			array(
				'data' => array(
					'transferable'     => true,
					'price'            => array(
						'value'    => 9.99,
						'currency' => 'eur',
					),
					'requiresAuthCode' => true,
					'estimatedDays'    => 5,
				),
			)
		);

		$auth     = $this->createMock( AuthService::class );
		$settings = $this->createMock( Settings::class );
		$logger   = $this->createMock( Logger::class );

		$transfer = new TransferService( $http, $auth, $settings, $logger );

		$result = $transfer->check_transfer( 'example', 'com' );

		$this->assertTrue( $result['available'] );
		$this->assertEquals( 9.99, $result['price'] );
		$this->assertEquals( 'EUR', $result['currency'] );
		$this->assertTrue( $result['requires_auth_code'] );

		$this->assertSame( 'POST', $http->requests[0]['method'] );
		$this->assertSame( '/transfers/check', $http->requests[0]['path'] );
		$this->assertSame(
			array(
				'name'      => 'example',
				'extension' => 'com',
			),
			$http->requests[0]['options']['body']
		);
	}

	/**
	 * Test initiate_transfer maps response correctly.
	 */
	public function test_initiate_transfer_creates_transfer(): void {
		$http = new HttpClientStub();
		$http->add_response(
			200,
			array(
				'data' => array(
					'id'        => 'transfer_abc123',
					'status'    => 'pending_approval',
					'createdAt' => '2026-01-15T10:30:00Z',
				),
			)
		);

		$auth     = $this->createMock( AuthService::class );
		$settings = $this->createMock( Settings::class );
		$logger   = $this->createMock( Logger::class );

		$transfer = new TransferService( $http, $auth, $settings, $logger );

		$result = $transfer->initiate_transfer(
			array(
				'name'           => 'example',
				'extension'      => 'com',
				'auth_code'      => 'ABC123XYZ',
				'owner_handle'   => 'handle123',
				'admin_handle'   => 'handle123',
				'tech_handle'    => 'handle123',
				'billing_handle' => 'handle123',
			)
		);

		$this->assertEquals( 'transfer_abc123', $result['transfer_id'] );
		$this->assertEquals( 'pending_approval', $result['status'] );

		$this->assertSame( 'POST', $http->requests[0]['method'] );
		$this->assertSame( '/transfers', $http->requests[0]['path'] );
		$this->assertSame( 'ABC123XYZ', $http->requests[0]['options']['body']['authCode'] );
		$this->assertSame( array( 'handle' => 'handle123' ), $http->requests[0]['options']['body']['owner'] );
	}

	/**
	 * Test get_transfer_status maps response correctly.
	 */
	public function test_get_transfer_status_returns_progress(): void {
		$http = new HttpClientStub();
		$http->add_response(
			200,
			array(
				'data' => array(
					'id'                  => 'transfer_abc123',
					'status'              => 'in_progress',
					'progress'            => 50,
					'estimatedCompletion' => '2026-01-20T10:30:00Z',
				),
			)
		);

		$auth     = $this->createMock( AuthService::class );
		$settings = $this->createMock( Settings::class );
		$logger   = $this->createMock( Logger::class );

		$transfer = new TransferService( $http, $auth, $settings, $logger );

		$result = $transfer->get_transfer_status( 'transfer_abc123' );

		$this->assertEquals( 'in_progress', $result['status'] );
		$this->assertEquals( 50, $result['progress'] );

		$this->assertSame( 'GET', $http->requests[0]['method'] );
		$this->assertSame( '/transfers/transfer_abc123', $http->requests[0]['path'] );
	}

	/**
	 * Test complete_transfer with auth code.
	 */
	public function test_complete_transfer_with_auth_code(): void {
		$http = new HttpClientStub();
		$http->add_response( 200, array( 'data' => array( 'message' => 'Transfer completed' ) ) );

		$auth     = $this->createMock( AuthService::class );
		$settings = $this->createMock( Settings::class );
		$logger   = $this->createMock( Logger::class );

		$transfer = new TransferService( $http, $auth, $settings, $logger );

		$result = $transfer->complete_transfer( 'transfer_abc123', 'ABC123XYZ' );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'Transfer completed', $result['message'] );

		$this->assertSame( 'PUT', $http->requests[0]['method'] );
		$this->assertSame( '/transfers/transfer_abc123', $http->requests[0]['path'] );
		$this->assertSame( array( 'authCode' => 'ABC123XYZ' ), $http->requests[0]['options']['body'] );
	}

	/**
	 * Test cancel_transfer.
	 */
	public function test_cancel_transfer(): void {
		$http = new HttpClientStub();
		$http->add_response( 200, array( 'data' => array( 'message' => 'Transfer cancelled' ) ) );

		$auth     = $this->createMock( AuthService::class );
		$settings = $this->createMock( Settings::class );
		$logger   = $this->createMock( Logger::class );

		$transfer = new TransferService( $http, $auth, $settings, $logger );

		$result = $transfer->cancel_transfer( 'transfer_abc123' );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'Transfer cancelled', $result['message'] );

		$this->assertSame( 'DELETE', $http->requests[0]['method'] );
		$this->assertSame( '/transfers/transfer_abc123', $http->requests[0]['path'] );
	}

	/**
	 * Test ApiException is propagated for non-401 errors.
	 */
	public function test_propagates_non_401_exception(): void {
		$http = new class() implements \OpenProviderWooCommerce\Api\HttpClientInterface {
			public function request( string $method, string $path, array $options = array() ): array {
				throw new ApiException( 'Bad Request', 400, array() );
			}
		};

		$auth     = $this->createMock( AuthService::class );
		$settings = $this->createMock( Settings::class );
		$logger   = $this->createMock( Logger::class );

		$transfer = new TransferService( $http, $auth, $settings, $logger );

		$this->expectException( ApiException::class );
		$transfer->check_transfer( 'example', 'com' );
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
							'transferable'     => true,
							'price'            => array(
								'value'    => 9.99,
								'currency' => 'EUR',
							),
							'requiresAuthCode' => true,
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

		$transfer = new TransferService( $http, $auth, $settings, $logger );

		$result = $transfer->check_transfer( 'example', 'com' );

		$this->assertTrue( $result['available'] );
		$this->assertSame( 2, $http->calls );
	}
}

<?php
/**
 * AuthService Test class for OpenProvider WooCommerce
 *
 * @package OpenProviderWooCommerce\Tests\Api
 */

namespace OpenProviderWooCommerce\Tests\Api;

use OpenProviderWooCommerce\Api\AuthService;
use OpenProviderWooCommerce\Api\HttpClientInterface;
use OpenProviderWooCommerce\Support\Settings;
use OpenProviderWooCommerce\Support\Logger;
use OpenProviderWooCommerce\Tests\Fixtures\HttpClientStub;
use PHPUnit\Framework\TestCase;

/**
 * AuthService unit tests.
 */
class AuthServiceTest extends TestCase {

	/**
	 * Test login maps token correctly.
	 */
	public function test_login_maps_token(): void {
		$http = new HttpClientStub();
		$http->add_response( 200, array(
			'data' => array(
				'token' => 'test_token_123',
				'resellerId' => 'reseller_456',
				'expiresIn' => 3600,
			),
		));

		$settings = $this->createMock( Settings::class );
		$settings->method( 'get_openprovider_username' )->willReturn( 'test_user' );
		$settings->method( 'get_openprovider_password' )->willReturn( 'test_pass' );

		$logger = $this->createMock( Logger::class );

		$auth = new AuthService( $http, $settings, $logger );

		// Trigger login by calling get_token (which will cache).
		$reflection = new \ReflectionClass( $auth );
		$method = $reflection->getMethod( 'login' );
		$method->setAccessible( true );

		$result = $method->invoke( $auth );

		$this->assertEquals( 'test_token_123', $result['token'] );
		$this->assertEquals( 'reseller_456', $result['reseller_id'] );
	}

	/**
	 * Test login handles nested token field.
	 */
	public function test_login_handles_nested_token(): void {
		$http = new HttpClientStub();
		$http->add_response( 200, array(
			'token' => 'direct_token',
			'reseller_id' => 'reseller_789',
			'expires_in' => 1800,
		));

		$settings = $this->createMock( Settings::class );
		$settings->method( 'get_openprovider_username' )->willReturn( 'test_user' );
		$settings->method( 'get_openprovider_password' )->willReturn( 'test_pass' );

		$logger = $this->createMock( Logger::class );

		$auth = new AuthService( $http, $settings, $logger );

		$reflection = new \ReflectionClass( $auth );
		$method = $reflection->getMethod( 'login' );
		$method->setAccessible( true );

		$result = $method->invoke( $auth );

		$this->assertEquals( 'direct_token', $result['token'] );
	}

	/**
	 * Test login throws on missing token.
	 */
	public function test_login_throws_on_missing_token(): void {
		$http = new HttpClientStub();
		$http->add_response( 200, array(
			'data' => array(),
		));

		$settings = $this->createMock( Settings::class );
		$settings->method( 'get_openprovider_username' )->willReturn( 'test_user' );
		$settings->method( 'get_openprovider_password' )->willReturn( 'test_pass' );

		$logger = $this->createMock( Logger::class );

		$auth = new AuthService( $http, $settings, $logger );

		$reflection = new \ReflectionClass( $auth );
		$method = $reflection->getMethod( 'login' );
		$method->setAccessible( true );

		$this->expectException( \OpenProviderWooCommerce\Api\ApiException::class );
		$method->invoke( $auth );
	}
}

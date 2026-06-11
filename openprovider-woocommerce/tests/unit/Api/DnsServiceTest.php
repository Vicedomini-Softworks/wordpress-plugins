<?php
/**
 * DnsService Test class for OpenProvider WooCommerce
 *
 * @package OpenProviderWooCommerce\Tests\Api
 */

namespace OpenProviderWooCommerce\Tests\Api;

use OpenProviderWooCommerce\Api\DnsService;
use OpenProviderWooCommerce\Api\AuthService;
use OpenProviderWooCommerce\Api\ApiException;
use OpenProviderWooCommerce\Support\Settings;
use OpenProviderWooCommerce\Support\Logger;
use OpenProviderWooCommerce\Tests\Fixtures\HttpClientStub;
use PHPUnit\Framework\TestCase;

/**
 * DnsService unit tests.
 */
class DnsServiceTest extends TestCase {

	/**
	 * Test get_nameservers returns type and servers for custom nameservers.
	 */
	public function test_get_nameservers_returns_type_and_servers(): void {
		$http = new HttpClientStub();
		$http->add_response(
			200,
			array(
				'data' => array(
					'nameServers' => array(
						array( 'name' => 'ns1.example.com' ),
						array( 'name' => 'ns2.example.com' ),
					),
				),
			)
		);

		$auth     = $this->createMock( AuthService::class );
		$settings = $this->createMock( Settings::class );
		$logger   = $this->createMock( Logger::class );

		$dns = new DnsService( $http, $auth, $settings, $logger );

		$result = $dns->get_nameservers( '12345' );

		$this->assertSame( 'custom', $result['type'] );
		$this->assertSame( array( 'ns1.example.com', 'ns2.example.com' ), $result['servers'] );

		$this->assertSame( 'GET', $http->requests[0]['method'] );
		$this->assertSame( '/domains/12345', $http->requests[0]['path'] );
	}

	/**
	 * Test get_nameservers returns 'default' type when no custom nameservers set.
	 */
	public function test_get_nameservers_returns_default_when_empty(): void {
		$http = new HttpClientStub();
		$http->add_response( 200, array( 'data' => array( 'nameServers' => array() ) ) );

		$auth     = $this->createMock( AuthService::class );
		$settings = $this->createMock( Settings::class );
		$logger   = $this->createMock( Logger::class );

		$dns = new DnsService( $http, $auth, $settings, $logger );

		$result = $dns->get_nameservers( '12345' );

		$this->assertSame( 'default', $result['type'] );
		$this->assertSame( array(), $result['servers'] );
	}

	/**
	 * Test update_nameservers saves custom nameservers.
	 */
	public function test_update_nameservers_saves_custom(): void {
		$http = new HttpClientStub();
		$http->add_response( 200, array( 'data' => array( 'message' => 'Nameservers updated' ) ) );

		$auth     = $this->createMock( AuthService::class );
		$settings = $this->createMock( Settings::class );
		$logger   = $this->createMock( Logger::class );

		$dns = new DnsService( $http, $auth, $settings, $logger );

		$result = $dns->update_nameservers( '12345', array( 'ns1.example.com', 'ns2.example.com' ) );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'Nameservers updated', $result['message'] );

		$this->assertSame( 'PUT', $http->requests[0]['method'] );
		$this->assertSame( '/domains/12345', $http->requests[0]['path'] );
		$this->assertSame(
			array(
				array( 'name' => 'ns1.example.com' ),
				array( 'name' => 'ns2.example.com' ),
			),
			$http->requests[0]['options']['body']['nameServers']
		);
	}

	/**
	 * Test reset_nameservers resets to OpenProvider defaults.
	 */
	public function test_reset_nameservers(): void {
		$http = new HttpClientStub();
		$http->add_response( 200, array( 'data' => array( 'message' => 'Nameservers reset' ) ) );

		$auth     = $this->createMock( AuthService::class );
		$settings = $this->createMock( Settings::class );
		$logger   = $this->createMock( Logger::class );

		$dns = new DnsService( $http, $auth, $settings, $logger );

		$result = $dns->reset_nameservers( '12345' );

		$this->assertTrue( $result['success'] );

		$this->assertSame( 'PUT', $http->requests[0]['method'] );
		$this->assertSame( '/domains/12345', $http->requests[0]['path'] );
		$this->assertTrue( $http->requests[0]['options']['body']['useDomainProviderNameservers'] );
	}

	/**
	 * Test get_dns_records returns all types.
	 */
	public function test_get_dns_records_returns_all_types(): void {
		$http = new HttpClientStub();
		$http->add_response(
			200,
			array(
				'data' => array(
					'records' => array(
						array(
							'id'    => 'rec_1',
							'type'  => 'A',
							'name'  => '@',
							'value' => '192.0.2.1',
							'ttl'   => 3600,
						),
						array(
							'id'    => 'rec_2',
							'type'  => 'MX',
							'name'  => '@',
							'value' => 'mail.example.com',
							'ttl'   => 3600,
							'prio'  => 10,
						),
					),
				),
			)
		);

		$auth     = $this->createMock( AuthService::class );
		$settings = $this->createMock( Settings::class );
		$logger   = $this->createMock( Logger::class );

		$dns = new DnsService( $http, $auth, $settings, $logger );

		$result = $dns->get_dns_records( '12345' );

		$this->assertCount( 2, $result );
		$this->assertSame( 'A', $result[0]['type'] );
		$this->assertSame( '192.0.2.1', $result[0]['value'] );
		$this->assertSame( 'MX', $result[1]['type'] );
		$this->assertSame( 10, $result[1]['priority'] );

		$this->assertSame( 'GET', $http->requests[0]['method'] );
		$this->assertSame( '/dns/zones/12345/records', $http->requests[0]['path'] );
	}

	/**
	 * Test get_dns_records filters by type.
	 */
	public function test_get_dns_records_filters_by_type(): void {
		$http = new HttpClientStub();
		$http->add_response( 200, array( 'data' => array( 'records' => array() ) ) );

		$auth     = $this->createMock( AuthService::class );
		$settings = $this->createMock( Settings::class );
		$logger   = $this->createMock( Logger::class );

		$dns = new DnsService( $http, $auth, $settings, $logger );

		$dns->get_dns_records( '12345', 'a' );

		$this->assertSame( '/dns/zones/12345/records?type=A', $http->requests[0]['path'] );
	}

	/**
	 * Test add_dns_record, update_dns_record, and delete_dns_record.
	 */
	public function test_add_update_delete_dns_record(): void {
		$http = new HttpClientStub();
		$http->add_response( 201, array( 'data' => array( 'id' => 'rec_3' ) ) );
		$http->add_response( 200, array( 'data' => array( 'success' => true ) ) );
		$http->add_response( 200, array( 'data' => array( 'success' => true ) ) );

		$auth     = $this->createMock( AuthService::class );
		$settings = $this->createMock( Settings::class );
		$logger   = $this->createMock( Logger::class );

		$dns = new DnsService( $http, $auth, $settings, $logger );

		$add_result = $dns->add_dns_record(
			array(
				'domain_id' => '12345',
				'type'      => 'txt',
				'name'      => '@',
				'value'     => 'v=spf1 -all',
				'ttl'       => 300,
			)
		);

		$this->assertTrue( $add_result['success'] );
		$this->assertSame( 'rec_3', $add_result['id'] );
		$this->assertSame( 'POST', $http->requests[0]['method'] );
		$this->assertSame( '/dns/zones/12345/records', $http->requests[0]['path'] );
		$this->assertSame( 'TXT', $http->requests[0]['options']['body']['type'] );

		$update_result = $dns->update_dns_record(
			'rec_3',
			array(
				'type'  => 'txt',
				'name'  => '@',
				'value' => 'v=spf1 ~all',
				'ttl'   => 300,
			)
		);

		$this->assertTrue( $update_result['success'] );
		$this->assertSame( 'PUT', $http->requests[1]['method'] );
		$this->assertSame( '/dns/records/rec_3', $http->requests[1]['path'] );

		$delete_result = $dns->delete_dns_record( 'rec_3' );

		$this->assertTrue( $delete_result['success'] );
		$this->assertSame( 'DELETE', $http->requests[2]['method'] );
		$this->assertSame( '/dns/records/rec_3', $http->requests[2]['path'] );
	}

	/**
	 * Test get_supported_types returns the expected record types.
	 */
	public function test_get_supported_types(): void {
		$http     = new HttpClientStub();
		$auth     = $this->createMock( AuthService::class );
		$settings = $this->createMock( Settings::class );
		$logger   = $this->createMock( Logger::class );

		$dns = new DnsService( $http, $auth, $settings, $logger );

		$types = $dns->get_supported_types();

		$this->assertContains( 'A', $types );
		$this->assertContains( 'MX', $types );
		$this->assertContains( 'SOA', $types );
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

		$dns = new DnsService( $http, $auth, $settings, $logger );

		$this->expectException( ApiException::class );
		$dns->get_nameservers( '12345' );
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
					'body'    => array( 'data' => array( 'nameServers' => array() ) ),
					'headers' => array(),
				);
			}
		};

		$auth = $this->createMock( AuthService::class );
		$auth->expects( $this->once() )->method( 'invalidate_token' );

		$settings = $this->createMock( Settings::class );
		$logger   = $this->createMock( Logger::class );

		$dns = new DnsService( $http, $auth, $settings, $logger );

		$result = $dns->get_nameservers( '12345' );

		$this->assertSame( 'default', $result['type'] );
		$this->assertSame( 2, $http->calls );
	}
}

<?php
/**
 * RateLimiter Test class for OpenProvider WooCommerce
 *
 * @package OpenProviderWooCommerce\Tests\Support
 */

namespace OpenProviderWooCommerce\Tests\Support;

use OpenProviderWooCommerce\Support\RateLimiter;
use PHPUnit\Framework\TestCase;

/**
 * RateLimiter unit tests.
 */
class RateLimiterTest extends TestCase {

	/**
	 * Test rate limiter allows requests under limit.
	 */
	public function test_allows_under_limit(): void {
		$limiter = new RateLimiter();
		$bucket = 'test_bucket_' . uniqid();

		// First 5 requests should be allowed.
		for ( $i = 0; $i < 5; $i++ ) {
			$this->assertTrue( $limiter->check( $bucket, 10, 60 ) );
		}
	}

	/**
	 * Test rate limiter blocks requests over limit.
	 */
	public function test_blocks_over_limit(): void {
		$limiter = new RateLimiter();
		$bucket = 'test_bucket_block_' . uniqid();

		// First 3 requests should be allowed.
		for ( $i = 0; $i < 3; $i++ ) {
			$this->assertTrue( $limiter->check( $bucket, 3, 60 ) );
		}

		// 4th request should be blocked.
		$this->assertFalse( $limiter->check( $bucket, 3, 60 ) );
	}
}

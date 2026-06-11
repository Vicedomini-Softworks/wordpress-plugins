<?php
/**
 * Crypto Test class for OpenProvider WooCommerce
 *
 * @package OpenProviderWooCommerce\Tests\Api
 */

namespace OpenProviderWooCommerce\Tests\Support;

use OpenProviderWooCommerce\Support\Crypto;
use PHPUnit\Framework\TestCase;

/**
 * Crypto unit tests.
 */
class CryptoTest extends TestCase {

	/**
	 * Test encrypt/decrypt roundtrip.
	 */
	public function test_encrypt_decrypt_roundtrip(): void {
		$original = 'This is a secret message!';
		$encrypted = Crypto::encrypt( $original );

		$this->assertNotSame( $original, $encrypted );
		$this->assertStringStartsWithWithBase64( $encrypted );

		$decrypted = Crypto::decrypt( $encrypted );
		$this->assertEquals( $original, $decrypted );
	}

	/**
	 * Test empty string handling.
	 */
	public function test_empty_string_handling(): void {
		$encrypted = Crypto::encrypt( '' );
		$this->assertEquals( '', $encrypted );

		$decrypted = Crypto::decrypt( '' );
		$this->assertEquals( '', $decrypted );
	}

	/**
	 * Test invalid input handling.
	 */
	public function test_invalid_input_handling(): void {
		// Invalid base64 should return empty string.
		$decrypted = Crypto::decrypt( 'not-valid-base64!!!' );
		$this->assertEquals( '', $decrypted );
	}

	/**
	 * Test different strings produce different encrypted values.
	 */
	public function test_different_strings_produce_different_encrypted_values(): void {
		$encrypted1 = Crypto::encrypt( 'secret1' );
		$encrypted2 = Crypto::encrypt( 'secret2' );

		$this->assertNotEquals( $encrypted1, $encrypted2 );
	}

	/**
	 * Helper to assert base64 format.
	 */
	private function assertStringStartsWithWithBase64( string $str ): void {
		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9+\/=]+$/', $str );
	}
}

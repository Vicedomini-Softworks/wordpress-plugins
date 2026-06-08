<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class VS_Secrets_Manager_Provider {

	abstract public function get( string $name ): ?string;

	/**
	 * @phpstan-impure Persists the secret to the provider's storage.
	 */
	abstract public function set( string $name, string $value, array $meta = array() ): bool;

	/**
	 * @phpstan-impure Removes the secret from the provider's storage.
	 */
	abstract public function delete( string $name ): bool;

	abstract public function test_connection(): array;
}

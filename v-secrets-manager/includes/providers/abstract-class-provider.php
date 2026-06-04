<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class VS_Secrets_Manager_Provider {

	abstract public function get( string $name ): ?string;

	abstract public function set( string $name, string $value, array $meta = array() ): bool;

	abstract public function delete( string $name ): bool;

	abstract public function test_connection(): array;
}

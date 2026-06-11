<?php
/**
 * Logger class for OpenProvider WooCommerce
 *
 * Wraps WC_Logger with plugin-specific source tagging.
 *
 * @package OpenProviderWooCommerce\Support
 */

namespace OpenProviderWooCommerce\Support;

/**
 * Logger class.
 */
class Logger {

	/**
	 * Log source identifier.
	 */
	private const SOURCE = 'openprovider-woocommerce';

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * WC Logger instance.
	 *
	 * @var \WC_Logger_Interface
	 */
	private \WC_Logger_Interface $wc_logger;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings instance.
	 */
	public function __construct( Settings $settings ) {
		$this->settings   = $settings;
		$this->wc_logger  = wc_get_logger();
	}

	/**
	 * Log debug message.
	 *
	 * @param string $message Message to log.
	 * @param array  $context Context data.
	 */
	public function debug( string $message, array $context = array() ): void {
		if ( ! $this->settings->is_debug_enabled() ) {
			return;
		}
		$this->log( 'debug', $message, $context );
	}

	/**
	 * Log info message.
	 *
	 * @param string $message Message to log.
	 * @param array  $context Context data.
	 */
	public function info( string $message, array $context = array() ): void {
		$this->log( 'info', $message, $context );
	}

	/**
	 * Log warning message.
	 *
	 * @param string $message Message to log.
	 * @param array  $context Context data.
	 */
	public function warning( string $message, array $context = array() ): void {
		$this->log( 'warning', $message, $context );
	}

	/**
	 * Log error message.
	 *
	 * @param string $message Message to log.
	 * @param array  $context Context data.
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( 'error', $message, $context );
	}

	/**
	 * Log message with level.
	 *
	 * @param string $level Log level.
	 * @param string $message Message to log.
	 * @param array  $context Context data.
	 */
	private function log( string $level, string $message, array $context = array() ): void {
		$context['source'] = self::SOURCE;
		$this->wc_logger->log( $level, $message, $context );
	}
}

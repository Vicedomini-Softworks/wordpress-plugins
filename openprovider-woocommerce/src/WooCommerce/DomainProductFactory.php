<?php
/**
 * Domain Product Factory class for OpenProvider WooCommerce
 *
 * Creates and manages the virtual domain registration product.
 *
 * @package OpenProviderWooCommerce\WooCommerce
 */

namespace OpenProviderWooCommerce\WooCommerce;

use OpenProviderWooCommerce\Support\Settings;
use OpenProviderWooCommerce\Support\Logger;
use OpenProviderWooCommerce\Api\PricingService;
use OpenProviderWooCommerce\Api\DomainService;

/**
 * Domain Product Factory class.
 */
class DomainProductFactory {

	/**
	 * Option key for cached product ID.
	 */
	private const PRODUCT_ID_OPTION = 'opwc_domain_product_id';

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Pricing service.
	 *
	 * @var PricingService
	 */
	private PricingService $pricing_service;

	/**
	 * Domain service.
	 *
	 * @var DomainService
	 */
	private DomainService $domain_service;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param Settings         $settings Settings.
	 * @param PricingService   $pricing_service Pricing service.
	 * @param DomainService    $domain_service Domain service.
	 * @param Logger           $logger Logger.
	 */
	public function __construct(
		Settings $settings,
		PricingService $pricing_service,
		DomainService $domain_service,
		Logger $logger
	) {
		$this->settings = $settings;
		$this->pricing_service = $pricing_service;
		$this->domain_service = $domain_service;
		$this->logger = $logger;
	}

	/**
	 * Get or create the domain registration product.
	 *
	 * @return int Product ID.
	 */
	public function get_or_create_domain_product(): int {
		$existing_id = get_option( self::PRODUCT_ID_OPTION );

		if ( $existing_id && wc_get_product( $existing_id ) ) {
			return (int) $existing_id;
		}

		// Create new virtual product.
		$product = new \WC_Product_Simple();
		$product->set_props(
			array(
				'name' => __( 'Domain Registration', 'openprovider-woocommerce' ),
				'status' => 'draft',
				'virtual' => true,
				'downloadable' => false,
				'price' => 0,
				'tax_status' => 'none',
			)
		);

		$product->save();

		update_option( self::PRODUCT_ID_OPTION, $product->get_id() );

		$this->logger->info( 'Created domain registration product ID: ' . $product->get_id() );

		return $product->get_id();
	}

	/**
	 * Add domain to cart.
	 *
	 * @param string $domain_name Domain name.
	 * @param string $tld TLD.
	 * @param int    $period Registration period.
	 * @param float  $price Domain price.
	 * @param string $currency Currency code.
	 * @param bool   $is_premium Whether this is a premium domain.
	 * @return string|WP_Error Cart item key or error.
	 */
	public function add_domain_to_cart(
		string $domain_name,
		string $tld,
		int $period,
		float $price,
		string $currency,
		bool $is_premium = false
	): string|\WP_Error {
		$product_id = $this->get_or_create_domain_product();

		// Apply premium markup if applicable.
		if ( $is_premium ) {
			$price = $this->apply_premium_markup( $price );
		}

		$cart_item_data = array(
			'domain_name' => $domain_name,
			'tld' => $tld,
			'registration_period' => $period,
			'price' => $price,
			'currency' => $currency,
			'premium' => $is_premium,
		);

		$cart_item_key = WC()->cart->add_to_cart(
			$product_id,
			1,
			0,
			array(),
			$cart_item_data
		);

		if ( false === $cart_item_key ) {
			return new \WP_Error(
				'opwc_add_to_cart_failed',
				__( 'Failed to add domain to cart', 'openprovider-woocommerce' )
			);
		}

		return $cart_item_key;
	}

	/**
	 * Apply premium markup to price.
	 *
	 * @param float $base_price Base price.
	 * @return float Marked-up price.
	 */
	public function apply_premium_markup( float $base_price ): float {
		$markup_percent = $this->settings->get_premium_markup_percent();
		$cap_percent = $this->settings->get_premium_markup_cap();
		$rounding_mode = $this->settings->get_premium_rounding_mode();

		// Calculate marked-up price.
		$marked_up = $base_price * ( 1 + $markup_percent / 100 );

		// Apply cap.
		$capped = min( $marked_up, $base_price * ( 1 + $cap_percent / 100 ) );

		// Round according to mode.
		return $this->round_price( $capped, $rounding_mode );
	}

	/**
	 * Round price according to mode.
	 *
	 * @param float  $price Price to round.
	 * @param string $mode Rounding mode.
	 * @return float Rounded price.
	 */
	private function round_price( float $price, string $mode ): float {
		switch ( $mode ) {
			case 'nearest_99':
				// Round up to nearest .99.
				$rounded = ceil( $price ) - 0.01;
				return max( $rounded, $price ); // Ensure we don't go below original.
			case 'nearest_5':
				// Round up to nearest .95 or .00.
				$remainder = fmod( $price, 5 );
				if ( $remainder <= 0.05 ) {
					return ceil( $price / 5 ) * 5;
				}
				return ceil( $price / 5 ) * 5 - 0.05;
			case 'nearest_1':
			default:
				// Round up to nearest whole number.
				return ceil( $price );
		}
	}
}

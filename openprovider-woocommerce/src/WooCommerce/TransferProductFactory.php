<?php
/**
 * Transfer Product Factory class for OpenProvider WooCommerce
 *
 * Creates and manages the virtual domain transfer product.
 *
 * @package OpenProviderWooCommerce\WooCommerce
 */

namespace OpenProviderWooCommerce\WooCommerce;

use OpenProviderWooCommerce\Support\Logger;

/**
 * Transfer Product Factory class.
 */
class TransferProductFactory {

	/**
	 * Option key for cached product ID.
	 */
	private const PRODUCT_ID_OPTION = 'opwc_transfer_product_id';

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param Logger $logger Logger.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Get or create the domain transfer product.
	 *
	 * @return int Product ID.
	 */
	public function get_or_create_transfer_product(): int {
		$existing_id = get_option( self::PRODUCT_ID_OPTION );

		if ( $existing_id && wc_get_product( $existing_id ) ) {
			return (int) $existing_id;
		}

		$product = new \WC_Product_Simple();
		$product->set_props(
			array(
				'name'         => __( 'Domain Transfer', 'openprovider-woocommerce' ),
				'status'       => 'draft',
				'virtual'      => true,
				'downloadable' => false,
				'price'        => 0,
				'tax_status'   => 'none',
			)
		);

		$product->save();

		update_option( self::PRODUCT_ID_OPTION, $product->get_id() );

		$this->logger->info( 'Created domain transfer product ID: ' . $product->get_id() );

		return $product->get_id();
	}

	/**
	 * Add a domain transfer to the cart.
	 *
	 * @param string $domain_name Domain name.
	 * @param string $tld TLD.
	 * @param string $auth_code Transfer auth/EPP code.
	 * @param float  $price Transfer price.
	 * @param string $currency Currency code.
	 * @return string|\WP_Error Cart item key or error.
	 */
	public function add_transfer_to_cart(
		string $domain_name,
		string $tld,
		string $auth_code,
		float $price,
		string $currency
	): string|\WP_Error {
		$product_id = $this->get_or_create_transfer_product();

		$cart_item_data = array(
			'openprovider_transfer' => array(
				'domain_name' => $domain_name,
				'tld'         => $tld,
				'auth_code'   => $auth_code,
				'price'       => $price,
				'currency'    => $currency,
			),
		);

		$cart_item_key = WC()->cart->add_to_cart( $product_id, 1, 0, array(), $cart_item_data );

		if ( false === $cart_item_key ) {
			return new \WP_Error(
				'opwc_add_to_cart_failed',
				__( 'Failed to add domain transfer to cart.', 'openprovider-woocommerce' )
			);
		}

		return $cart_item_key;
	}
}

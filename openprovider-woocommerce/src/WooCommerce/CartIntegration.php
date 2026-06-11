<?php
/**
 * Cart Integration class for OpenProvider WooCommerce
 *
 * Handles domain items in the WooCommerce cart.
 *
 * @package OpenProviderWooCommerce\WooCommerce
 */

namespace OpenProviderWooCommerce\WooCommerce;

use OpenProviderWooCommerce\Support\Settings;
use OpenProviderWooCommerce\Support\Logger;
use OpenProviderWooCommerce\Api\DomainService;

/**
 * Cart Integration class.
 */
class CartIntegration {

	/**
	 * Domain product factory.
	 *
	 * @var DomainProductFactory
	 */
	private DomainProductFactory $product_factory;

	/**
	 * Domain service.
	 *
	 * @var DomainService
	 */
	private DomainService $domain_service;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param DomainProductFactory $product_factory Domain product factory.
	 * @param DomainService        $domain_service Domain service.
	 * @param Settings             $settings Settings.
	 * @param Logger               $logger Logger.
	 */
	public function __construct(
		DomainProductFactory $product_factory,
		DomainService $domain_service,
		Settings $settings,
		Logger $logger
	) {
		$this->product_factory = $product_factory;
		$this->domain_service = $domain_service;
		$this->settings = $settings;
		$this->logger = $logger;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		// Set cart item price dynamically.
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'set_cart_item_price' ), 10, 1 );

		// Display cart item meta.
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_meta' ), 10, 2 );

		// Add order item meta on checkout.
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );

		// Prevent quantity changes for domain items.
		add_filter( 'woocommerce_cart_item_quantity', array( $this, 'filter_domain_item_quantity' ), 10, 3 );

		// Validate domain contact fields before checkout.
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_domain_contact_fields' ), 10, 2 );
	}

	/**
	 * Set cart item price dynamically.
	 *
	 * @param \WC_Cart $cart Cart object.
	 */
	public function set_cart_item_price( \WC_Cart $cart ): void {
		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( isset( $cart_item['openprovider_domain'] ) ) {
				$domain_data = $cart_item['openprovider_domain'];
				if ( isset( $domain_data['price'] ) ) {
					$cart_item['data']->set_price( (float) $domain_data['price'] );
				}
			}
		}
	}

	/**
	 * Display cart item meta.
	 *
	 * @param array $item_data Existing item data.
	 * @param array $cart_item Cart item.
	 * @return array Modified item data.
	 */
	public function display_cart_item_meta( array $item_data, array $cart_item ): array {
		if ( ! isset( $cart_item['openprovider_domain'] ) ) {
			return $item_data;
		}

		$domain = $cart_item['openprovider_domain'];

		$item_data[] = array(
			'name'  => __( 'Domain', 'openprovider-woocommerce' ),
			'value' => esc_html( $domain['domain_name'] . '.' . $domain['tld'] ),
		);

		$item_data[] = array(
			'name'  => __( 'Registration Period', 'openprovider-woocommerce' ),
			'value' => esc_html( $domain['registration_period'] . ' ' . _n( 'year', 'years', $domain['registration_period'], 'openprovider-woocommerce' ) ),
		);

		if ( ! empty( $domain['premium'] ) ) {
			$item_data[] = array(
				'name'  => __( 'Domain Type', 'openprovider-woocommerce' ),
				'value' => esc_html__( 'Premium', 'openprovider-woocommerce' ),
			);
		}

		return $item_data;
	}

	/**
	 * Add order item meta on checkout.
	 *
	 * @param \WC_Order_Item_Product $item Order item.
	 * @param string                 $cart_item_key Cart item key.
	 * @param array                  $values Cart item values.
	 * @param \WC_Order              $order Order object.
	 */
	public function add_order_item_meta( \WC_Order_Item_Product $item, string $cart_item_key, array $values, \WC_Order $order ): void {
		if ( ! isset( $values['openprovider_domain'] ) ) {
			return;
		}

		$domain = $values['openprovider_domain'];

		$item->add_meta_data( 'domain_name', $domain['domain_name'] );
		$item->add_meta_data( 'tld', $domain['tld'] );
		$item->add_meta_data( 'registration_period', $domain['registration_period'] );
		$item->add_meta_data( 'openprovider_order_id', '' ); // Will be updated after registration.

		$item->save();
	}

	/**
	 * Filter domain item quantity to always show as 1.
	 *
	 * @param string $quantity_html Quantity HTML.
	 * @param string $cart_item_key Cart item key.
	 * @param array  $cart_item Cart item.
	 * @return string Modified quantity HTML.
	 */
	public function filter_domain_item_quantity( string $quantity_html, string $cart_item_key, array $cart_item ): string {
		if ( isset( $cart_item['openprovider_domain'] ) ) {
			return '<span class="quantity">1</span>';
		}
		return $quantity_html;
	}

	/**
	 * Validate domain contact fields before checkout.
	 *
	 * @param \WC_Checkout $checkout Checkout object.
	 * @param array        $data Posted data.
	 */
	public function validate_domain_contact_fields( \WC_Checkout $checkout, array $data ): void {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$customer_fields = new CustomerProfileFields();

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( ! isset( $cart_item['openprovider_domain']['tld'] ) ) {
				continue;
			}

			$tld = $cart_item['openprovider_domain']['tld'];
			$missing = $customer_fields->missing_fields( $user_id, $tld );

			foreach ( $missing as $field ) {
				$checkout->add_error(
					sprintf(
						/* translators: %s: field label */
						__( 'Please complete your %s in My Account → Edit Account before purchasing a .%s domain.', 'openprovider-woocommerce' ),
						$customer_fields->get_field_label( $field ),
						$tld
					)
				);
			}
		}
	}
}

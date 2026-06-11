<?php
/**
 * Renewal Integration class for OpenProvider WooCommerce
 *
 * Handles domain renewal items in the WooCommerce cart and on order completion.
 *
 * @package OpenProviderWooCommerce\WooCommerce
 */

namespace OpenProviderWooCommerce\WooCommerce;

use OpenProviderWooCommerce\Support\Settings;
use OpenProviderWooCommerce\Support\Logger;
use OpenProviderWooCommerce\Api\RenewalService;
use OpenProviderWooCommerce\Api\ApiException;

/**
 * Renewal Integration class.
 */
class RenewalIntegration {

	/**
	 * Renewal service.
	 *
	 * @var RenewalService
	 */
	private RenewalService $renewal_service;

	/**
	 * Domain repository.
	 *
	 * @var DomainRepository
	 */
	private DomainRepository $repository;

	/**
	 * Notification repository.
	 *
	 * @var NotificationRepository
	 */
	private NotificationRepository $notifications;

	/**
	 * Domain product factory.
	 *
	 * @var DomainProductFactory
	 */
	private DomainProductFactory $product_factory;

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
	 * @param RenewalService          $renewal_service Renewal service.
	 * @param DomainRepository        $repository Domain repository.
	 * @param NotificationRepository  $notifications Notification repository.
	 * @param DomainProductFactory    $product_factory Domain product factory.
	 * @param Settings                $settings Settings.
	 * @param Logger                  $logger Logger.
	 */
	public function __construct(
		RenewalService $renewal_service,
		DomainRepository $repository,
		NotificationRepository $notifications,
		DomainProductFactory $product_factory,
		Settings $settings,
		Logger $logger
	) {
		$this->renewal_service = $renewal_service;
		$this->repository      = $repository;
		$this->notifications   = $notifications;
		$this->product_factory = $product_factory;
		$this->settings        = $settings;
		$this->logger          = $logger;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'set_cart_item_price' ), 10, 1 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_meta' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'on_order_completed' ), 10, 1 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'on_order_completed' ), 10, 1 );
	}

	/**
	 * Add a domain renewal to the cart.
	 *
	 * @param int $domain_id Domain record ID.
	 * @param int $period Renewal period in years.
	 * @return string|\WP_Error Cart item key or error.
	 */
	public function add_renewal_to_cart( int $domain_id, int $period ) {
		$domain = $this->repository->find( $domain_id );

		if ( ! $domain ) {
			return new \WP_Error(
				'opwc_domain_not_found',
				__( 'Domain not found.', 'openprovider-woocommerce' ),
				array( 'status' => 404 )
			);
		}

		try {
			$price_data = $this->renewal_service->get_renewal_price( $domain->domain_name, $domain->tld, $period );
		} catch ( ApiException $e ) {
			$this->logger->error( "Failed to get renewal price for domain {$domain_id}: " . $e->getMessage() );

			return new \WP_Error(
				'opwc_renewal_price_failed',
				__( 'Unable to retrieve renewal price.', 'openprovider-woocommerce' ),
				array( 'status' => 500 )
			);
		}

		$product_id = $this->product_factory->get_or_create_domain_product();

		$cart_item_data = array(
			'openprovider_renewal' => array(
				'domain_id'   => $domain_id,
				'domain_name' => $domain->domain_name,
				'tld'         => $domain->tld,
				'period'      => $period,
				'price'       => $price_data['price'],
				'currency'    => $price_data['currency'],
			),
		);

		$cart_item_key = WC()->cart->add_to_cart( $product_id, 1, 0, array(), $cart_item_data );

		if ( false === $cart_item_key ) {
			return new \WP_Error(
				'opwc_add_to_cart_failed',
				__( 'Failed to add domain renewal to cart.', 'openprovider-woocommerce' ),
				array( 'status' => 500 )
			);
		}

		return $cart_item_key;
	}

	/**
	 * Set cart item price dynamically for renewal items.
	 *
	 * @param \WC_Cart $cart Cart object.
	 */
	public function set_cart_item_price( \WC_Cart $cart ): void {
		foreach ( $cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['openprovider_renewal']['price'] ) ) {
				$cart_item['data']->set_price( (float) $cart_item['openprovider_renewal']['price'] );
			}
		}
	}

	/**
	 * Display cart item meta for renewal items.
	 *
	 * @param array $item_data Existing item data.
	 * @param array $cart_item Cart item.
	 * @return array Modified item data.
	 */
	public function display_cart_item_meta( array $item_data, array $cart_item ): array {
		if ( ! isset( $cart_item['openprovider_renewal'] ) ) {
			return $item_data;
		}

		$renewal = $cart_item['openprovider_renewal'];

		$item_data[] = array(
			'name'  => __( 'Domain Renewal', 'openprovider-woocommerce' ),
			'value' => esc_html( $renewal['domain_name'] . '.' . $renewal['tld'] ),
		);

		$item_data[] = array(
			'name'  => __( 'Renewal Period', 'openprovider-woocommerce' ),
			'value' => esc_html( $renewal['period'] . ' ' . _n( 'year', 'years', $renewal['period'], 'openprovider-woocommerce' ) ),
		);

		return $item_data;
	}

	/**
	 * Add order item meta on checkout for renewal items.
	 *
	 * @param \WC_Order_Item_Product $item Order item.
	 * @param string                 $cart_item_key Cart item key.
	 * @param array                  $values Cart item values.
	 * @param \WC_Order              $order Order object.
	 */
	public function add_order_item_meta( \WC_Order_Item_Product $item, string $cart_item_key, array $values, \WC_Order $order ): void {
		if ( ! isset( $values['openprovider_renewal'] ) ) {
			return;
		}

		$renewal = $values['openprovider_renewal'];

		$item->add_meta_data( 'renewal_domain_id', $renewal['domain_id'] );
		$item->add_meta_data( 'renewal_period', $renewal['period'] );
		$item->add_meta_data( 'openprovider_renewal_completed', '' );

		$item->save();
	}

	/**
	 * Handle order completed/processing status.
	 *
	 * @param int $order_id Order ID.
	 */
	public function on_order_completed( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			$this->process_renewal_line_item( $order, $item );
		}
	}

	/**
	 * Process a renewal line item.
	 *
	 * @param \WC_Order              $order Order object.
	 * @param \WC_Order_Item_Product $item Order item.
	 */
	private function process_renewal_line_item( \WC_Order $order, \WC_Order_Item_Product $item ): void {
		$domain_id = $item->get_meta( 'renewal_domain_id', true );
		$period    = $item->get_meta( 'renewal_period', true );
		$completed = $item->get_meta( 'openprovider_renewal_completed', true );

		if ( ! $domain_id ) {
			return;
		}

		if ( $completed ) {
			$this->logger->debug( "Renewal for domain {$domain_id} already processed, skipping" );
			return;
		}

		$domain = $this->repository->find( (int) $domain_id );

		if ( ! $domain || ! $domain->openprovider_domain_id ) {
			$this->logger->error( "Cannot renew domain {$domain_id}: not found or missing OpenProvider ID" );
			return;
		}

		$period = (int) $period;
		$period = $period > 0 ? $period : 1;

		try {
			$result = $this->renewal_service->renew_domain(
				array(
					'domain_id' => $domain->openprovider_domain_id,
					'period'    => $period,
				)
			);

			$new_expiry = $result['new_expiry'] ?? null;

			if ( $new_expiry ) {
				$new_expiry = gmdate( 'Y-m-d H:i:s', strtotime( $new_expiry ) );
			} else {
				$base       = $domain->expires_at ? strtotime( $domain->expires_at ) : time();
				$new_expiry = gmdate( 'Y-m-d H:i:s', strtotime( "+{$period} year", $base ) );
			}

			$this->repository->mark_renewed( (int) $domain_id, $new_expiry, $period );
			$this->notifications->clear_for_domain( (int) $domain_id );

			$item->update_meta_data( 'openprovider_renewal_completed', ! empty( $result['order_id'] ) ? $result['order_id'] : '1' );
			$item->save();

			$order->add_order_note(
				sprintf(
					/* translators: %1$s: domain name, %2$s: TLD, %3$d: renewal period in years */
					__( 'Domain %1$s.%2$s renewed successfully via OpenProvider for %3$d year(s).', 'openprovider-woocommerce' ),
					$domain->domain_name,
					$domain->tld,
					$period
				)
			);

			$this->logger->info( "Domain {$domain->domain_name}.{$domain->tld} renewed successfully" );
		} catch ( ApiException $e ) {
			$order->add_order_note(
				sprintf(
					/* translators: %1$s: domain name, %2$s: TLD, %3$s: error message */
					__( 'FAILED to renew domain %1$s.%2$s: %3$s', 'openprovider-woocommerce' ),
					$domain->domain_name,
					$domain->tld,
					$e->getMessage()
				)
			);

			$this->logger->error( "Failed to renew domain {$domain->domain_name}.{$domain->tld}: " . $e->getMessage() );
		}
	}
}

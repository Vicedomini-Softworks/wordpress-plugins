<?php
/**
 * Transfer Integration class for OpenProvider WooCommerce
 *
 * Handles domain transfer items in the WooCommerce cart and on order completion.
 *
 * @package OpenProviderWooCommerce\WooCommerce
 */

namespace OpenProviderWooCommerce\WooCommerce;

use OpenProviderWooCommerce\Support\Settings;
use OpenProviderWooCommerce\Support\Logger;
use OpenProviderWooCommerce\Api\TransferService;
use OpenProviderWooCommerce\Api\ApiException;

/**
 * Transfer Integration class.
 */
class TransferIntegration {

	/**
	 * Transfer service.
	 *
	 * @var TransferService
	 */
	private TransferService $transfer_service;

	/**
	 * Domain repository.
	 *
	 * @var DomainRepository
	 */
	private DomainRepository $repository;

	/**
	 * Transfer product factory.
	 *
	 * @var TransferProductFactory
	 */
	private TransferProductFactory $product_factory;

	/**
	 * Contact data resolver.
	 *
	 * @var ContactDataResolver
	 */
	private ContactDataResolver $contact_resolver;

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
	 * @param TransferService        $transfer_service Transfer service.
	 * @param DomainRepository       $repository Domain repository.
	 * @param TransferProductFactory $product_factory Transfer product factory.
	 * @param ContactDataResolver    $contact_resolver Contact data resolver.
	 * @param Settings               $settings Settings.
	 * @param Logger                 $logger Logger.
	 */
	public function __construct(
		TransferService $transfer_service,
		DomainRepository $repository,
		TransferProductFactory $product_factory,
		ContactDataResolver $contact_resolver,
		Settings $settings,
		Logger $logger
	) {
		$this->transfer_service = $transfer_service;
		$this->repository       = $repository;
		$this->product_factory  = $product_factory;
		$this->contact_resolver = $contact_resolver;
		$this->settings         = $settings;
		$this->logger           = $logger;
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
	 * Add a domain transfer to the cart.
	 *
	 * @param string $domain_name Domain name.
	 * @param string $tld TLD.
	 * @param string $auth_code Transfer auth/EPP code.
	 * @return string|\WP_Error Cart item key or error.
	 */
	public function add_transfer_to_cart( string $domain_name, string $tld, string $auth_code ) {
		try {
			$check = $this->transfer_service->check_transfer( $domain_name, $tld );
		} catch ( ApiException $e ) {
			$this->logger->error( "Failed to check transfer eligibility for {$domain_name}.{$tld}: " . $e->getMessage() );

			return new \WP_Error(
				'opwc_transfer_check_failed',
				__( 'Unable to verify transfer eligibility.', 'openprovider-woocommerce' ),
				array( 'status' => 500 )
			);
		}

		if ( ! $check['available'] ) {
			return new \WP_Error(
				'opwc_transfer_not_available',
				sprintf(
					/* translators: %s: domain name */
					__( '%1$s.%2$s is not eligible for transfer.', 'openprovider-woocommerce' ),
					$domain_name,
					$tld
				),
				array( 'status' => 422 )
			);
		}

		if ( $check['requires_auth_code'] && '' === $auth_code ) {
			return new \WP_Error(
				'opwc_auth_code_required',
				__( 'An auth/EPP code is required to transfer this domain.', 'openprovider-woocommerce' ),
				array( 'status' => 422 )
			);
		}

		return $this->product_factory->add_transfer_to_cart( $domain_name, $tld, $auth_code, $check['price'], $check['currency'] );
	}

	/**
	 * Set cart item price dynamically for transfer items.
	 *
	 * @param \WC_Cart $cart Cart object.
	 */
	public function set_cart_item_price( \WC_Cart $cart ): void {
		foreach ( $cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['openprovider_transfer']['price'] ) ) {
				$cart_item['data']->set_price( (float) $cart_item['openprovider_transfer']['price'] );
			}
		}
	}

	/**
	 * Display cart item meta for transfer items.
	 *
	 * @param array $item_data Existing item data.
	 * @param array $cart_item Cart item.
	 * @return array Modified item data.
	 */
	public function display_cart_item_meta( array $item_data, array $cart_item ): array {
		if ( ! isset( $cart_item['openprovider_transfer'] ) ) {
			return $item_data;
		}

		$transfer = $cart_item['openprovider_transfer'];

		$item_data[] = array(
			'name'  => __( 'Domain Transfer', 'openprovider-woocommerce' ),
			'value' => esc_html( $transfer['domain_name'] . '.' . $transfer['tld'] ),
		);

		return $item_data;
	}

	/**
	 * Add order item meta on checkout for transfer items.
	 *
	 * @param \WC_Order_Item_Product $item Order item.
	 * @param string                 $cart_item_key Cart item key.
	 * @param array                  $values Cart item values.
	 * @param \WC_Order              $order Order object.
	 */
	public function add_order_item_meta( \WC_Order_Item_Product $item, string $cart_item_key, array $values, \WC_Order $order ): void {
		if ( ! isset( $values['openprovider_transfer'] ) ) {
			return;
		}

		$transfer = $values['openprovider_transfer'];

		$item->add_meta_data( 'transfer_domain_name', $transfer['domain_name'] );
		$item->add_meta_data( 'transfer_tld', $transfer['tld'] );
		$item->add_meta_data( 'transfer_auth_code', $transfer['auth_code'] );
		$item->add_meta_data( 'transfer_initiated', '' );

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

		$customer_id = $order->get_customer_id();

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			$this->process_transfer_line_item( $order, $item, $customer_id );
		}
	}

	/**
	 * Process a transfer line item.
	 *
	 * @param \WC_Order              $order Order object.
	 * @param \WC_Order_Item_Product $item Order item.
	 * @param int                    $customer_id Customer ID.
	 */
	private function process_transfer_line_item( \WC_Order $order, \WC_Order_Item_Product $item, int $customer_id ): void {
		$domain_name = $item->get_meta( 'transfer_domain_name', true );
		$tld         = $item->get_meta( 'transfer_tld', true );
		$auth_code   = $item->get_meta( 'transfer_auth_code', true );
		$initiated   = $item->get_meta( 'transfer_initiated', true );

		if ( ! $domain_name || ! $tld ) {
			return;
		}

		if ( $initiated ) {
			$this->logger->debug( "Transfer for {$domain_name}.{$tld} already initiated, skipping" );
			return;
		}

		$existing = $this->repository->find_by_domain( $domain_name, $tld );

		if ( $existing && in_array( $existing->transfer_status, array( 'pending_approval', 'in_progress', 'completed' ), true ) ) {
			$this->logger->debug( "Transfer for {$domain_name}.{$tld} already in progress, skipping" );
			return;
		}

		$domain_id = $existing ? (int) $existing->id : $this->repository->insert(
			array(
				'order_id'      => $order->get_id(),
				'order_item_id' => $item->get_id(),
				'customer_id'   => $customer_id,
				'domain_name'   => $domain_name,
				'tld'           => $tld,
				'status'        => 'pending',
			)
		);

		$this->repository->set_transfer_auth_code( $domain_id, $auth_code );

		$this->logger->info( "Initiating transfer for {$domain_name}.{$tld} on order {$order->get_id()}" );

		try {
			$contact_data = $this->contact_resolver->get_contact_data( $customer_id, $tld );

			$result = $this->transfer_service->initiate_transfer(
				array(
					'name'           => $domain_name,
					'extension'      => $tld,
					'auth_code'      => $auth_code,
					'owner_handle'   => $contact_data['owner_handle'],
					'admin_handle'   => $contact_data['admin_handle'],
					'tech_handle'    => $contact_data['tech_handle'],
					'billing_handle' => $contact_data['billing_handle'],
				)
			);

			$this->repository->mark_transfer_initiated( $domain_id, $result['transfer_id'], $result['status'] );

			$item->update_meta_data( 'transfer_initiated', $result['transfer_id'] );
			$item->save();

			$order->add_order_note(
				sprintf(
					/* translators: %1$s: domain name, %2$s: TLD, %3$s: transfer ID */
					__( 'Domain transfer for %1$s.%2$s initiated via OpenProvider (transfer ID: %3$s)', 'openprovider-woocommerce' ),
					$domain_name,
					$tld,
					$result['transfer_id']
				)
			);

			$this->logger->info( "Transfer initiated for {$domain_name}.{$tld}: {$result['transfer_id']}" );
		} catch ( ApiException $e ) {
			$this->repository->mark_transfer_failed( $domain_id, $e->getMessage() );

			$order->add_order_note(
				sprintf(
					/* translators: %1$s: domain name, %2$s: TLD, %3$s: error message */
					__( 'FAILED to initiate transfer for %1$s.%2$s: %3$s', 'openprovider-woocommerce' ),
					$domain_name,
					$tld,
					$e->getMessage()
				)
			);

			$this->notify_admin_failure( $order, $domain_name, $tld, $e );

			$this->logger->error( "Failed to initiate transfer for {$domain_name}.{$tld}: " . $e->getMessage() );
		}
	}

	/**
	 * Notify admin of transfer initiation failure.
	 *
	 * @param \WC_Order  $order Order object.
	 * @param string     $domain_name Domain name.
	 * @param string     $tld TLD.
	 * @param \Throwable $exception Exception that was thrown.
	 */
	private function notify_admin_failure( \WC_Order $order, string $domain_name, string $tld, \Throwable $exception ): void {
		$admin_email = $this->settings->get_admin_notification_email();

		$subject = sprintf(
			/* translators: %s: domain name */
			__( '[OpenProvider WooCommerce] Domain transfer failed: %1$s.%2$s', 'openprovider-woocommerce' ),
			$domain_name,
			$tld
		);

		$message = sprintf(
			/* translators: %1$s: domain name, %2$s: TLD, %3$d: order ID, %4$s: error message */
			__(
				'Domain transfer failed for %1$s.%2$s.

Order ID: %3$d
Error: %4$s

Please check the OpenProvider panel and complete the transfer manually.',
				'openprovider-woocommerce'
			),
			$domain_name,
			$tld,
			$order->get_id(),
			$exception->getMessage()
		);

		if ( class_exists( 'VS_Mailer' ) && method_exists( 'VS_Mailer', 'send' ) ) {
			\VS_Mailer::send( $admin_email, $subject, $message );
		} else {
			wp_mail( $admin_email, $subject, $message );
		}
	}
}

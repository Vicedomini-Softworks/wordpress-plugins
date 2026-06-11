<?php
/**
 * Order Integration class for OpenProvider WooCommerce
 *
 * Handles domain registration on order completion.
 *
 * @package OpenProviderWooCommerce\WooCommerce
 */

namespace OpenProviderWooCommerce\WooCommerce;

use OpenProviderWooCommerce\Support\Settings;
use OpenProviderWooCommerce\Support\Logger;
use OpenProviderWooCommerce\Api\DomainService;
use OpenProviderWooCommerce\Api\ApiException;

/**
 * Order Integration class.
 */
class OrderIntegration {

	/**
	 * Domain service.
	 *
	 * @var DomainService
	 */
	private DomainService $domain_service;

	/**
	 * Domain repository.
	 *
	 * @var DomainRepository
	 */
	private DomainRepository $repository;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Contact data resolver.
	 *
	 * @var ContactDataResolver
	 */
	private ContactDataResolver $contact_resolver;

	/**
	 * Constructor.
	 *
	 * @param DomainService       $domain_service Domain service.
	 * @param DomainRepository    $repository Domain repository.
	 * @param Logger              $logger Logger.
	 * @param Settings            $settings Settings.
	 * @param ContactDataResolver $contact_resolver Contact data resolver.
	 */
	public function __construct(
		DomainService $domain_service,
		DomainRepository $repository,
		Logger $logger,
		Settings $settings,
		ContactDataResolver $contact_resolver
	) {
		$this->domain_service   = $domain_service;
		$this->repository       = $repository;
		$this->logger           = $logger;
		$this->settings         = $settings;
		$this->contact_resolver = $contact_resolver;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		// Hook into order status change to 'completed'.
		add_action( 'woocommerce_order_status_completed', array( $this, 'on_order_completed' ), 10, 1 );

		// Also hook into 'processing' for digital/virtual orders.
		add_action( 'woocommerce_order_status_processing', array( $this, 'on_order_completed' ), 10, 1 );
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

		// Get customer ID.
		$customer_id = $order->get_customer_id();

		// Process each line item.
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			$this->process_domain_line_item( $order, $item, $customer_id );
		}
	}

	/**
	 * Process a domain line item.
	 *
	 * @param \WC_Order          $order Order object.
	 * @param \WC_Order_Item_Product $item Order item.
	 * @param int                $customer_id Customer ID.
	 */
	private function process_domain_line_item( \WC_Order $order, \WC_Order_Item_Product $item, int $customer_id ): void {
		// Check if this is a domain item.
		$domain_name          = $item->get_meta( 'domain_name', true );
		$tld                  = $item->get_meta( 'tld', true );
		$registration_period  = $item->get_meta( 'registration_period', true );
		$existing_op_order_id = $item->get_meta( 'openprovider_order_id', true );

		// Skip if not a domain item or already registered.
		if ( ! $domain_name || ! $tld ) {
			return;
		}

		if ( $existing_op_order_id ) {
			$this->logger->debug( "Domain {$domain_name}.{$tld} already has OpenProvider order ID, skipping" );
			return;
		}

		// Check if already registered in database.
		$existing = $this->repository->find_by_domain( $domain_name, $tld );
		if ( $existing && 'registered' === $existing->status ) {
			$this->logger->debug( "Domain {$domain_name}.{$tld} already registered, skipping" );
			return;
		}

		// Insert pending record.
		$domain_id = $this->repository->insert(
			array(
				'order_id'            => $order_id,
				'order_item_id'       => $item->get_id(),
				'customer_id'         => $customer_id,
				'domain_name'         => $domain_name,
				'tld'                 => $tld,
				'registration_period' => $registration_period,
				'status'              => 'pending',
			)
		);

		$this->logger->info( "Attempting to register domain {$domain_name}.{$tld} for order {$order_id}" );

		try {
			// Get contact handle from customer profile.
			$contact_data = $this->contact_resolver->get_contact_data( $customer_id, $tld );

			// Build registration request.
			$registration_data = array(
				'domain_name'         => $domain_name,
				'tld'                 => $tld,
				'registration_period' => (int) $registration_period,
				'owner_handle'        => $contact_data['owner_handle'],
				'admin_handle'        => $contact_data['admin_handle'],
				'tech_handle'         => $contact_data['tech_handle'],
				'billing_handle'      => $contact_data['billing_handle'],
				'additional_data'     => $contact_data['additional_data'],
			);

			// Register domain.
			$result = $this->domain_service->register( $registration_data );

			// Update order item meta.
			$item->update_meta_data( 'openprovider_order_id', $result['order_id'] ?? '' );
			$item->save();

			// Update domain record.
			$this->repository->mark_registered(
				$domain_id,
				$result['domain_id'] ?? '',
				$result['order_id'] ?? '',
				null // Expiry date would come from OpenProvider response.
			);

			// Add success order note.
			$order->add_order_note(
				sprintf(
					/* translators: %s: OpenProvider order ID */
					__( 'Domain %1$s.%2$s registered successfully via OpenProvider (order: %3$s)', 'openprovider-woocommerce' ),
					$domain_name,
					$tld,
					$result['order_id'] ?? 'N/A'
				)
			);

			$this->logger->info( "Domain {$domain_name}.{$tld} registered successfully" );

		} catch ( ApiException $e ) {
			// Mark domain as failed.
			$this->repository->mark_failed( $domain_id, $e->getMessage() );

			// Add failure order note.
			$order->add_order_note(
				sprintf(
					/* translators: %1$s: domain name, %2$s: error message */
					__( 'FAILED to register domain %1$s.%2$s: %3$s', 'openprovider-woocommerce' ),
					$domain_name,
					$tld,
					$e->getMessage()
				),
				true // Is customer note? No.
			);

			// Notify admin.
			$this->notify_admin_failure( $order, $item, $e );

			$this->logger->error( "Failed to register domain {$domain_name}.{$tld}: " . $e->getMessage() );
		} catch ( \Throwable $e ) {
			// Mark domain as failed.
			$this->repository->mark_failed( $domain_id, $e->getMessage() );

			// Add failure order note.
			$order->add_order_note(
				sprintf(
					/* translators: %1$s: domain name, %2$s: error message */
					__( 'FAILED to register domain %1$s.%2$s: %3$s', 'openprovider-woocommerce' ),
					$domain_name,
					$tld,
					$e->getMessage()
				),
				false
			);

			// Notify admin.
			$this->notify_admin_failure( $order, $item, $e );

			$this->logger->error( "Unexpected error registering domain {$domain_name}.{$tld}: " . $e->getMessage() );
		}
	}

	/**
	 * Notify admin of registration failure.
	 *
	 * @param \WC_Order          $order Order object.
	 * @param \WC_Order_Item_Product $item Order item.
	 * @param \Throwable         $exception Exception that was thrown.
	 */
	private function notify_admin_failure( \WC_Order $order, \WC_Order_Item_Product $item, \Throwable $exception ): void {
		$admin_email = $this->settings->get_admin_notification_email();
		$domain_name = $item->get_meta( 'domain_name', true );
		$tld         = $item->get_meta( 'tld', true );

		$subject = sprintf(
			/* translators: %s: domain name */
			__( '[OpenProvider WooCommerce] Domain registration failed: %1$s.%2$s', 'openprovider-woocommerce' ),
			$domain_name,
			$tld
		);

		$message = sprintf(
			/* translators: %1$s: domain name, %2$s: order ID, %3$s: error message */
			__(
				'Domain registration failed for %1$s.%2$s.

Order ID: %3$s
Error: %4$s

Please check the OpenProvider panel and complete the registration manually.',
				'openprovider-woocommerce'
			),
			$domain_name,
			$tld,
			$order->get_id(),
			$exception->getMessage()
		);

		// Try to use vs-mailer if available.
		if ( class_exists( 'VS_Mailer' ) && method_exists( 'VS_Mailer', 'send' ) ) {
			\VS_Mailer::send( $admin_email, $subject, $message );
		} else {
			wp_mail( $admin_email, $subject, $message );
		}
	}
}

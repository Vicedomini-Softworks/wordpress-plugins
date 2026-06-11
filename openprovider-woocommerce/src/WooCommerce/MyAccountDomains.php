<?php
/**
 * My Account Domains class for OpenProvider WooCommerce
 *
 * Adds a "My Domains" tab to WooCommerce My Account with domain list,
 * details, renewal, and auto-renewal management.
 *
 * @package OpenProviderWooCommerce\WooCommerce
 */

namespace OpenProviderWooCommerce\WooCommerce;

use OpenProviderWooCommerce\Support\Settings;

/**
 * My Account Domains class.
 */
class MyAccountDomains {

	/**
	 * Domain repository.
	 *
	 * @var DomainRepository
	 */
	private DomainRepository $repository;

	/**
	 * Renewal integration.
	 *
	 * @var RenewalIntegration
	 */
	private RenewalIntegration $renewal_integration;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param DomainRepository   $repository Domain repository.
	 * @param RenewalIntegration $renewal_integration Renewal integration.
	 * @param Settings           $settings Settings.
	 */
	public function __construct(
		DomainRepository $repository,
		RenewalIntegration $renewal_integration,
		Settings $settings
	) {
		$this->repository          = $repository;
		$this->renewal_integration = $renewal_integration;
		$this->settings            = $settings;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'add_endpoints' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_item' ) );
		add_action( 'woocommerce_account_my-domains_endpoint', array( $this, 'render_domains_list' ) );
		add_action( 'woocommerce_account_domain-details_endpoint', array( $this, 'render_domain_details' ) );
		add_action( 'template_redirect', array( $this, 'handle_actions' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue My Account domain panel assets.
	 */
	public function enqueue_assets(): void {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}

		wp_enqueue_style(
			'opwc-my-account-domains',
			OPWC_PLUGIN_URL . 'assets/css/my-account-domains.css',
			array(),
			OPWC_VERSION
		);
	}

	/**
	 * Register My Account rewrite endpoints.
	 */
	public function add_endpoints(): void {
		add_rewrite_endpoint( 'my-domains', EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( 'domain-details', EP_ROOT | EP_PAGES );
	}

	/**
	 * Add "My Domains" tab to WooCommerce My Account menu.
	 *
	 * @param array $items Existing menu items.
	 * @return array Modified menu items.
	 */
	public function add_menu_item( array $items ): array {
		$new_items = array();

		foreach ( $items as $key => $label ) {
			// Insert before the logout item, after orders.
			if ( 'customer-logout' === $key ) {
				$new_items['my-domains'] = __( 'My Domains', 'openprovider-woocommerce' );
			}

			$new_items[ $key ] = $label;
		}

		if ( ! isset( $new_items['my-domains'] ) ) {
			$new_items['my-domains'] = __( 'My Domains', 'openprovider-woocommerce' );
		}

		return $new_items;
	}

	/**
	 * Check if current user can access a domain.
	 *
	 * @param int $domain_id Domain ID.
	 * @return bool True if accessible.
	 */
	public function can_access_domain( int $domain_id ): bool {
		$domain = $this->repository->find( $domain_id );

		if ( ! $domain ) {
			return false;
		}

		return get_current_user_id() === (int) $domain->customer_id || current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Get paginated domains for a user.
	 *
	 * @param int $user_id User ID.
	 * @param int $page Page number.
	 * @param int $per_page Items per page.
	 * @return array{domains: array, total: int, pages: int}
	 */
	public function get_user_domains( int $user_id, int $page = 1, int $per_page = 20 ): array {
		return $this->repository->find_by_customer( $user_id, $page, $per_page );
	}

	/**
	 * Get domain with full details for display.
	 *
	 * @param int $domain_id Domain ID.
	 * @return array|null Domain details or null if not found/inaccessible.
	 */
	public function get_domain_details( int $domain_id ): ?array {
		$domain = $this->repository->find( $domain_id );

		if ( ! $domain || ! $this->can_access_domain( $domain_id ) ) {
			return null;
		}

		$days_until_expiry = null;

		if ( $domain->expires_at ) {
			$days_until_expiry = (int) ceil( ( strtotime( $domain->expires_at ) - time() ) / DAY_IN_SECONDS );
		}

		return array(
			'id'                     => (int) $domain->id,
			'domain_name'            => $domain->domain_name,
			'tld'                    => $domain->tld,
			'status'                 => $domain->status,
			'registered_at'          => $domain->registered_at,
			'expires_at'             => $domain->expires_at,
			'days_until_expiry'      => $days_until_expiry,
			'auto_renew'             => (bool) $domain->auto_renew,
			'transfer_status'        => $domain->transfer_status,
			'order_id'               => (int) $domain->order_id,
			'openprovider_domain_id' => $domain->openprovider_domain_id,
		);
	}

	/**
	 * Handle domain renewal action: add a renewal to the cart.
	 *
	 * @param int $domain_id Domain ID.
	 * @param int $period Renewal period in years.
	 * @return array{success: bool, message?: string, cart_url?: string}
	 */
	public function handle_renew_action( int $domain_id, int $period ): array {
		if ( ! $this->can_access_domain( $domain_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'You do not have permission to renew this domain.', 'openprovider-woocommerce' ),
			);
		}

		$cart_item_key = $this->renewal_integration->add_renewal_to_cart( $domain_id, $period );

		if ( is_wp_error( $cart_item_key ) ) {
			return array(
				'success' => false,
				'message' => $cart_item_key->get_error_message(),
			);
		}

		return array(
			'success'  => true,
			'cart_url' => wc_get_cart_url(),
		);
	}

	/**
	 * Handle auto-renewal toggle.
	 *
	 * @param int  $domain_id Domain ID.
	 * @param bool $enabled Whether to enable auto-renewal.
	 * @return array{success: bool, message?: string}
	 */
	public function handle_auto_renew_toggle( int $domain_id, bool $enabled ): array {
		if ( ! $this->can_access_domain( $domain_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'You do not have permission to manage this domain.', 'openprovider-woocommerce' ),
			);
		}

		$this->repository->set_auto_renew( $domain_id, $enabled );

		return array( 'success' => true );
	}

	/**
	 * Render the My Domains list endpoint.
	 */
	public function render_domains_list(): void {
		$page    = max( 1, (int) get_query_var( 'paged', 1 ) );
		$domains = $this->get_user_domains( get_current_user_id(), $page );

		include OPWC_PLUGIN_DIR . 'templates/my-account-domains.php';
	}

	/**
	 * Render the domain details endpoint.
	 */
	public function render_domain_details(): void {
		$domain_id = (int) get_query_var( 'domain-details' );
		$domain    = $this->get_domain_details( $domain_id );

		include OPWC_PLUGIN_DIR . 'templates/my-account-domain-details.php';
	}

	/**
	 * Handle My Account form actions (renew, toggle auto-renew).
	 */
	public function handle_actions(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( isset( $_POST['opwc_renew_domain'] ) && check_admin_referer( 'opwc_renew_domain' ) ) {
			$domain_id = isset( $_POST['domain_id'] ) ? absint( wp_unslash( $_POST['domain_id'] ) ) : 0;
			$period    = isset( $_POST['period'] ) ? absint( wp_unslash( $_POST['period'] ) ) : 1;

			$result = $this->handle_renew_action( $domain_id, $period );

			if ( $result['success'] ) {
				wp_safe_redirect( $result['cart_url'] );
				exit;
			}

			wc_add_notice( $result['message'], 'error' );
			return;
		}

		if ( isset( $_POST['opwc_toggle_auto_renew'] ) && check_admin_referer( 'opwc_toggle_auto_renew' ) ) {
			$domain_id = isset( $_POST['domain_id'] ) ? absint( wp_unslash( $_POST['domain_id'] ) ) : 0;
			$enabled   = ! empty( $_POST['auto_renew'] );

			$result = $this->handle_auto_renew_toggle( $domain_id, $enabled );

			if ( ! $result['success'] ) {
				wc_add_notice( $result['message'], 'error' );
			} else {
				wc_add_notice( __( 'Auto-renewal preference updated.', 'openprovider-woocommerce' ), 'success' );
			}

			$referer = wp_get_referer();
			wp_safe_redirect( $referer ? $referer : wc_get_account_endpoint_url( 'my-domains' ) );
			exit;
		}
	}
}

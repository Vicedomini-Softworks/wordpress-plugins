<?php
/**
 * DNS Integration class for OpenProvider WooCommerce
 *
 * Adds a "Manage DNS" endpoint to WooCommerce My Account for managing
 * nameservers and DNS records of a domain.
 *
 * @package OpenProviderWooCommerce\WooCommerce
 */

namespace OpenProviderWooCommerce\WooCommerce;

use OpenProviderWooCommerce\Api\DnsService;
use OpenProviderWooCommerce\Api\ApiException;
use OpenProviderWooCommerce\Support\Settings;
use OpenProviderWooCommerce\Support\Logger;

/**
 * DNS Integration class.
 */
class DnsIntegration {

	/**
	 * DNS service.
	 *
	 * @var DnsService
	 */
	private DnsService $dns_service;

	/**
	 * Domain repository.
	 *
	 * @var DomainRepository
	 */
	private DomainRepository $repository;

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
	 * @param DnsService       $dns_service DNS service.
	 * @param DomainRepository $repository Domain repository.
	 * @param Settings         $settings Settings.
	 * @param Logger           $logger Logger.
	 */
	public function __construct(
		DnsService $dns_service,
		DomainRepository $repository,
		Settings $settings,
		Logger $logger
	) {
		$this->dns_service = $dns_service;
		$this->repository  = $repository;
		$this->settings    = $settings;
		$this->logger      = $logger;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'add_endpoints' ) );
		add_action( 'woocommerce_account_domain-dns_endpoint', array( $this, 'render_dns_manager' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register My Account rewrite endpoint.
	 */
	public function add_endpoints(): void {
		add_rewrite_endpoint( 'domain-dns', EP_ROOT | EP_PAGES );
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
	 * Render the DNS management endpoint.
	 */
	public function render_dns_manager(): void {
		$domain_id = (int) get_query_var( 'domain-dns' );

		if ( ! $this->can_access_domain( $domain_id ) ) {
			echo '<p>' . esc_html__( 'Domain not found.', 'openprovider-woocommerce' ) . '</p>';
			return;
		}

		$domain = $this->repository->find( $domain_id );

		$nameservers     = array(
			'type'    => 'default',
			'servers' => array(),
		);
		$records         = array();
		$supported_types = $this->dns_service->get_supported_types();
		$error           = '';

		try {
			$nameservers = $this->dns_service->get_nameservers( (string) $domain->openprovider_domain_id );
			$records     = $this->dns_service->get_dns_records( (string) $domain->openprovider_domain_id );
		} catch ( ApiException $e ) {
			$this->logger->error( "Failed to load DNS data for domain {$domain_id}: " . $e->getMessage() );
			$error = __( 'Unable to load DNS information at this time. Please try again later.', 'openprovider-woocommerce' );
		}

		include OPWC_PLUGIN_DIR . 'templates/my-account-dns.php';
	}

	/**
	 * Enqueue DNS manager assets.
	 */
	public function enqueue_assets(): void {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}

		$domain_id = (int) get_query_var( 'domain-dns' );

		if ( ! $domain_id ) {
			return;
		}

		wp_enqueue_style(
			'opwc-dns-manager',
			OPWC_PLUGIN_URL . 'assets/css/dns-manager.css',
			array(),
			OPWC_VERSION
		);

		wp_enqueue_script(
			'opwc-dns-manager',
			OPWC_PLUGIN_URL . 'assets/js/dns-manager.js',
			array( 'jquery' ),
			OPWC_VERSION,
			true
		);

		wp_localize_script(
			'opwc-dns-manager',
			'opwcDns',
			array(
				'domainId' => $domain_id,
				'restUrl'  => rest_url( 'openprovider-woocommerce/v1' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'i18n'     => array(
					'saving'        => __( 'Saving...', 'openprovider-woocommerce' ),
					'saved'         => __( 'Saved.', 'openprovider-woocommerce' ),
					'error'         => __( 'An error occurred. Please try again.', 'openprovider-woocommerce' ),
					'confirmDelete' => __( 'Are you sure you want to delete this DNS record?', 'openprovider-woocommerce' ),
					'addRecord'     => __( 'Add DNS Record', 'openprovider-woocommerce' ),
					'editRecord'    => __( 'Edit DNS Record', 'openprovider-woocommerce' ),
					'cancel'        => __( 'Cancel', 'openprovider-woocommerce' ),
				),
			)
		);
	}
}

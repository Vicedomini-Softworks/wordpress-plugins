<?php
/**
 * Main Plugin class for OpenProvider WooCommerce
 *
 * @package OpenProviderWooCommerce
 */

namespace OpenProviderWooCommerce;

use OpenProviderWooCommerce\Support\Settings;
use OpenProviderWooCommerce\Support\Logger;
use OpenProviderWooCommerce\Support\RateLimiter;
use OpenProviderWooCommerce\Api\HttpClientInterface;
use OpenProviderWooCommerce\Api\WpHttpClient;
use OpenProviderWooCommerce\Api\AuthService;
use OpenProviderWooCommerce\Api\DomainService;
use OpenProviderWooCommerce\Api\PricingService;
use OpenProviderWooCommerce\Api\RenewalService;
use OpenProviderWooCommerce\Api\TransferService;
use OpenProviderWooCommerce\Api\DnsService;
use OpenProviderWooCommerce\Activation\Schema;

/**
 * Main Plugin class.
 */
class Plugin {

	/**
	 * Initialize plugin.
	 */
	public static function init(): void {
		// Check dependencies.
		if ( ! self::check_dependencies() ) {
			return;
		}

		// Load textdomain.
		load_plugin_textdomain(
			'openprovider-woocommerce',
			false,
			dirname( plugin_basename( OPWC_PLUGIN_FILE ) ) . '/languages'
		);

		// Register hooks for all subsystems.
		self::register_hooks();
	}

	/**
	 * Check plugin dependencies.
	 *
	 * @return bool True if all dependencies met.
	 */
	private static function check_dependencies(): bool {
		if ( ! function_exists( 'WC' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'notice_missing_woocommerce' ) );
			return false;
		}
		return true;
	}

	/**
	 * Register all hooks.
	 */
	private static function register_hooks(): void {
		// Initialize core services.
		$settings     = new Settings();
		$logger       = new Logger( $settings );
		$rate_limiter = new RateLimiter();

		// Initialize HTTP client and API services.
		$http_client      = new WpHttpClient( $settings->get_api_base_url(), null, $logger );
		$auth_service     = new AuthService( $http_client, $settings, $logger );
		$domain_service   = new DomainService( $http_client, $auth_service, $settings, $logger );
		$pricing_service  = new PricingService( $http_client, $auth_service, $logger );
		$renewal_service  = new RenewalService( $http_client, $auth_service, $settings, $logger );
		$transfer_service = new TransferService( $http_client, $auth_service, $settings, $logger );
		$dns_service      = new DnsService( $http_client, $auth_service, $settings, $logger );

		// Register WooCommerce integration (hooks).
		$domain_repository       = new WooCommerce\DomainRepository();
		$notification_repository = new WooCommerce\NotificationRepository();
		$contact_resolver        = new WooCommerce\ContactDataResolver();
		$domain_product_factory  = new WooCommerce\DomainProductFactory( $settings, $pricing_service, $domain_service, $logger );
		$cart_integration        = new WooCommerce\CartIntegration( $domain_product_factory, $domain_service, $settings, $logger );
		$order_integration       = new WooCommerce\OrderIntegration( $domain_service, $domain_repository, $logger, $settings, $contact_resolver );

		$cart_integration->register();
		$order_integration->register();

		// Renewal workflow.
		$renewal_integration = new WooCommerce\RenewalIntegration( $renewal_service, $domain_repository, $notification_repository, $domain_product_factory, $settings, $logger );
		$renewal_notifier    = new WooCommerce\RenewalNotifier( $settings, $logger, $domain_repository, $notification_repository );
		$renewal_integration->register();
		$renewal_notifier->register();

		// Transfer workflow.
		$transfer_product_factory = new WooCommerce\TransferProductFactory( $logger );
		$transfer_integration     = new WooCommerce\TransferIntegration( $transfer_service, $domain_repository, $transfer_product_factory, $contact_resolver, $settings, $logger );
		$transfer_integration->register();

		// DNS management.
		$dns_integration = new WooCommerce\DnsIntegration( $dns_service, $domain_repository, $settings, $logger );
		$dns_integration->register();

		// My Account domain panel.
		$my_account_domains = new WooCommerce\MyAccountDomains( $domain_repository, $renewal_integration, $settings );
		$my_account_domains->register();

		// Register REST API routes.
		$domain_search_controller = new Rest\DomainSearchController( $rate_limiter, $settings, $logger, $domain_service, $pricing_service, $domain_product_factory );
		$domain_search_controller->register_routes();

		$pricing_controller = new Rest\PricingController( $rate_limiter, $settings, $logger, $pricing_service );
		$pricing_controller->register_routes();

		$admin_controller = new Rest\AdminController( $rate_limiter, $settings, $logger, $auth_service );
		$admin_controller->register_routes();

		$renewal_controller = new Rest\RenewalController( $rate_limiter, $settings, $logger, $domain_repository, $renewal_integration, $renewal_service );
		$renewal_controller->register_routes();

		$transfer_controller = new Rest\TransferController( $rate_limiter, $settings, $logger, $transfer_service, $transfer_integration, $domain_repository );
		$transfer_controller->register_routes();

		$dns_controller = new Rest\DnsController( $rate_limiter, $settings, $logger, $dns_service, $domain_repository );
		$dns_controller->register_routes();

		// Register admin pages (only in admin).
		if ( is_admin() ) {
			$settings_page = new Admin\SettingsPage( $settings, $auth_service );
			$settings_page->register();
		}

		// Register frontend components.
		$shortcode = new Frontend\Shortcode( $settings );
		$shortcode->register();

		$block = new Frontend\Block( $settings );
		$block->register();

		// Register customer profile fields.
		$customer_profile_fields = new WooCommerce\CustomerProfileFields();
		$customer_profile_fields->register();
	}

	/**
	 * Show admin notice when WooCommerce is not active.
	 */
	public static function notice_missing_woocommerce(): void {
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'OpenProvider for WooCommerce', 'openprovider-woocommerce' ); ?></strong>:
				<?php esc_html_e( 'This plugin requires WooCommerce to be installed and active.', 'openprovider-woocommerce' ); ?>
			</p>
		</div>
		<?php
	}
}

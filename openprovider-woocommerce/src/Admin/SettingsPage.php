<?php
/**
 * Admin Settings Page for OpenProvider WooCommerce
 *
 * @package OpenProviderWooCommerce\Admin
 */

namespace OpenProviderWooCommerce\Admin;

use OpenProviderWooCommerce\Support\Settings;
use OpenProviderWooCommerce\Support\Crypto;
use OpenProviderWooCommerce\Api\AuthService;

/**
 * Admin Settings Page class.
 */
class SettingsPage {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Auth service (for test connection).
	 *
	 * @var AuthService|null
	 */
	private ?AuthService $auth_service;

	/**
	 * Constructor.
	 *
	 * @param Settings     $settings Settings.
	 * @param AuthService  $auth_service Auth service.
	 */
	public function __construct( Settings $settings, ?AuthService $auth_service = null ) {
		$this->settings = $settings;
		$this->auth_service = $auth_service;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_opwc_test_connection', array( $this, 'handle_test_connection' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'OpenProvider Domains', 'openprovider-woocommerce' ),
			__( 'OpenProvider Domains', 'openprovider-woocommerce' ),
			'manage_woocommerce',
			'opwc-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings(): void {
		register_setting(
			'opwc_settings_group',
			'opwc_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		// API Environment section.
		add_settings_section(
			'opwc_api_section',
			__( 'API Configuration', 'openprovider-woocommerce' ),
			array( $this, 'render_api_section' ),
			'opwc-settings'
		);

		add_settings_field(
			'api_environment',
			__( 'Environment', 'openprovider-woocommerce' ),
			array( $this, 'render_api_environment_field' ),
			'opwc-settings',
			'opwc_api_section'
		);

		// Credentials section.
		add_settings_section(
			'opwc_credentials_section',
			__( 'Credentials', 'openprovider-woocommerce' ),
			array( $this, 'render_credentials_section' ),
			'opwc-settings'
		);

		add_settings_field(
			'secret_names',
			__( 'Secret Names (v-secrets-manager)', 'openprovider-woocommerce' ),
			array( $this, 'render_secret_names_field' ),
			'opwc-settings',
			'opwc_credentials_section'
		);

		add_settings_field(
			'local_credentials',
			__( 'Local Encrypted Credentials', 'openprovider-woocommerce' ),
			array( $this, 'render_local_credentials_field' ),
			'opwc-settings',
			'opwc_credentials_section'
		);

		// Domain Configuration section.
		add_settings_section(
			'opwc_domain_section',
			__( 'Domain Configuration', 'openprovider-woocommerce' ),
			array( $this, 'render_domain_section' ),
			'opwc-settings'
		);

		add_settings_field(
			'allowed_tlds',
			__( 'Allowed TLDs', 'openprovider-woocommerce' ),
			array( $this, 'render_allowed_tlds_field' ),
			'opwc-settings',
			'opwc_domain_section'
		);

		add_settings_field(
			'default_registration_period',
			__( 'Default Registration Period', 'openprovider-woocommerce' ),
			array( $this, 'render_default_period_field' ),
			'opwc-settings',
			'opwc_domain_section'
		);

		// Premium Pricing section.
		add_settings_section(
			'opwc_premium_section',
			__( 'Premium Domain Pricing', 'openprovider-woocommerce' ),
			array( $this, 'render_premium_section' ),
			'opwc-settings'
		);

		add_settings_field(
			'premium_markup_percent',
			__( 'Markup Percentage', 'openprovider-woocommerce' ),
			array( $this, 'render_markup_percent_field' ),
			'opwc-settings',
			'opwc_premium_section'
		);

		add_settings_field(
			'premium_markup_cap',
			__( 'Markup Cap', 'openprovider-woocommerce' ),
			array( $this, 'render_markup_cap_field' ),
			'opwc-settings',
			'opwc_premium_section'
		);

		add_settings_field(
			'premium_rounding_mode',
			__( 'Rounding Mode', 'openprovider-woocommerce' ),
			array( $this, 'render_rounding_mode_field' ),
			'opwc-settings',
			'opwc_premium_section'
		);

		// Cache & Logging section.
		add_settings_section(
			'opwc_cache_section',
			__( 'Cache & Logging', 'openprovider-woocommerce' ),
			array( $this, 'render_cache_section' ),
			'opwc-settings'
		);

		add_settings_field(
			'cache_ttl_search',
			__( 'Search Cache TTL', 'openprovider-woocommerce' ),
			array( $this, 'render_cache_ttl_search_field' ),
			'opwc-settings',
			'opwc_cache_section'
		);

		add_settings_field(
			'cache_ttl_pricing',
			__( 'Pricing Cache TTL', 'openprovider-woocommerce' ),
			array( $this, 'render_cache_ttl_pricing_field' ),
			'opwc-settings',
			'opwc_cache_section'
		);

		add_settings_field(
			'debug_logging',
			__( 'Debug Logging', 'openprovider-woocommerce' ),
			array( $this, 'render_debug_logging_field' ),
			'opwc-settings',
			'opwc_cache_section'
		);

		add_settings_field(
			'admin_notification_email',
			__( 'Admin Notification Email', 'openprovider-woocommerce' ),
			array( $this, 'render_admin_email_field' ),
			'opwc-settings',
			'opwc_cache_section'
		);
	}

	/**
	 * Render page.
	 */
	public function render_page(): void {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php settings_errors(); ?>

			<form action="options.php" method="post">
				<?php
				settings_fields( 'opwc_settings_group' );
				do_settings_sections( 'opwc-settings' );
				submit_button( __( 'Save Settings', 'openprovider-woocommerce' ) );
				?>
			</form>

			<hr>

			<h2><?php esc_html_e( 'Test Connection', 'openprovider-woocommerce' ); ?></h2>
			<p><?php esc_html_e( 'Click the button below to test your OpenProvider API connection.', 'openprovider-woocommerce' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="opwc_test_connection">
				<?php wp_nonce_field( 'opwc_test_connection' ); ?>
				<?php submit_button( __( 'Test Connection', 'openprovider-woocommerce' ), 'secondary', 'opwc_test_connection_btn' ); ?>
			</form>
			<div id="opwc-test-result" style="margin-top: 10px;"></div>
		</div>
		<?php
	}

	/**
	 * Render API section description.
	 */
	public function render_api_section(): void {
		?>
		<p><?php esc_html_e( 'Configure whether to use the OpenProvider sandbox (CTE) or production API.', 'openprovider-woocommerce' ); ?></p>
		<?php
	}

	/**
	 * Render API environment field.
	 */
	public function render_api_environment_field(): void {
		$settings = $this->settings->all();
		?>
		<select name="opwc_settings[api_environment]" id="api_environment">
			<option value="sandbox" <?php selected( $settings['api_environment'], 'sandbox' ); ?>><?php esc_html_e( 'Sandbox (CTE)', 'openprovider-woocommerce' ); ?></option>
			<option value="production" <?php selected( $settings['api_environment'], 'production' ); ?>><?php esc_html_e( 'Production', 'openprovider-woocommerce' ); ?></option>
		</select>
		<p class="description">
			<?php esc_html_e( 'Sandbox mode uses OpenProvider\'s test environment. You need a separate CTE account.', 'openprovider-woocommerce' ); ?>
		</p>
		<?php
	}

	/**
	 * Render credentials section description.
	 */
	public function render_credentials_section(): void {
		?>
		<p><?php esc_html_e( 'Configure your OpenProvider API credentials.', 'openprovider-woocommerce' ); ?></p>
		<?php
		$has_vsm = function_exists( 'vs_secrets_manager_get' );
		if ( $has_vsm ) {
			echo '<p class="notice notice-success inline"><p>' . esc_html__( 'v-secrets-manager is active. Use secret names for secure storage.', 'openprovider-woocommerce' ) . '</p></p>';
		} else {
			echo '<p class="notice notice-warning inline"><p>' . esc_html__( 'v-secrets-manager not detected. Credentials will be stored encrypted in the database.', 'openprovider-woocommerce' ) . '</p></p>';
		}
	}

	/**
	 * Render secret names field.
	 */
	public function render_secret_names_field(): void {
		$settings = $this->settings->all();
		?>
		<p>
			<label><?php esc_html_e( 'Username Secret Name:', 'openprovider-woocommerce' ); ?></label><br>
			<input type="text" name="opwc_settings[secret_name_username]" value="<?php echo esc_attr( $settings['secret_name_username'] ); ?>" class="regular-text">
		</p>
		<p>
			<label><?php esc_html_e( 'Password Secret Name:', 'openprovider-woocommerce' ); ?></label><br>
			<input type="text" name="opwc_settings[secret_name_password]" value="<?php echo esc_attr( $settings['secret_name_password'] ); ?>" class="regular-text">
		</p>
		<?php
	}

	/**
	 * Render local credentials field.
	 */
	public function render_local_credentials_field(): void {
		?>
		<p><em><?php esc_html_e( 'Only used if v-secrets-manager is not active or secret names are not configured.', 'openprovider-woocommerce' ); ?></em></p>
		<p>
			<label><?php esc_html_e( 'Username:', 'openprovider-woocommerce' ); ?></label><br>
			<input type="text" name="opwc_settings[local_username]" value="" class="regular-text" autocomplete="off">
		</p>
		<p>
			<label><?php esc_html_e( 'Password:', 'openprovider-woocommerce' ); ?></label><br>
			<input type="password" name="opwc_settings[local_password]" value="" class="regular-text" autocomplete="off">
		</p>
		<?php
	}

	/**
	 * Render domain section description.
	 */
	public function render_domain_section(): void {
		?>
		<p><?php esc_html_e( 'Configure which TLDs customers can register.', 'openprovider-woocommerce' ); ?></p>
		<?php
	}

	/**
	 * Render allowed TLDs field.
	 */
	public function render_allowed_tlds_field(): void {
		$settings = $this->settings->all();
		$tlds = implode( ', ', $settings['allowed_tlds'] );
		?>
		<input type="text" name="opwc_settings[allowed_tlds]" value="<?php echo esc_attr( $tlds ); ?>" class="regular-text">
		<p class="description"><?php esc_html_e( 'Comma-separated list of TLDs (without dots). Example: com,net,org,it,eu', 'openprovider-woocommerce' ); ?></p>
		<?php
	}

	/**
	 * Render default period field.
	 */
	public function render_default_period_field(): void {
		$settings = $this->settings->all();
		?>
		<select name="opwc_settings[default_registration_period]">
			<?php for ( $i = 1; $i <= 10; $i++ ): ?>
				<option value="<?php echo $i; ?>" <?php selected( $settings['default_registration_period'], $i ); ?>><?php echo $i; ?> <?php echo 1 === $i ? esc_html__( 'year', 'openprovider-woocommerce' ) : esc_html__( 'years', 'openprovider-woocommerce' ); ?></option>
			<?php endfor; ?>
		</select>
		<?php
	}

	/**
	 * Render premium section description.
	 */
	public function render_premium_section(): void {
		?>
		<p><?php esc_html_e( 'Configure markup for premium domains.', 'openprovider-woocommerce' ); ?></p>
		<?php
	}

	/**
	 * Render markup percent field.
	 */
	public function render_markup_percent_field(): void {
		$settings = $this->settings->all();
		?>
		<input type="number" name="opwc_settings[premium_markup_percent]" value="<?php echo esc_attr( $settings['premium_markup_percent'] ); ?>" min="0" max="100" class="small-text"> %
		<?php
	}

	/**
	 * Render markup cap field.
	 */
	public function render_markup_cap_field(): void {
		$settings = $this->settings->all();
		?>
		<input type="number" name="opwc_settings[premium_markup_cap]" value="<?php echo esc_attr( $settings['premium_markup_cap'] ); ?>" min="0" max="200" class="small-text"> %
		<?php
	}

	/**
	 * Render rounding mode field.
	 */
	public function render_rounding_mode_field(): void {
		$settings = $this->settings->all();
		?>
		<select name="opwc_settings[premium_rounding_mode]">
			<option value="nearest_99" <?php selected( $settings['premium_rounding_mode'], 'nearest_99' ); ?>><?php esc_html_e( 'Nearest .99', 'openprovider-woocommerce' ); ?></option>
			<option value="nearest_1" <?php selected( $settings['premium_rounding_mode'], 'nearest_1' ); ?>><?php esc_html_e( 'Nearest whole number', 'openprovider-woocommerce' ); ?></option>
			<option value="nearest_5" <?php selected( $settings['premium_rounding_mode'], 'nearest_5' ); ?>><?php esc_html_e( 'Nearest .95 or .00', 'openprovider-woocommerce' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Render cache section description.
	 */
	public function render_cache_section(): void {
		?>
		<p><?php esc_html_e( 'Configure caching and logging settings.', 'openprovider-woocommerce' ); ?></p>
		<?php
	}

	/**
	 * Render search cache TTL field.
	 */
	public function render_cache_ttl_search_field(): void {
		$settings = $this->settings->all();
		?>
		<input type="number" name="opwc_settings[cache_ttl_search]" value="<?php echo esc_attr( $settings['cache_ttl_search'] ); ?>" min="60" class="small-text">
		<?php esc_html_e( 'seconds (default: 300)', 'openprovider-woocommerce' ); ?>
		<?php
	}

	/**
	 * Render pricing cache TTL field.
	 */
	public function render_cache_ttl_pricing_field(): void {
		$settings = $this->settings->all();
		?>
		<input type="number" name="opwc_settings[cache_ttl_pricing]" value="<?php echo esc_attr( $settings['cache_ttl_pricing'] ); ?>" min="300" class="small-text">
		<?php esc_html_e( 'seconds (default: 43200 = 12 hours)', 'openprovider-woocommerce' ); ?>
		<?php
	}

	/**
	 * Render debug logging field.
	 */
	public function render_debug_logging_field(): void {
		$settings = $this->settings->all();
		?>
		<label>
			<input type="checkbox" name="opwc_settings[debug_logging_enabled]" value="1" <?php checked( $settings['debug_logging_enabled'] ); ?>>
			<?php esc_html_e( 'Enable debug logging', 'openprovider-woocommerce' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Logs API requests and responses to WooCommerce > Status > Logs.', 'openprovider-woocommerce' ); ?></p>
		<?php
	}

	/**
	 * Render admin email field.
	 */
	public function render_admin_email_field(): void {
		$settings = $this->settings->all();
		$default = get_option( 'admin_email' );
		$value = $settings['admin_notification_email'] ?: $default;
		?>
		<input type="email" name="opwc_settings[admin_notification_email]" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
		<p class="description">
			<?php
			printf(
				/* translators: %s: default email */
				esc_html__( 'Leave empty to use site admin email (%s).', 'openprovider-woocommerce' ),
				esc_html( $default )
			);
			?>
		</p>
		<?php
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( array $input ): array {
		$current = $this->settings->all();

		// API environment.
		$output['api_environment'] = in_array( $input['api_environment'] ?? 'sandbox', array( 'sandbox', 'production' ), true )
			? $input['api_environment']
			: 'sandbox';

		// Secret names.
		$output['secret_name_username'] = sanitize_text_field( $input['secret_name_username'] ?? '' );
		$output['secret_name_password'] = sanitize_text_field( $input['secret_name_password'] ?? '' );

		// Local credentials (encrypt if provided).
		if ( ! empty( $input['local_username'] ) ) {
			$output['local_username_encrypted'] = Crypto::encrypt( sanitize_text_field( $input['local_username'] ) );
		} else {
			$output['local_username_encrypted'] = $current['local_username_encrypted'] ?? '';
		}

		if ( ! empty( $input['local_password'] ) ) {
			$output['local_password_encrypted'] = Crypto::encrypt( sanitize_text_field( $input['local_password'] ) );
		} else {
			$output['local_password_encrypted'] = $current['local_password_encrypted'] ?? '';
		}

		// Allowed TLDs.
		$tlds = isset( $input['allowed_tlds'] ) ? explode( ',', $input['allowed_tlds'] ) : array();
		$output['allowed_tlds'] = array_map(
			function( $tld ) {
				return strtolower( trim( $tld ) );
			},
			array_filter( $tlds )
		);

		// Default period.
		$output['default_registration_period'] = max( 1, min( 10, (int) ( $input['default_registration_period'] ?? 1 ) ) );

		// Premium settings.
		$output['premium_markup_percent'] = max( 0, min( 100, (int) ( $input['premium_markup_percent'] ?? 20 ) ) );
		$output['premium_markup_cap'] = max( 0, min( 200, (int) ( $input['premium_markup_cap'] ?? 50 ) ) );
		$output['premium_rounding_mode'] = in_array( $input['premium_rounding_mode'] ?? 'nearest_99', array( 'nearest_99', 'nearest_1', 'nearest_5' ), true )
			? $input['premium_rounding_mode']
			: 'nearest_99';

		// Cache TTLs.
		$output['cache_ttl_search'] = max( 60, (int) ( $input['cache_ttl_search'] ?? 300 ) );
		$output['cache_ttl_pricing'] = max( 300, (int) ( $input['cache_ttl_pricing'] ?? 43200 ) );

		// Debug logging.
		$output['debug_logging_enabled'] = ! empty( $input['debug_logging_enabled'] );

		// Admin email.
		$output['admin_notification_email'] = is_email( $input['admin_notification_email'] ?? '' )
			? $input['admin_notification_email']
			: '';

		return $output;
	}

	/**
	 * Handle test connection via admin-post.
	 */
	public function handle_test_connection(): void {
		check_admin_referer( 'opwc_test_connection' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'openprovider-woocommerce' ) );
		}

		if ( ! $this->auth_service ) {
			wp_redirect( add_query_arg( array( 'opwc_test_result' => 'error_no_service' ), admin_url( 'admin.php?page=opwc-settings' ) ) );
			exit;
		}

		try {
			$this->auth_service->login();
			$redirect = add_query_arg( array( 'opwc_test_result' => 'success' ), admin_url( 'admin.php?page=opwc-settings' ) );
		} catch ( \Throwable $e ) {
			$redirect = add_query_arg(
				array(
					'opwc_test_result' => 'error',
					'opwc_test_message' => rawurlencode( $e->getMessage() ),
				),
				admin_url( 'admin.php?page=opwc-settings' )
			);
		}

		wp_redirect( $redirect );
		exit;
	}
}

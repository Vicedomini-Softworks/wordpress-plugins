<?php
/**
 * Block class for OpenProvider WooCommerce
 *
 * @package OpenProviderWooCommerce\Frontend
 */

namespace OpenProviderWooCommerce\Frontend;

use OpenProviderWooCommerce\Support\Settings;

/**
 * Block class.
 */
class Block {

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register block.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Register block.
	 */
	public function register_block(): void {
		// Register via block.json.
		register_block_type(
			OPWC_PLUGIN_DIR . 'blocks/domain-search/block.json',
			array(
				'render_callback' => array( $this, 'render_callback' ),
			)
		);
	}

	/**
	 * Render callback for dynamic block.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content Block content.
	 * @param \WP_Block $block Block instance.
	 * @return string
	 */
	public function render_callback( array $attributes, string $content, \WP_Block $block ): string {
		// Enqueue assets.
		$this->enqueue_assets();

		// Get attributes.
		$default_tlds = ! empty( $attributes['defaultTlds'] ) ? $attributes['defaultTlds'] : $this->settings->get_allowed_tlds();
		$button_label = ! empty( $attributes['buttonLabel'] ) ? $attributes['buttonLabel'] : __( 'Search', 'openprovider-woocommerce' );

		// Render template.
		ob_start();
		include OPWC_PLUGIN_DIR . 'templates/shortcode-domain-search.php';
		return ob_get_clean();
	}

	/**
	 * Enqueue assets.
	 */
	private function enqueue_assets(): void {
		wp_enqueue_style(
			'opwc-domain-search',
			OPWC_PLUGIN_URL . 'assets/css/domain-search.css',
			array(),
			OPWC_VERSION
		);

		wp_enqueue_script(
			'opwc-domain-search',
			OPWC_PLUGIN_URL . 'assets/js/domain-search.js',
			array( 'jquery' ),
			OPWC_VERSION,
			true
		);

		wp_localize_script(
			'opwc-domain-search',
			'opwcSearch',
			array(
				'restUrl' => rest_url( 'openprovider-woocommerce/v1/search' ),
				'cartUrl' => rest_url( 'openprovider-woocommerce/v1/cart/add' ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'allowedTlds' => $this->settings->get_allowed_tlds(),
				'defaultPeriod' => $this->settings->get_default_registration_period(),
				'i18n' => array(
					'searching' => __( 'Searching...', 'openprovider-woocommerce' ),
					'addToIntCart' => __( 'Add to Cart', 'openprovider-woocommerce' ),
					'addedToCart' => __( 'Added to cart!', 'openprovider-woocommerce' ),
					'unavailable' => __( 'Unavailable', 'openprovider-woocommerce' ),
					'premium' => __( 'Premium', 'openprovider-woocommerce' ),
					'error' => __( 'An error occurred. Please try again.', 'openprovider-woocommerce' ),
				),
			)
		);
	}
}

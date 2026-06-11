<?php
/**
 * Domain Transfer Shortcode Template
 *
 * @package OpenProviderWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="opwc-transfer-search" data-allowed-tlds="<?php echo esc_attr( wp_json_encode( $allowed_tlds ?? array() ) ); ?>">
	<div class="opwc-transfer-form">
		<input
			type="text"
			class="opwc-transfer-domain"
			placeholder="example.com"
			aria-label="<?php esc_attr_e( 'Domain name to transfer', 'openprovider-woocommerce' ); ?>"
		/>
		<button type="button" class="opwc-transfer-check-btn">
			<?php esc_html_e( 'Check Transfer', 'openprovider-woocommerce' ); ?>
		</button>
	</div>

	<div class="opwc-transfer-result" style="display: none;">
		<div class="opwc-transfer-price"></div>

		<div class="opwc-transfer-auth-code-input" style="display: none;">
			<label for="opwc-auth-code"><?php esc_html_e( 'Enter Auth/EPP Code:', 'openprovider-woocommerce' ); ?></label>
			<input type="text" id="opwc-auth-code" class="opwc-auth-code" />
		</div>

		<button type="button" class="opwc-transfer-add-cart" style="display: none;">
			<?php esc_html_e( 'Add to Cart', 'openprovider-woocommerce' ); ?>
		</button>
	</div>

	<div class="opwc-transfer-result-error" style="display: none;"></div>
</div>

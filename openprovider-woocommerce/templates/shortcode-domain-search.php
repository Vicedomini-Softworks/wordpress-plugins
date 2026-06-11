<?php
/**
 * Domain Search Shortcode/Block Template
 *
 * @package OpenProviderWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="opwc-domain-search" data-default-period="<?php echo esc_attr( $default_period ?? 1 ); ?>">
	<div class="opwc-search-form">
		<div class="opwc-search-input-group">
			<input
				type="text"
				class="opwc-domain-input"
				placeholder="<?php esc_attr_e( 'Enter domain name (e.g., example)', 'openprovider-woocommerce' ); ?>"
				aria-label="<?php esc_attr_e( 'Domain name', 'openprovider-woocommerce' ); ?>"
			/>
			<select class="opwc-tld-select">
				<?php foreach ( $allowed_tlds ?? array( 'com', 'net', 'org' ) as $tld ): ?>
					<option value="<?php echo esc_attr( $tld ); ?>">.<?php echo esc_html( $tld ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="opwc-period-selector">
			<label for="opwc-period"><?php esc_html_e( 'Period:', 'openprovider-woocommerce' ); ?></label>
			<select id="opwc-period" class="opwc-period-select">
				<?php for ( $i = 1; $i <= 10; $i++ ): ?>
					<option value="<?php echo $i; ?>" <?php selected( $i, $default_period ?? 1 ); ?>>
						<?php echo $i; ?> <?php echo 1 === $i ? esc_html__( 'year' ) : esc_html__( 'years' ); ?>
					</option>
				<?php endfor; ?>
			</select>
		</div>

		<button type="button" class="opwc-search-btn">
			<?php esc_html_e( 'Search', 'openprovider-woocommerce' ); ?>
		</button>
	</div>

	<div class="opwc-search-results" style="display: none;"></div>
</div>

<?php
/**
 * My Account → Domain Details template
 *
 * @package OpenProviderWooCommerce
 *
 * @var array|null $domain Domain details, or null if not found/inaccessible.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( null === $domain ) :
	?>
	<div class="opwc-domain-details">
		<p><?php esc_html_e( 'Domain not found.', 'openprovider-woocommerce' ); ?></p>
		<a class="woocommerce-button button" href="<?php echo esc_url( wc_get_account_endpoint_url( 'my-domains' ) ); ?>">
			<?php esc_html_e( 'Back to My Domains', 'openprovider-woocommerce' ); ?>
		</a>
	</div>
	<?php
	return;
endif;
?>
<div class="opwc-domain-details" data-domain-id="<?php echo esc_attr( $domain['id'] ); ?>">
	<h2><?php echo esc_html( $domain['domain_name'] . '.' . $domain['tld'] ); ?></h2>

	<table class="opwc-domain-details-table shop_table">
		<tbody>
			<tr>
				<th><?php esc_html_e( 'Status', 'openprovider-woocommerce' ); ?></th>
				<td>
					<span class="opwc-domain-status status-<?php echo esc_attr( $domain['status'] ); ?>">
						<?php echo esc_html( ucfirst( $domain['status'] ) ); ?>
					</span>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Registered On', 'openprovider-woocommerce' ); ?></th>
				<td>
					<?php
					echo $domain['registered_at']
						? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $domain['registered_at'] ) ) )
						: esc_html__( 'N/A', 'openprovider-woocommerce' );
					?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Expiry Date', 'openprovider-woocommerce' ); ?></th>
				<td>
					<?php if ( $domain['expires_at'] ) : ?>
						<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $domain['expires_at'] ) ) ); ?>
						<?php if ( null !== $domain['days_until_expiry'] && $domain['days_until_expiry'] <= 30 ) : ?>
							<small class="opwc-expiry-warning">
								(<?php echo esc_html( $domain['days_until_expiry'] ); ?> <?php esc_html_e( 'days', 'openprovider-woocommerce' ); ?>)
							</small>
						<?php endif; ?>
					<?php else : ?>
						<?php esc_html_e( 'N/A', 'openprovider-woocommerce' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<?php if ( $domain['transfer_status'] ) : ?>
				<tr>
					<th><?php esc_html_e( 'Transfer Status', 'openprovider-woocommerce' ); ?></th>
					<td><?php echo esc_html( ucfirst( $domain['transfer_status'] ) ); ?></td>
				</tr>
			<?php endif; ?>
			<?php if ( $domain['order_id'] ) : ?>
				<tr>
					<th><?php esc_html_e( 'Order', 'openprovider-woocommerce' ); ?></th>
					<td>
						<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'view-order' ) . $domain['order_id'] . '/' ); ?>">
							#<?php echo esc_html( $domain['order_id'] ); ?>
						</a>
					</td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( 'registered' === $domain['status'] ) : ?>
		<div class="opwc-domain-actions">
			<h3><?php esc_html_e( 'Auto-Renewal', 'openprovider-woocommerce' ); ?></h3>
			<form method="post" class="opwc-auto-renew-form">
				<?php wp_nonce_field( 'opwc_toggle_auto_renew' ); ?>
				<input type="hidden" name="domain_id" value="<?php echo esc_attr( $domain['id'] ); ?>" />
				<label>
					<input type="checkbox" name="auto_renew" value="1" <?php checked( $domain['auto_renew'] ); ?> />
					<?php esc_html_e( 'Automatically renew this domain before it expires', 'openprovider-woocommerce' ); ?>
				</label>
				<button type="submit" name="opwc_toggle_auto_renew" value="1" class="woocommerce-button button">
					<?php esc_html_e( 'Save', 'openprovider-woocommerce' ); ?>
				</button>
			</form>

			<h3><?php esc_html_e( 'Renew Domain', 'openprovider-woocommerce' ); ?></h3>
			<form method="post" class="opwc-renew-form">
				<?php wp_nonce_field( 'opwc_renew_domain' ); ?>
				<input type="hidden" name="domain_id" value="<?php echo esc_attr( $domain['id'] ); ?>" />
				<label for="opwc-renewal-period"><?php esc_html_e( 'Renewal Period', 'openprovider-woocommerce' ); ?></label>
				<select name="period" id="opwc-renewal-period">
					<?php for ( $year = 1; $year <= 10; $year++ ) : ?>
						<option value="<?php echo esc_attr( $year ); ?>">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: number of years */
									_n( '%d year', '%d years', $year, 'openprovider-woocommerce' ),
									$year
								)
							);
							?>
						</option>
					<?php endfor; ?>
				</select>
				<button type="submit" name="opwc_renew_domain" value="1" class="woocommerce-button button">
					<?php esc_html_e( 'Renew', 'openprovider-woocommerce' ); ?>
				</button>
			</form>

			<p>
				<a class="woocommerce-button button" href="<?php echo esc_url( wc_get_account_endpoint_url( 'domain-dns' ) . $domain['id'] . '/' ); ?>">
					<?php esc_html_e( 'Manage DNS', 'openprovider-woocommerce' ); ?>
				</a>
			</p>
		</div>
	<?php endif; ?>

	<p>
		<a class="woocommerce-button button" href="<?php echo esc_url( wc_get_account_endpoint_url( 'my-domains' ) ); ?>">
			<?php esc_html_e( 'Back to My Domains', 'openprovider-woocommerce' ); ?>
		</a>
	</p>
</div>

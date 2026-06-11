<?php
/**
 * My Account → My Domains template
 *
 * @package OpenProviderWooCommerce
 *
 * @var array $domains Paginated domains: array{domains: array, total: int, pages: int}.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="opwc-my-domains">
	<h2><?php esc_html_e( 'My Domains', 'openprovider-woocommerce' ); ?></h2>

	<?php if ( empty( $domains['domains'] ) ) : ?>
		<p><?php esc_html_e( 'You have no registered domains.', 'openprovider-woocommerce' ); ?></p>
	<?php else : ?>
		<table class="opwc-domains-table shop_table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Domain', 'openprovider-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Status', 'openprovider-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Expiry Date', 'openprovider-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Auto-Renew', 'openprovider-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'openprovider-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $domains['domains'] as $domain ) : ?>
					<tr class="opwc-domain-row status-<?php echo esc_attr( $domain->status ); ?>">
						<td data-title="<?php esc_attr_e( 'Domain', 'openprovider-woocommerce' ); ?>">
							<?php echo esc_html( $domain->domain_name . '.' . $domain->tld ); ?>
						</td>
						<td data-title="<?php esc_attr_e( 'Status', 'openprovider-woocommerce' ); ?>">
							<span class="opwc-domain-status status-<?php echo esc_attr( $domain->status ); ?>">
								<?php echo esc_html( ucfirst( $domain->status ) ); ?>
							</span>
						</td>
						<td data-title="<?php esc_attr_e( 'Expiry Date', 'openprovider-woocommerce' ); ?>">
							<?php
							if ( $domain->expires_at ) :
								$expiry = strtotime( $domain->expires_at );
								$days   = (int) ceil( ( $expiry - time() ) / DAY_IN_SECONDS );
								echo esc_html( date_i18n( get_option( 'date_format' ), $expiry ) );
								if ( $days <= 30 ) :
									echo ' <small class="opwc-expiry-warning">(' . esc_html( $days ) . ' ' . esc_html__( 'days', 'openprovider-woocommerce' ) . ')</small>';
								endif;
							else :
								esc_html_e( 'N/A', 'openprovider-woocommerce' );
							endif;
							?>
						</td>
						<td data-title="<?php esc_attr_e( 'Auto-Renew', 'openprovider-woocommerce' ); ?>">
							<?php echo $domain->auto_renew ? esc_html__( 'Enabled', 'openprovider-woocommerce' ) : esc_html__( 'Disabled', 'openprovider-woocommerce' ); ?>
						</td>
						<td data-title="<?php esc_attr_e( 'Actions', 'openprovider-woocommerce' ); ?>">
							<a class="woocommerce-button button" href="<?php echo esc_url( wc_get_account_endpoint_url( 'domain-details' ) . $domain->id . '/' ); ?>">
								<?php esc_html_e( 'Details', 'openprovider-woocommerce' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $domains['pages'] > 1 ) : ?>
			<div class="opwc-pagination">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'    => trailingslashit( wc_get_account_endpoint_url( 'my-domains' ) ) . '%_%',
							'format'  => 'page/%#%/',
							'total'   => $domains['pages'],
							'current' => max( 1, (int) get_query_var( 'paged', 1 ) ),
						)
					)
				);
				?>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>

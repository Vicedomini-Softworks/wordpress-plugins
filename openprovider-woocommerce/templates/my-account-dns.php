<?php
/**
 * My Account → DNS Management template
 *
 * @package OpenProviderWooCommerce
 *
 * @var object $domain Domain record from DomainRepository.
 * @var array  $nameservers Nameservers data: {type: string, servers: array}.
 * @var array  $records DNS records.
 * @var array  $supported_types Supported DNS record types.
 * @var string $error Error message, if any.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="opwc-dns-manager" data-domain-id="<?php echo esc_attr( $domain->id ); ?>">
	<h2><?php echo esc_html( $domain->domain_name . '.' . $domain->tld ); ?></h2>

	<?php if ( $error ) : ?>
		<div class="opwc-dns-error notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
	<?php endif; ?>

	<div class="opwc-dns-section opwc-nameservers-section">
		<h3><?php esc_html_e( 'Nameservers', 'openprovider-woocommerce' ); ?></h3>

		<div class="opwc-nameserver-type">
			<label>
				<input type="radio" name="ns_type" value="default" <?php checked( $nameservers['type'], 'default' ); ?> />
				<?php esc_html_e( 'Use OpenProvider nameservers', 'openprovider-woocommerce' ); ?>
			</label>
			<label>
				<input type="radio" name="ns_type" value="custom" <?php checked( $nameservers['type'], 'custom' ); ?> />
				<?php esc_html_e( 'Use custom nameservers', 'openprovider-woocommerce' ); ?>
			</label>
		</div>

		<div class="opwc-custom-nameservers" style="display: <?php echo 'custom' === $nameservers['type'] ? 'block' : 'none'; ?>;">
			<?php
			$servers = ! empty( $nameservers['servers'] ) ? $nameservers['servers'] : array( '', '' );
			foreach ( $servers as $server ) :
				?>
				<input type="text" class="opwc-nameserver-input" value="<?php echo esc_attr( $server ); ?>" placeholder="ns1.example.com" />
			<?php endforeach; ?>
			<button type="button" class="opwc-add-nameserver-btn"><?php esc_html_e( 'Add Nameserver', 'openprovider-woocommerce' ); ?></button>
		</div>

		<button type="button" class="opwc-save-nameservers-btn woocommerce-button button"><?php esc_html_e( 'Save Nameservers', 'openprovider-woocommerce' ); ?></button>
		<span class="opwc-nameservers-status"></span>
	</div>

	<div class="opwc-dns-section opwc-records-section">
		<h3><?php esc_html_e( 'DNS Records', 'openprovider-woocommerce' ); ?></h3>

		<table class="opwc-dns-records-table shop_table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Type', 'openprovider-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Name', 'openprovider-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Value', 'openprovider-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'TTL', 'openprovider-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'openprovider-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody class="opwc-dns-records-list">
				<?php foreach ( $records as $record ) : ?>
					<tr data-record-id="<?php echo esc_attr( $record['id'] ); ?>">
						<td><span class="opwc-record-type"><?php echo esc_html( $record['type'] ); ?></span></td>
						<td><?php echo esc_html( $record['name'] ); ?></td>
						<td><?php echo esc_html( $record['value'] ); ?></td>
						<td><?php echo esc_html( $record['ttl'] ); ?></td>
						<td>
							<button type="button" class="opwc-edit-record-btn"><?php esc_html_e( 'Edit', 'openprovider-woocommerce' ); ?></button>
							<button type="button" class="opwc-delete-record-btn"><?php esc_html_e( 'Delete', 'openprovider-woocommerce' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<button type="button" class="opwc-add-record-btn woocommerce-button button"><?php esc_html_e( 'Add DNS Record', 'openprovider-woocommerce' ); ?></button>
	</div>

	<div class="opwc-dns-record-form" style="display: none;">
		<h3 class="opwc-dns-record-form-title"></h3>
		<input type="hidden" class="opwc-record-id" value="" />
		<p>
			<label for="opwc-record-type"><?php esc_html_e( 'Type', 'openprovider-woocommerce' ); ?></label>
			<select id="opwc-record-type" class="opwc-record-type-input">
				<?php foreach ( $supported_types as $type ) : ?>
					<?php if ( 'SOA' !== $type ) : ?>
						<option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $type ); ?></option>
					<?php endif; ?>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="opwc-record-name"><?php esc_html_e( 'Name', 'openprovider-woocommerce' ); ?></label>
			<input type="text" id="opwc-record-name" class="opwc-record-name-input" placeholder="@" />
		</p>
		<p>
			<label for="opwc-record-value"><?php esc_html_e( 'Value', 'openprovider-woocommerce' ); ?></label>
			<input type="text" id="opwc-record-value" class="opwc-record-value-input" />
		</p>
		<p class="opwc-record-priority-field" style="display: none;">
			<label for="opwc-record-priority"><?php esc_html_e( 'Priority', 'openprovider-woocommerce' ); ?></label>
			<input type="number" id="opwc-record-priority" class="opwc-record-priority-input" min="0" />
		</p>
		<p>
			<label for="opwc-record-ttl"><?php esc_html_e( 'TTL', 'openprovider-woocommerce' ); ?></label>
			<input type="number" id="opwc-record-ttl" class="opwc-record-ttl-input" min="60" value="3600" />
		</p>
		<p>
			<button type="button" class="opwc-save-record-btn woocommerce-button button"><?php esc_html_e( 'Save', 'openprovider-woocommerce' ); ?></button>
			<button type="button" class="opwc-cancel-record-btn"><?php esc_html_e( 'Cancel', 'openprovider-woocommerce' ); ?></button>
		</p>
	</div>

	<div class="opwc-propagation-warning notice">
		<p><?php esc_html_e( 'DNS changes may take up to 48 hours to propagate across the internet.', 'openprovider-woocommerce' ); ?></p>
	</div>

	<p>
		<a class="woocommerce-button button" href="<?php echo esc_url( wc_get_account_endpoint_url( 'domain-details' ) . $domain->id . '/' ); ?>">
			<?php esc_html_e( 'Back to Domain Details', 'openprovider-woocommerce' ); ?>
		</a>
	</p>
</div>

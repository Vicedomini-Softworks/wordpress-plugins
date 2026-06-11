<?php
/**
 * Customer Profile Fields class for OpenProvider WooCommerce
 *
 * Adds domain registration contact fields to user profiles.
 *
 * @package OpenProviderWooCommerce\WooCommerce
 */

namespace OpenProviderWooCommerce\WooCommerce;

/**
 * Customer Profile Fields class.
 */
class CustomerProfileFields {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		// Admin user profile.
		add_action( 'show_user_profile', array( $this, 'render_admin_profile_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'render_admin_profile_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_admin_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_admin_profile_fields' ) );

		// WooCommerce My Account.
		add_action( 'woocommerce_edit_account_form', array( $this, 'render_my_account_fields' ) );
		add_action( 'woocommerce_save_account_details', array( $this, 'save_my_account_fields' ) );
	}

	/**
	 * Render admin profile fields.
	 *
	 * @param \WP_User $user User object.
	 */
	public function render_admin_profile_fields( \WP_User $user ): void {
		?>
		<h3><?php esc_html_e( 'Domain Registration Contact Information', 'openprovider-woocommerce' ); ?></h3>
		<table class="form-table">
			<tr>
				<th><label for="opwc_phone"><?php esc_html_e( 'Phone (with country code)', 'openprovider-woocommerce' ); ?></label></th>
				<td>
					<input type="text" id="opwc_phone" name="opwc_phone" value="<?php echo esc_attr( get_user_meta( $user->ID, 'opwc_phone', true ) ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Example: +14155551234', 'openprovider-woocommerce' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="opwc_company_name"><?php esc_html_e( 'Company Name (optional)', 'openprovider-woocommerce' ); ?></label></th>
				<td>
					<input type="text" id="opwc_company_name" name="opwc_company_name" value="<?php echo esc_attr( get_user_meta( $user->ID, 'opwc_company_name', true ) ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Required for company registrations', 'openprovider-woocommerce' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="opwc_vat_number"><?php esc_html_e( 'VAT Number (optional)', 'openprovider-woocommerce' ); ?></label></th>
				<td>
					<input type="text" id="opwc_vat_number" name="opwc_vat_number" value="<?php echo esc_attr( get_user_meta( $user->ID, 'opwc_vat_number', true ) ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Required for .eu company registrations', 'openprovider-woocommerce' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="opwc_fiscal_code"><?php esc_html_e( 'Fiscal Code / Codice Fiscale', 'openprovider-woocommerce' ); ?></label></th>
				<td>
					<input type="text" id="opwc_fiscal_code" name="opwc_fiscal_code" value="<?php echo esc_attr( get_user_meta( $user->ID, 'opwc_fiscal_code', true ) ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Required for .it domain registrations', 'openprovider-woocommerce' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save admin profile fields.
	 *
	 * @param int $user_id User ID.
	 */
	public function save_admin_profile_fields( int $user_id ): void {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		if ( isset( $_POST['opwc_phone'] ) ) {
			update_user_meta( $user_id, 'opwc_phone', sanitize_text_field( wp_unslash( $_POST['opwc_phone'] ) ) );
		}
		if ( isset( $_POST['opwc_company_name'] ) ) {
			update_user_meta( $user_id, 'opwc_company_name', sanitize_text_field( wp_unslash( $_POST['opwc_company_name'] ) ) );
		}
		if ( isset( $_POST['opwc_vat_number'] ) ) {
			update_user_meta( $user_id, 'opwc_vat_number', sanitize_text_field( wp_unslash( $_POST['opwc_vat_number'] ) ) );
		}
		if ( isset( $_POST['opwc_fiscal_code'] ) ) {
			update_user_meta( $user_id, 'opwc_fiscal_code', sanitize_text_field( wp_unslash( $_POST['opwc_fiscal_code'] ) ) );
		}
	}

	/**
	 * Render My Account fields.
	 */
	public function render_my_account_fields(): void {
		$user_id = get_current_user_id();
		?>
		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="opwc_phone"><?php esc_html_e( 'Phone (with country code)', 'openprovider-woocommerce' ); ?></label>
			<input type="text" id="opwc_phone" name="opwc_phone" value="<?php echo esc_attr( get_user_meta( $user_id, 'opwc_phone', true ) ); ?>" class="woocommerce-Input woocommerce-Input--text input-text" />
		</p>
		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="opwc_company_name"><?php esc_html_e( 'Company Name (optional)', 'openprovider-woocommerce' ); ?></label>
			<input type="text" id="opwc_company_name" name="opwc_company_name" value="<?php echo esc_attr( get_user_meta( $user_id, 'opwc_company_name', true ) ); ?>" class="woocommerce-Input woocommerce-Input--text input-text" />
		</p>
		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="opwc_vat_number"><?php esc_html_e( 'VAT Number (optional)', 'openprovider-woocommerce' ); ?></label>
			<input type="text" id="opwc_vat_number" name="opwc_vat_number" value="<?php echo esc_attr( get_user_meta( $user_id, 'opwc_vat_number', true ) ); ?>" class="woocommerce-Input woocommerce-Input--text input-text" />
		</p>
		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="opwc_fiscal_code"><?php esc_html_e( 'Fiscal Code / Codice Fiscale', 'openprovider-woocommerce' ); ?></label>
			<input type="text" id="opwc_fiscal_code" name="opwc_fiscal_code" value="<?php echo esc_attr( get_user_meta( $user_id, 'opwc_fiscal_code', true ) ); ?>" class="woocommerce-Input woocommerce-Input--text input-text" />
		</p>
		<?php
	}

	/**
	 * Save My Account fields.
	 *
	 * @param int $user_id User ID.
	 */
	public function save_my_account_fields( int $user_id ): void {
		if ( isset( $_POST['opwc_phone'] ) ) {
			update_user_meta( $user_id, 'opwc_phone', sanitize_text_field( wp_unslash( $_POST['opwc_phone'] ) ) );
		}
		if ( isset( $_POST['opwc_company_name'] ) ) {
			update_user_meta( $user_id, 'opwc_company_name', sanitize_text_field( wp_unslash( $_POST['opwc_company_name'] ) ) );
		}
		if ( isset( $_POST['opwc_vat_number'] ) ) {
			update_user_meta( $user_id, 'opwc_vat_number', sanitize_text_field( wp_unslash( $_POST['opwc_vat_number'] ) ) );
		}
		if ( isset( $_POST['opwc_fiscal_code'] ) ) {
			update_user_meta( $user_id, 'opwc_fiscal_code', sanitize_text_field( wp_unslash( $_POST['opwc_fiscal_code'] ) ) );
		}
	}

	/**
	 * Get missing required fields for a TLD.
	 *
	 * @param int    $user_id User ID.
	 * @param string $tld TLD.
	 * @return array Array of missing field names.
	 */
	public function missing_fields( int $user_id, string $tld ): array {
		$missing = array();

		$phone = get_user_meta( $user_id, 'opwc_phone', true );
		if ( empty( $phone ) ) {
			$missing[] = 'opwc_phone';
		}

		$tld = strtolower( $tld );

		if ( 'it' === $tld ) {
			$fiscal_code = get_user_meta( $user_id, 'opwc_fiscal_code', true );
			$company_name = get_user_meta( $user_id, 'opwc_company_name', true );

			if ( empty( $fiscal_code ) && empty( $company_name ) ) {
				$missing[] = 'opwc_fiscal_code_or_company';
			}
		} elseif ( 'eu' === $tld ) {
			$company_name = get_user_meta( $user_id, 'opwc_company_name', true );
			$vat_number = get_user_meta( $user_id, 'opwc_vat_number', true );

			// For .eu, at least one of these should be present for company registrations.
			// But individuals can register .eu domains too, so we don't enforce this strictly.
			// Only require if user has indicated they're a company.
		}

		return $missing;
	}

	/**
	 * Get field label for error messages.
	 *
	 * @param string $field Field name.
	 * @return string Field label.
	 */
	public function get_field_label( string $field ): string {
		$labels = array(
			'opwc_phone' => __( 'Phone with country code', 'openprovider-woocommerce' ),
			'opwc_company_name' => __( 'Company name', 'openprovider-woocommerce' ),
			'opwc_vat_number' => __( 'VAT number', 'openprovider-woocommerce' ),
			'opwc_fiscal_code' => __( 'Fiscal code (Codice Fiscale)', 'openprovider-woocommerce' ),
			'opwc_fiscal_code_or_company' => __( 'Fiscal code or company name', 'openprovider-woocommerce' ),
		);

		return $labels[ $field ] ?? $field;
	}
}

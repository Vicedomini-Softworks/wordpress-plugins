<?php
/**
 * Contact Data Resolver class for OpenProvider WooCommerce
 *
 * Resolves OpenProvider contact handles and TLD-specific additional data
 * for a WooCommerce customer.
 *
 * @package OpenProviderWooCommerce\WooCommerce
 */

namespace OpenProviderWooCommerce\WooCommerce;

/**
 * Contact Data Resolver class.
 */
class ContactDataResolver {

	/**
	 * Get contact data for a customer.
	 *
	 * @param int    $customer_id Customer ID.
	 * @param string $tld TLD.
	 * @return array{owner_handle: string, admin_handle: string, tech_handle: string, billing_handle: string, additional_data: array}
	 */
	public function get_contact_data( int $customer_id, string $tld ): array {
		$user = get_user_by( 'id', $customer_id );

		$email       = $user->user_email ?? '';
		$company     = get_user_meta( $customer_id, 'opwc_company_name', true );
		$fiscal_code = get_user_meta( $customer_id, 'opwc_fiscal_code', true );
		$vat_number  = get_user_meta( $customer_id, 'opwc_vat_number', true );

		// For v1, we'll use a simple approach: create a contact handle based on email.
		// In production, you'd want to create/reuse proper OpenProvider contact handles.
		$handle = 'opwc_' . md5( $email . $customer_id );

		$additional_data = array();

		// TLD-specific additional data.
		if ( 'it' === strtolower( $tld ) ) {
			if ( $company ) {
				$additional_data['it'] = array(
					'entity_type'         => 'company',
					'identification_code' => '' !== $vat_number ? $vat_number : $fiscal_code,
				);
			} elseif ( $fiscal_code ) {
				$additional_data['it'] = array(
					'entity_type'         => 'individual',
					'identification_code' => $fiscal_code,
				);
			}
		} elseif ( 'eu' === strtolower( $tld ) ) {
			if ( $vat_number ) {
				$additional_data['eu'] = array(
					'vat_number' => $vat_number,
				);
			}
		}

		return array(
			'owner_handle'    => $handle,
			'admin_handle'    => $handle,
			'tech_handle'     => $handle,
			'billing_handle'  => $handle,
			'additional_data' => $additional_data,
		);
	}
}

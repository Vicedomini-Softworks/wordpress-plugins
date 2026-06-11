/**
 * OpenProvider WooCommerce - Domain Transfer JavaScript
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		const $form = $('.opwc-transfer-search');
		const $domainInput = $form.find('.opwc-transfer-domain');
		const $checkBtn = $form.find('.opwc-transfer-check-btn');
		const $result = $form.find('.opwc-transfer-result');
		const $price = $result.find('.opwc-transfer-price');
		const $authCodeWrap = $result.find('.opwc-transfer-auth-code-input');
		const $authCode = $result.find('.opwc-auth-code');
		const $addCartBtn = $result.find('.opwc-transfer-add-cart');
		const $error = $form.find('.opwc-transfer-result-error');

		let currentDomain = '';
		let currentTld = '';

		$checkBtn.on('click', checkTransfer);

		$domainInput.on('keypress', function(e) {
			if (e.which === 13) {
				e.preventDefault();
				checkTransfer();
			}
		});

		function parseDomain(value) {
			const trimmed = value.trim().toLowerCase();
			const parts = trimmed.split('.');

			if (parts.length < 2) {
				return null;
			}

			const tld = parts.pop();
			const name = parts.join('.');

			if (!/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/.test(name) || !/^[a-z]{2,}$/.test(tld)) {
				return null;
			}

			return { name: name, tld: tld };
		}

		function checkTransfer() {
			const parsed = parseDomain($domainInput.val());

			$result.hide();
			$error.hide().empty();
			$authCodeWrap.hide();
			$addCartBtn.hide();

			if (!parsed) {
				$error.text(opwcTransfer.i18n.invalidDomain).show();
				return;
			}

			currentDomain = parsed.name;
			currentTld = parsed.tld;

			$checkBtn.prop('disabled', true).text(opwcTransfer.i18n.checking);

			$.ajax({
				url: opwcTransfer.checkUrl,
				method: 'GET',
				data: {
					domain_name: currentDomain,
					tld: currentTld
				},
				headers: {
					'X-WP-Nonce': opwcTransfer.nonce
				},
				success: function(response) {
					if (!response.available) {
						$error.text(opwcTransfer.i18n.notEligible).show();
						return;
					}

					$price.text(response.currency + ' ' + response.price.toFixed(2));

					if (response.requires_auth_code) {
						$authCodeWrap.show();
					}

					$addCartBtn.show();
					$result.show();
				},
				error: function(xhr) {
					const message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : opwcTransfer.i18n.error;
					$error.text(message).show();
				},
				complete: function() {
					$checkBtn.prop('disabled', false).text(opwcTransfer.i18n.checkTransfer);
				}
			});
		}

		$addCartBtn.on('click', function() {
			$addCartBtn.prop('disabled', true);

			$.ajax({
				url: opwcTransfer.cartUrl,
				method: 'POST',
				contentType: 'application/json',
				data: JSON.stringify({
					domain_name: currentDomain,
					tld: currentTld,
					auth_code: $authCode.val().trim()
				}),
				headers: {
					'X-WP-Nonce': opwcTransfer.nonce
				},
				success: function(response) {
					if (response.success) {
						$(document.body).trigger('update_cart_ajax');
						$addCartBtn.text(opwcTransfer.i18n.addedToCart);
					}
				},
				error: function(xhr) {
					const message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : opwcTransfer.i18n.error;
					$error.text(message).show();
					$addCartBtn.prop('disabled', false);
				}
			});
		});
	});
})(jQuery);

/**
 * OpenProvider WooCommerce - Domain Search JavaScript
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		const $searchForm = $('.opwc-domain-search');
		const $domainInput = $searchForm.find('.opwc-domain-input');
		const $tldSelect = $searchForm.find('.opwc-tld-select');
		const $periodSelect = $searchForm.find('.opwc-period-select');
		const $searchBtn = $searchForm.find('.opwc-search-btn');
		const $resultsContainer = $searchForm.find('.opwc-search-results');

		// Search on button click
		$searchBtn.on('click', performSearch);

		// Search on Enter key
		$domainInput.on('keypress', function(e) {
			if (e.which === 13) {
				e.preventDefault();
				performSearch();
			}
		});

		function performSearch() {
			const domainName = $domainInput.val().trim().toLowerCase();
			const tld = $tldSelect.val().toLowerCase();

			if (!domainName) {
				alert(opwcSearch.i18n.error);
				return;
			}

			// Validate domain name
			if (!/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/.test(domainName)) {
				alert(opwcSearch.i18n.error);
				return;
			}

			// Show loading state
			$searchForm.addClass('opwc-searching');
			$searchBtn.prop('disabled', true);
			$resultsContainer.hide().empty();

			// Fetch period
			const period = parseInt($periodSelect.val()) || 1;

			// Make API request
			$.ajax({
				url: opwcSearch.restUrl,
				method: 'GET',
				data: {
					query: domainName,
					tlds: [tld],
					period: period
				},
				headers: {
					'X-WP-Nonce': opwcSearch.nonce
				},
				success: function(response) {
					displayResults(response.domains || []);
				},
				error: function(xhr) {
					const message = xhr.responseJSON?.message || opwcSearch.i18n.error;
					alert(message);
				},
				complete: function() {
					$searchForm.removeClass('opwc-searching');
					$searchBtn.prop('disabled', false);
				}
			});
		}

		function displayResults(domains) {
			$resultsContainer.empty();

			if (!domains || domains.length === 0) {
				$resultsContainer.html('<p>' + opwcSearch.i18n.error + '</p>').show();
				return;
			}

			domains.forEach(function(domain) {
				const $item = $('<div class="opwc-result-item"></div>');
				const domainFull = domain.name + '.' + domain.tld;

				if (domain.available) {
					$item.addClass('available');

					let priceHtml = '';
					if (domain.price) {
						priceHtml = '<span class="opwc-result-price">' +
							domain.currency + ' ' + domain.price.toFixed(2) +
							'</span>';
					}

					let premiumBadge = '';
					if (domain.premium) {
						premiumBadge = '<span class="opwc-result-premium">' +
							opwcSearch.i18n.premium + '</span>';
					}

					$item.html(
						'<span class="opwc-result-domain">' +
							domainFull + premiumBadge +
						'</span>' +
						'<span class="opwc-result-status">' +
							priceHtml +
							'<button type="button" class="opwc-add-to-cart-btn" ' +
								'data-domain="' + domain.name + '" ' +
								'data-tld="' + domain.tld + '">' +
								opwcSearch.i18n.addToIntCart +
							'</button>' +
						'</span>'
					);

					// Add to cart handler
					$item.find('.opwc-add-to-cart-btn').on('click', function() {
						const $btn = $(this);
						$btn.prop('disabled', true).text(opwcSearch.i18n.addedToCart);

						const period = parseInt($searchForm.find('.opwc-period-select').val()) || 1;

						$.ajax({
							url: opwcSearch.cartUrl,
							method: 'POST',
							contentType: 'application/json',
							data: JSON.stringify({
								domain_name: domain.name,
								tld: domain.tld,
								registration_period: period
							}),
							headers: {
								'X-WP-Nonce': opwcSearch.nonce
							},
							success: function(response) {
								if (response.success) {
									// Trigger cart fragment update
									$(document.body).trigger('update_cart_ajax');
									setTimeout(function() {
										$btn.text(opwcSearch.i18n.addedToCart);
									}, 500);
								}
							},
							error: function(xhr) {
								const message = xhr.responseJSON?.message || opwcSearch.i18n.error;
								alert(message);
								$btn.prop('disabled', false).text(opwcSearch.i18n.addToIntCart);
							}
						});
					});
				} else {
					$item.addClass('unavailable');
					$item.html(
						'<span class="opwc-result-domain">' + domainFull + '</span>' +
						'<span class="opwc-result-status">' +
							'<span class="opwc-result-unavailable">' +
								opwcSearch.i18n.unavailable +
							'</span>' +
						'</span>'
					);
				}

				$resultsContainer.append($item);
			});

			$resultsContainer.show();
		}
	});
})(jQuery);

(function ($) {
	'use strict';

	$(function () {
		$('.vs-reveal-btn').on('click', function () {
			var $btn = $(this);
			var name = $btn.data('name');
			var $valueSpan = $btn.siblings('.vs-revealed-value');

			if ($valueSpan.is(':visible')) {
				$valueSpan.hide();
				$btn.text('Reveal');
				return;
			}

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'vs_secrets_manager_reveal',
					name: name,
					nonce: vsSecretsManager.ajaxNonce
				},
				success: function (response) {
					if (response.success) {
						$valueSpan.text(response.data.value).show();
						$btn.text('Hide');
					} else {
						alert(response.data.message || 'Error revealing secret.');
					}
				},
				error: function () {
					alert('Request failed.');
				}
			});
		});

		$('#vs-secret-form').on('submit', function (e) {
			e.preventDefault();

			var data = {
				name: $('#vs-secret-name').val(),
				title: $('#vs-secret-title').val(),
				value: $('#vs-secret-value').val(),
				provider: $('#vs-secret-provider').val()
			};

			if (!data.name || !data.value) {
				alert('Name and value are required.');
				return;
			}

			$.ajax({
				url: vsSecretsManager.restUrl + '/secrets',
				type: 'POST',
				beforeSend: function (xhr) {
					xhr.setRequestHeader('X-WP-Nonce', vsSecretsManager.nonce);
				},
				data: JSON.stringify(data),
				contentType: 'application/json',
				success: function (response) {
					window.location.href = 'admin.php?page=vs-secrets-manager';
				},
				error: function (xhr) {
					var msg = 'Error saving secret.';
					if (xhr.responseJSON && xhr.responseJSON.message) {
						msg = xhr.responseJSON.message;
					}
					alert(msg);
				}
			});
		});
	});

})(jQuery);

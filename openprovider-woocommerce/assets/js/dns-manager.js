/**
 * OpenProvider WooCommerce - DNS Manager JavaScript
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		const $manager = $('.opwc-dns-manager');

		if (!$manager.length || typeof opwcDns === 'undefined') {
			return;
		}

		const domainId = opwcDns.domainId;
		const PRIORITY_TYPES = ['MX', 'SRV'];

		/**
		 * Nameserver type toggle.
		 */
		const $customNsWrap = $manager.find('.opwc-custom-nameservers');

		$manager.on('change', 'input[name="ns_type"]', function() {
			$customNsWrap.toggle($(this).val() === 'custom');
		});

		$manager.on('click', '.opwc-add-nameserver-btn', function() {
			const $input = $('<input type="text" class="opwc-nameserver-input" placeholder="ns.example.com" />');
			$(this).before($input);
		});

		$manager.on('click', '.opwc-save-nameservers-btn', function() {
			const $btn = $(this);
			const $status = $manager.find('.opwc-nameservers-status');
			const nsType = $manager.find('input[name="ns_type"]:checked').val();

			$btn.prop('disabled', true);
			$status.text(opwcDns.i18n.saving);

			const payload = nsType === 'default'
				? { reset: true }
				: {
					reset: false,
					nameservers: $manager.find('.opwc-nameserver-input').map(function() {
						return $(this).val().trim();
					}).get().filter(Boolean)
				};

			$.ajax({
				url: opwcDns.restUrl + '/domain/' + domainId + '/nameservers',
				method: 'PUT',
				contentType: 'application/json',
				data: JSON.stringify(payload),
				headers: {
					'X-WP-Nonce': opwcDns.nonce
				},
				success: function() {
					$status.text(opwcDns.i18n.saved);
				},
				error: function(xhr) {
					const message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : opwcDns.i18n.error;
					$status.text(message);
				},
				complete: function() {
					$btn.prop('disabled', false);
				}
			});
		});

		/**
		 * DNS record form.
		 */
		const $form = $manager.find('.opwc-dns-record-form');
		const $formTitle = $form.find('.opwc-dns-record-form-title');
		const $recordId = $form.find('.opwc-record-id');
		const $typeInput = $form.find('.opwc-record-type-input');
		const $nameInput = $form.find('.opwc-record-name-input');
		const $valueInput = $form.find('.opwc-record-value-input');
		const $priorityField = $form.find('.opwc-record-priority-field');
		const $priorityInput = $form.find('.opwc-record-priority-input');
		const $ttlInput = $form.find('.opwc-record-ttl-input');

		function togglePriorityField() {
			$priorityField.toggle(PRIORITY_TYPES.indexOf($typeInput.val()) !== -1);
		}

		$typeInput.on('change', togglePriorityField);

		function openForm(record) {
			if (record) {
				$formTitle.text(opwcDns.i18n.editRecord);
				$recordId.val(record.id);
				$typeInput.val(record.type);
				$nameInput.val(record.name);
				$valueInput.val(record.value);
				$priorityInput.val(record.priority || '');
				$ttlInput.val(record.ttl || 3600);
			} else {
				$formTitle.text(opwcDns.i18n.addRecord);
				$recordId.val('');
				$typeInput.prop('selectedIndex', 0);
				$nameInput.val('');
				$valueInput.val('');
				$priorityInput.val('');
				$ttlInput.val(3600);
			}

			togglePriorityField();
			$form.show();
		}

		function closeForm() {
			$form.hide();
		}

		$manager.on('click', '.opwc-add-record-btn', function() {
			openForm(null);
		});

		$manager.on('click', '.opwc-cancel-record-btn', function() {
			closeForm();
		});

		$manager.on('click', '.opwc-edit-record-btn', function() {
			const $row = $(this).closest('tr');

			openForm({
				id: $row.data('record-id'),
				type: $row.find('.opwc-record-type').text().trim(),
				name: $row.find('td').eq(1).text().trim(),
				value: $row.find('td').eq(2).text().trim(),
				ttl: $row.find('td').eq(3).text().trim(),
				priority: ''
			});
		});

		$manager.on('click', '.opwc-save-record-btn', function() {
			const $btn = $(this);
			const recordId = $recordId.val();

			const payload = {
				domain_id: domainId,
				type: $typeInput.val(),
				name: $nameInput.val().trim(),
				value: $valueInput.val().trim(),
				ttl: parseInt($ttlInput.val(), 10) || 3600
			};

			if (PRIORITY_TYPES.indexOf(payload.type) !== -1 && $priorityInput.val() !== '') {
				payload.priority = parseInt($priorityInput.val(), 10);
			}

			const url = recordId
				? opwcDns.restUrl + '/dns/record/' + recordId
				: opwcDns.restUrl + '/dns/record';

			$btn.prop('disabled', true);

			$.ajax({
				url: url,
				method: recordId ? 'PUT' : 'POST',
				contentType: 'application/json',
				data: JSON.stringify(payload),
				headers: {
					'X-WP-Nonce': opwcDns.nonce
				},
				success: function() {
					window.location.reload();
				},
				error: function(xhr) {
					const message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : opwcDns.i18n.error;
					alert(message);
				},
				complete: function() {
					$btn.prop('disabled', false);
				}
			});
		});

		$manager.on('click', '.opwc-delete-record-btn', function() {
			if (!window.confirm(opwcDns.i18n.confirmDelete)) {
				return;
			}

			const $row = $(this).closest('tr');
			const recordId = $row.data('record-id');

			$.ajax({
				url: opwcDns.restUrl + '/dns/record/' + recordId + '?domain_id=' + domainId,
				method: 'DELETE',
				headers: {
					'X-WP-Nonce': opwcDns.nonce
				},
				success: function() {
					$row.remove();
				},
				error: function(xhr) {
					const message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : opwcDns.i18n.error;
					alert(message);
				}
			});
		});
	});
})(jQuery);

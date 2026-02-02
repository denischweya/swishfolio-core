/**
 * Swish Forms Admin Scripts
 *
 * @package SwishfolioCore
 */

(function($) {
	'use strict';

	// Entry details modal.
	$(document).on('click', '.swish-forms-view-details', function(e) {
		e.preventDefault();

		var data = $(this).data('data');
		var $modal = $('#swish-forms-entry-modal');
		var $details = $('#swish-forms-entry-details');

		if (typeof data === 'string') {
			try {
				data = JSON.parse(data);
			} catch (e) {
				data = {};
			}
		}

		// Build details table.
		var html = '<table>';
		if (data && typeof data === 'object') {
			for (var key in data) {
				if (data.hasOwnProperty(key)) {
					var value = data[key];
					if (typeof value === 'object') {
						value = JSON.stringify(value);
					}
					html += '<tr><th>' + escapeHtml(key) + '</th><td>' + escapeHtml(value) + '</td></tr>';
				}
			}
		}
		html += '</table>';

		$details.html(html);
		$modal.show();
	});

	// Close modal.
	$(document).on('click', '.swish-forms-modal-close', function() {
		$('.swish-forms-modal').hide();
	});

	$(document).on('click', '.swish-forms-modal', function(e) {
		if (e.target === this) {
			$(this).hide();
		}
	});

	// Test connection button.
	$(document).on('click', '.swish-forms-test-connection', function() {
		var $button = $(this);
		var provider = $button.data('provider');
		var $result = $('#' + provider + '-test-result');

		$button.prop('disabled', true).text(swishFormsAdmin.i18n.testing || 'Testing...');
		$result.removeClass('success error').text('');

		$.ajax({
			url: swishFormsAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'swish_forms_test_connection',
				provider: provider,
				nonce: swishFormsAdmin.nonce
			},
			success: function(response) {
				if (response.success) {
					$result.addClass('success').text(swishFormsAdmin.i18n.testSuccess);
				} else {
					$result.addClass('error').text(response.data.message || swishFormsAdmin.i18n.testFailed);
				}
			},
			error: function() {
				$result.addClass('error').text(swishFormsAdmin.i18n.testFailed);
			},
			complete: function() {
				$button.prop('disabled', false).text('Test Connection');
			}
		});
	});

	// Fetch lists button.
	$(document).on('click', '.swish-forms-fetch-lists', function() {
		var $button = $(this);
		var provider = $button.data('provider');
		var $result = $('#' + provider + '-lists-result');

		$button.prop('disabled', true).text('Fetching...');
		$result.html('');

		$.ajax({
			url: swishFormsAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'swish_forms_fetch_lists',
				provider: provider,
				nonce: swishFormsAdmin.nonce
			},
			success: function(response) {
				if (response.success && response.data.lists) {
					var html = '<p><strong>Available Lists:</strong></p><ul>';
					response.data.lists.forEach(function(list) {
						html += '<li><code>' + escapeHtml(list.id) + '</code> - ' + escapeHtml(list.name) + '</li>';
					});
					html += '</ul>';
					$result.html(html);
				} else {
					$result.html('<p class="error">' + (response.data.message || 'Failed to fetch lists.') + '</p>');
				}
			},
			error: function() {
				$result.html('<p class="error">Failed to fetch lists.</p>');
			},
			complete: function() {
				$button.prop('disabled', false).text('Fetch Lists');
			}
		});
	});

	// Send test email button.
	$(document).on('click', '#send_test_email', function() {
		var $button = $(this);
		var email = $('#test_email_address').val();
		var $result = $('#test_email_result');

		if (!email) {
			$result.addClass('error').text('Please enter an email address.');
			return;
		}

		$button.prop('disabled', true).text('Sending...');
		$result.removeClass('success error').text('');

		$.ajax({
			url: swishFormsAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'swish_forms_send_test_email',
				email: email,
				nonce: swishFormsAdmin.nonce
			},
			success: function(response) {
				if (response.success) {
					$result.addClass('success').text(swishFormsAdmin.i18n.emailSent);
				} else {
					$result.addClass('error').text(response.data.message || swishFormsAdmin.i18n.emailFailed);
				}
			},
			error: function() {
				$result.addClass('error').text(swishFormsAdmin.i18n.emailFailed);
			},
			complete: function() {
				$button.prop('disabled', false).text('Send Test Email');
			}
		});
	});

	// Bulk action confirmation.
	$('#swish-forms-entries-form').on('submit', function(e) {
		var action = $('select[name="action2"]').val();
		var checked = $('input[name="entry_ids[]"]:checked').length;

		if (action === 'delete' && checked > 0) {
			if (!confirm(swishFormsAdmin.i18n.confirmBulk)) {
				e.preventDefault();
			}
		}
	});

	// Select all checkboxes.
	$('#cb-select-all').on('change', function() {
		$('input[name="entry_ids[]"]').prop('checked', $(this).is(':checked'));
	});

	// Helper function to escape HTML.
	function escapeHtml(text) {
		if (text === null || text === undefined) {
			return '';
		}
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(String(text)));
		return div.innerHTML;
	}

})(jQuery);

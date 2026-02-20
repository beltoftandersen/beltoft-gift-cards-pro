/**
 * Smart Gift Cards Pro - Admin JavaScript
 *
 * NOTE: License activate/deactivate is handled by inline <script> in
 * SettingsPage::render_license_tab() — do NOT duplicate those handlers here.
 */
/* global jQuery, wcgc_pro_params */

(function ($) {
	'use strict';

	/* ── Bulk Generation ─────────────────────── */

	$(document).on('click', '#wcgc-pro-bulk-generate-btn', function (e) {
		e.preventDefault();
		var $btn = $(this);
		var $progress = $('.wcgc-pro-bulk-progress');

		$btn.prop('disabled', true);
		$progress.show();

		$.post(wcgc_pro_params.ajax_url, {
			action: 'wcgc_pro_bulk_generate',
			nonce: wcgc_pro_params.nonce,
			quantity: $('#wcgc-pro-bulk-quantity').val(),
			amount: $('#wcgc-pro-bulk-amount').val(),
			prefix: $('#wcgc-pro-bulk-prefix').val(),
			expiry_days: $('#wcgc-pro-bulk-expiry').val(),
			recipient_email: $('#wcgc-pro-bulk-email').val(),
			recipient_name: $('#wcgc-pro-bulk-name').val()
		}, function (response) {
			$btn.prop('disabled', false);
			if (response.success) {
				$('.progress-bar-fill').css('width', '100%');
				$('.progress-text').text(response.data.message);
				setTimeout(function () { location.reload(); }, 2000);
			} else {
				$('.progress-text').text(response.data.message || 'Generation failed.');
			}
		}).fail(function () {
			$btn.prop('disabled', false);
			$('.progress-text').text('Request failed.');
		});
	});

	/* ── CSV Export ───────────────────────────── */

	$(document).on('click', '#wcgc-pro-export-csv-btn', function (e) {
		e.preventDefault();
		var status = $('#wcgc-pro-export-status').val() || '';
		window.location.href = wcgc_pro_params.ajax_url +
			'?action=wcgc_pro_export_csv&nonce=' + wcgc_pro_params.nonce +
			'&status=' + status;
	});

	/* ── CSV Import ──────────────────────────── */

	$(document).on('click', '#wcgc-pro-import-csv-btn', function (e) {
		e.preventDefault();
		var $btn = $(this);
		var $file = $('#wcgc-pro-import-file')[0];
		var $result = $('#wcgc-pro-import-result');

		if (!$file.files.length) {
			$result.text('Please select a CSV file.').show();
			return;
		}

		var formData = new FormData();
		formData.append('action', 'wcgc_pro_import_csv');
		formData.append('nonce', wcgc_pro_params.nonce);
		formData.append('csv_file', $file.files[0]);

		$btn.prop('disabled', true);
		$result.text('Importing...').show();

		$.ajax({
			url: wcgc_pro_params.ajax_url,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function (response) {
				$btn.prop('disabled', false);
				if (response.success) {
					$result.text(response.data.message);
				} else {
					$result.text(response.data.message || 'Import failed.');
				}
			},
			error: function () {
				$btn.prop('disabled', false);
				$result.text('Request failed.');
			}
		});
	});

	/* ── BOGO Rule Form Toggle ───────────────── */

	$(document).on('click', '#wcgc-pro-add-bogo-btn', function (e) {
		e.preventDefault();
		$('.wcgc-pro-bogo-form').toggle();
	});

	$(document).on('click', '#wcgc-pro-save-bogo-btn', function (e) {
		e.preventDefault();
		var $btn = $(this);

		$btn.prop('disabled', true);

		$.post(wcgc_pro_params.ajax_url, {
			action: 'wcgc_pro_save_bogo_rule',
			nonce: wcgc_pro_params.nonce,
			id: $('#wcgc-pro-bogo-id').val(),
			name: $('#wcgc-pro-bogo-name').val(),
			buy_amount: $('#wcgc-pro-bogo-buy').val(),
			get_amount: $('#wcgc-pro-bogo-get').val(),
			min_quantity: $('#wcgc-pro-bogo-min-qty').val(),
			max_uses: $('#wcgc-pro-bogo-max-uses').val(),
			starts_at: $('#wcgc-pro-bogo-starts').val(),
			ends_at: $('#wcgc-pro-bogo-ends').val()
		}, function (response) {
			$btn.prop('disabled', false);
			if (response.success) {
				location.reload();
			} else {
				/* translators: not used in PHP — JS alert only */
				alert(response.data.message || 'Save failed.');
			}
		}).fail(function () {
			$btn.prop('disabled', false);
		});
	});

	$(document).on('click', '.wcgc-pro-delete-bogo', function (e) {
		e.preventDefault();
		if (!confirm('Delete this rule?')) {
			return;
		}

		var ruleId = $(this).data('id');

		$.post(wcgc_pro_params.ajax_url, {
			action: 'wcgc_pro_delete_bogo_rule',
			nonce: wcgc_pro_params.nonce,
			id: ruleId
		}, function (response) {
			if (response.success) {
				location.reload();
			}
		});
	});

	/* ── Theme Picker (frontend) ─────────────── */

	$(document).on('change', '.wcgc-pro-theme-option input[type="radio"]', function () {
		$('.wcgc-pro-theme-option').removeClass('selected');
		$(this).closest('.wcgc-pro-theme-option').addClass('selected');
	});

})(jQuery);

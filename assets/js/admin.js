/**
 * Beltoft Gift Cards Pro - Admin JavaScript
 *
 * NOTE: License activate/deactivate is handled by inline <script> in
 * SettingsPage::render_license_tab() — do NOT duplicate those handlers here.
 */
/* global jQuery, bgcw_pro_params */

(function ($) {
	'use strict';

	/* ── Bulk Generation ─────────────────────── */

	$(document).on('click', '#bgcw-pro-bulk-generate-btn', function (e) {
		e.preventDefault();
		var $btn = $(this);
		var $progress = $('.bgcw-pro-bulk-progress');

		$btn.prop('disabled', true);
		$progress.show();

		$.post(bgcw_pro_params.ajax_url, {
			action: 'bgcw_pro_bulk_generate',
			nonce: bgcw_pro_params.nonce,
			quantity: $('#bgcw-pro-bulk-quantity').val(),
			amount: $('#bgcw-pro-bulk-amount').val(),
			prefix: $('#bgcw-pro-bulk-prefix').val(),
			expiry_days: $('#bgcw-pro-bulk-expiry').val(),
			source: $('#bgcw-pro-bulk-source').val(),
			recipient_email: $('#bgcw-pro-bulk-email').val(),
			recipient_name: $('#bgcw-pro-bulk-name').val()
		}, function (response) {
			$btn.prop('disabled', false);
			if (response.success) {
				$('.progress-bar-fill').css('width', '100%');
				$('.progress-text').text(response.data.message);
				setTimeout(function () { location.reload(); }, 2000);
			} else {
				$('.progress-text').text(response.data.message || bgcw_pro_params.i18n.generation_failed);
			}
		}).fail(function () {
			$btn.prop('disabled', false);
			$('.progress-text').text(bgcw_pro_params.i18n.request_failed);
		});
	});

	/* ── CSV Export ───────────────────────────── */

	$(document).on('click', '#bgcw-pro-export-csv-btn', function (e) {
		e.preventDefault();
		var status = $('#bgcw-pro-export-status').val() || '';
		window.location.href = bgcw_pro_params.ajax_url +
			'?action=bgcw_pro_export_csv&nonce=' + bgcw_pro_params.nonce +
			'&status=' + status;
	});

	/* ── CSV Import ──────────────────────────── */

	$(document).on('click', '#bgcw-pro-import-csv-btn', function (e) {
		e.preventDefault();
		var $btn = $(this);
		var $file = $('#bgcw-pro-import-file')[0];
		var $result = $('#bgcw-pro-import-result');

		if (!$file.files.length) {
			$result.text(bgcw_pro_params.i18n.select_csv).show();
			return;
		}

		var formData = new FormData();
		formData.append('action', 'bgcw_pro_import_csv');
		formData.append('nonce', bgcw_pro_params.nonce);
		formData.append('csv_file', $file.files[0]);

		$btn.prop('disabled', true);
		$result.text(bgcw_pro_params.i18n.importing).show();

		$.ajax({
			url: bgcw_pro_params.ajax_url,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function (response) {
				$btn.prop('disabled', false);
				if (response.success) {
					$result.text(response.data.message);
				} else {
					$result.text(response.data.message || bgcw_pro_params.i18n.import_failed);
				}
			},
			error: function () {
				$btn.prop('disabled', false);
				$result.text(bgcw_pro_params.i18n.request_failed);
			}
		});
	});

	/* ── BOGO Rule Form Toggle ───────────────── */

	$(document).on('click', '#bgcw-pro-add-bogo-btn', function (e) {
		e.preventDefault();
		$('.bgcw-pro-bogo-form').toggle();
	});

	$(document).on('click', '#bgcw-pro-save-bogo-btn', function (e) {
		e.preventDefault();
		var $btn = $(this);

		$btn.prop('disabled', true);

		$.post(bgcw_pro_params.ajax_url, {
			action: 'bgcw_pro_save_bogo_rule',
			nonce: bgcw_pro_params.nonce,
			id: $('#bgcw-pro-bogo-id').val(),
			name: $('#bgcw-pro-bogo-name').val(),
			buy_amount: $('#bgcw-pro-bogo-buy').val(),
			get_amount: $('#bgcw-pro-bogo-get').val(),
			min_quantity: $('#bgcw-pro-bogo-min-qty').val(),
			max_uses: $('#bgcw-pro-bogo-max-uses').val(),
			starts_at: $('#bgcw-pro-bogo-starts').val(),
			ends_at: $('#bgcw-pro-bogo-ends').val()
		}, function (response) {
			$btn.prop('disabled', false);
			if (response.success) {
				location.reload();
			} else {
				/* translators: not used in PHP — JS alert only */
				alert(response.data.message || bgcw_pro_params.i18n.save_failed);
			}
		}).fail(function () {
			$btn.prop('disabled', false);
		});
	});

	$(document).on('click', '.bgcw-pro-delete-bogo', function (e) {
		e.preventDefault();
		if (!confirm(bgcw_pro_params.i18n.confirm_delete_rule)) {
			return;
		}

		var ruleId = $(this).data('id');

		$.post(bgcw_pro_params.ajax_url, {
			action: 'bgcw_pro_delete_bogo_rule',
			nonce: bgcw_pro_params.nonce,
			id: ruleId
		}, function (response) {
			if (response.success) {
				location.reload();
			}
		});
	});

	/* ── Report Frequency Toggle ─────────────── */

	$(document).on('change', '#bgcw-pro-report-frequency', function () {
		var freq = $(this).val();
		$('#bgcw-pro-report-day-of-week').closest('tr').toggle(freq === 'weekly');
		$('#bgcw-pro-report-day-of-month').closest('tr').toggle(freq === 'monthly');
	});
	$('#bgcw-pro-report-frequency').trigger('change');

})(jQuery);

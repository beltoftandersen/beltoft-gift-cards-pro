/**
 * Beltoft Gift Cards Pro - License tab (activate / deactivate).
 */
jQuery(function ($) {
	'use strict';

	var p = window.bgcw_pro_license;
	if (!p) {
		return;
	}
	var $msg = $('#bgcw-pro-license-message');

	function show(text, ok) {
		$msg.text(text).css('color', ok ? '#00a32a' : '#d63638');
	}

	$('#bgcw-pro-activate-license').on('click', function () {
		var key = $.trim($('#bgcw-pro-license-key').val());
		if (!key) {
			return;
		}
		var $btn = $(this).prop('disabled', true).text(p.i18n.activating);
		$msg.text('');
		$.post(ajaxurl, { action: 'bgcw_pro_activate_license', nonce: p.nonce, license_key: key })
			.done(function (r) {
				if (r && r.success) {
					show(r.data.message, true);
					setTimeout(function () { location.reload(); }, 1000);
				} else {
					show(r && r.data && r.data.message ? r.data.message : p.i18n.activation_failed, false);
					$btn.prop('disabled', false).text(p.i18n.activate);
				}
			})
			.fail(function () {
				show(p.i18n.request_failed, false);
				$btn.prop('disabled', false).text(p.i18n.activate);
			});
	});

	$('#bgcw-pro-deactivate-license').on('click', function () {
		if (!window.confirm(p.i18n.confirm_deactivate)) {
			return;
		}
		var $btn = $(this).prop('disabled', true).text(p.i18n.deactivating);
		$msg.text('');
		$.post(ajaxurl, { action: 'bgcw_pro_deactivate_license', nonce: p.nonce })
			.done(function (r) {
				if (r && r.success) {
					show(r.data.message, true);
					setTimeout(function () { location.reload(); }, 500);
				} else {
					show(r && r.data && r.data.message ? r.data.message : p.i18n.deactivation_failed, false);
					$btn.prop('disabled', false).text(p.i18n.deactivate);
				}
			})
			.fail(function () {
				show(p.i18n.request_failed, false);
				$btn.prop('disabled', false).text(p.i18n.deactivate);
			});
	});
});

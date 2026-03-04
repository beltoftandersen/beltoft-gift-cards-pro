/**
 * Beltoft Gift Cards Pro - Frontend JavaScript
 */
(function () {
	'use strict';

	/* ── Theme Picker ───────────────────────────── */

	document.addEventListener('change', function (e) {
		if (!e.target.matches('.bgcw-theme-option input[type="radio"]')) {
			return;
		}
		document.querySelectorAll('.bgcw-theme-option').forEach(function (el) {
			el.classList.remove('selected');
		});
		e.target.closest('.bgcw-theme-option').classList.add('selected');
	});

	/* ── Delivery Date: enable hour picker ──────── */

	var dateInput = document.getElementById('bgcw_delivery_date');
	var hourSelect = document.getElementById('bgcw_delivery_hour');

	if (dateInput && hourSelect) {
		dateInput.addEventListener('change', function () {
			if (this.value) {
				hourSelect.disabled = false;
			} else {
				hourSelect.disabled = true;
				hourSelect.value = '';
			}
		});
	}
})();

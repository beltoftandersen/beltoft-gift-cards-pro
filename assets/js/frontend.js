/**
 * Smart Gift Cards Pro - Frontend JavaScript
 */
(function () {
	'use strict';

	/* ── Theme Picker ───────────────────────────── */

	document.addEventListener('change', function (e) {
		if (!e.target.matches('.wcgc-theme-option input[type="radio"]')) {
			return;
		}
		document.querySelectorAll('.wcgc-theme-option').forEach(function (el) {
			el.classList.remove('selected');
		});
		e.target.closest('.wcgc-theme-option').classList.add('selected');
	});
})();

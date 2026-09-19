/**
 * Beltoft Gift Cards Pro - Frontend JavaScript
 */
(function () {
	'use strict';

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

	/* ── Live card preview ──────────────────────── */

	var root = document.querySelector('[data-bgcw-preview]');
	var params = window.bgcw_pro_pdf || null;
	if (!root || !params) {
		return;
	}

	var card = root.querySelector('.bgcw-card');
	var stage = root.querySelector('.bgcw-pro-preview__stage');
	var scaler = root.querySelector('.bgcw-pro-preview__scale');
	if (!card || !stage || !scaler) {
		return;
	}

	function field(name) {
		return card.querySelector('[data-bgcw-field="' + name + '"]');
	}

	function formatNumber(value) {
		var c = params.currency;
		var decimals = parseInt(c.decimals, 10) || 0;
		var fixed = value.toFixed(decimals);
		var parts = fixed.split('.');
		var intPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, c.thousand_sep);
		var dec = parts[1] || '';
		if (dec && /^0+$/.test(dec)) {
			dec = '';
		}
		return dec ? intPart + c.decimal_sep + dec : intPart;
	}

	function sizeClass(number) {
		var len = number.length;
		return len <= 5 ? 'xl' : (len <= 8 ? 'lg' : 'md');
	}

	function updateAmount() {
		var hidden = document.getElementById('bgcw_amount');
		var amountEl = field('amount');
		var numberEl = field('number');
		if (!amountEl || !numberEl) {
			return;
		}
		var raw = hidden ? parseFloat(String(hidden.value).replace(',', '.')) : NaN;
		var number = isNaN(raw) || raw <= 0 ? '—' : formatNumber(raw);
		numberEl.textContent = number;
		amountEl.className = 'bgcw-card__amount bgcw-card__amount--' + sizeClass(number);
	}

	function updateText(name, inputId, placeholder) {
		var el = field(name);
		var input = document.getElementById(inputId);
		if (!el) {
			return;
		}
		var value = input ? input.value.trim() : '';
		// Keep in step with the 220/217 truncation in templates/pdf/_card.php.
		if (name === 'message' && value.length > 220) {
			value = value.slice(0, 217) + '…';
		}
		el.textContent = value || placeholder;
	}

	function updateDesign() {
		var checked = root.querySelector('input[name="bgcw_design_theme"]:checked');
		if (!checked) {
			return;
		}
		var slug = checked.value;
		card.className = card.className.replace(/\bbgcw-card--[a-z0-9_-]+/g, '').replace(/\s+/g, ' ').trim() + ' bgcw-card--' + slug;
		card.setAttribute('data-design', slug);
		var heading = field('heading');
		if (heading && params.headings[slug]) {
			heading.textContent = params.headings[slug];
		}
		var labels = root.querySelectorAll('.bgcw-pro-design');
		for (var i = 0; i < labels.length; i++) {
			labels[i].classList.toggle('is-selected', labels[i].contains(checked));
		}
	}

	function updateAll() {
		updateAmount();
		updateText('recipient_name', 'bgcw_recipient_name', params.placeholders.recipient_name);
		updateText('message', 'bgcw_message', params.placeholders.message);
		updateDesign();
	}

	function rescale() {
		var width = stage.clientWidth;
		if (!width) {
			return;
		}
		var scale = Math.min(1, width / params.card_width);
		scaler.style.transform = 'scale(' + scale + ')';
		stage.style.height = Math.round(params.card_height * scale) + 'px';
	}

	// The free plugin sets #bgcw_amount from these controls without firing events,
	// so listen at document level (runs after its handlers) and re-read the value.
	document.addEventListener('click', function (e) {
		if (e.target.closest && e.target.closest('.bgcw-amount-btn')) {
			setTimeout(updateAmount, 0);
		}
	});
	document.addEventListener('input', function (e) {
		var id = e.target.id;
		if (id === 'bgcw_custom_amount') {
			setTimeout(updateAmount, 0);
		} else if (id === 'bgcw_recipient_name' || id === 'bgcw_message') {
			updateAll();
		}
	});
	document.addEventListener('change', function (e) {
		if (e.target.id === 'bgcw_amount_dropdown' || e.target.id === 'bgcw_custom_amount') {
			setTimeout(updateAmount, 0);
		} else if (e.target.name === 'bgcw_design_theme') {
			updateDesign();
		}
	});

	window.addEventListener('resize', rescale);
	if (document.fonts && document.fonts.ready) {
		document.fonts.ready.then(rescale);
	}

	rescale();
	updateAll();
	setTimeout(updateAll, 100);
})();

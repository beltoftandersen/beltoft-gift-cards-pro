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
		var max = parseInt(params.message_max, 10) || 160;
		if (name === 'message' && value.length > max) {
			value = value.slice(0, max - 3) + '…';
		}
		if (name === 'message') {
			el.textContent = value ? '\u201C' + value + '\u201D' : '';
			el.style.display = value ? '' : 'none';
			return;
		}
		el.textContent = value || placeholder;
	}

	function updateDesign() {
		var checked = root.querySelector('input[name="bgcw_design_theme"]:checked');
		if (!checked) {
			return;
		}
		var slug = checked.value;
		card.className = card.className.replace(/\bbgcw-card--(?!product\b)[a-z0-9_-]+/g, '').replace(/\s+/g, ' ').trim() + ' bgcw-card--' + slug;
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

	var lightbox = root.querySelector('.bgcw-pro-lightbox');
	var openBtn = root.querySelector('.bgcw-pro-preview__open');
	var lastFocus = null;

	function rescale() {
		if (!lightbox || lightbox.hidden) {
			return;
		}
		var panel = lightbox.querySelector('.bgcw-pro-lightbox__panel');
		var note = lightbox.querySelector('.bgcw-pro-lightbox__note');
		var chrome = 48 + (note ? note.offsetHeight + 12 : 0);
		var availW = panel.clientWidth - 32;
		var availH = window.innerHeight * 0.9 - chrome;
		var scale = Math.min(1, availW / params.card_width, availH / params.card_height);
		if (!(scale > 0)) {
			return;
		}
		scaler.style.transform = 'scale(' + scale + ')';
		stage.style.width = Math.round(params.card_width * scale) + 'px';
		stage.style.height = Math.round(params.card_height * scale) + 'px';
	}

	function openLightbox() {
		if (!lightbox) {
			return;
		}
		lastFocus = document.activeElement;
		lightbox.hidden = false;
		document.body.classList.add('bgcw-pro-lightbox-open');
		updateAll();
		rescale();
		var close = lightbox.querySelector('.bgcw-pro-lightbox__close');
		if (close) {
			close.focus();
		}
	}

	function closeLightbox() {
		if (!lightbox || lightbox.hidden) {
			return;
		}
		lightbox.hidden = true;
		document.body.classList.remove('bgcw-pro-lightbox-open');
		if (lastFocus && lastFocus.focus) {
			lastFocus.focus();
		}
	}

	if (openBtn) {
		openBtn.addEventListener('click', openLightbox);
	}
	if (lightbox) {
		lightbox.addEventListener('click', function (e) {
			if (e.target.closest('[data-bgcw-close]')) {
				closeLightbox();
			}
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				closeLightbox();
			}
		});
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

	updateAll();
	setTimeout(updateAll, 100);
})();

/* ── Give as a gift toggle ──────────────────── */
(function () {
	'use strict';
	var gift = document.querySelector('[data-bgcw-gift]');
	if (!gift) {
		return;
	}
	var toggle = gift.querySelector('#bgcw_gift');
	var fields = gift.querySelector('.bgcw-pro-gift__fields');
	var form = gift.closest('form.cart');
	var button = form ? form.querySelector('.single_add_to_cart_button') : null;
	var originalText = button ? button.textContent : '';
	var params = window.bgcw_pro_gift || {};

	function apply() {
		var on = toggle.checked;
		fields.hidden = !on;
		gift.classList.toggle('is-on', on);
		var required = fields.querySelectorAll('[data-bgcw-required]');
		for (var i = 0; i < required.length; i++) {
			required[i].required = on;
		}
		if (button) {
			button.textContent = on && params.gift_button_text ? params.gift_button_text : originalText;
			if (on) {
				// A gift is locked to the parent product; the recipient picks options later.
				button.classList.remove('disabled', 'wc-variation-selection-needed', 'wc-variation-is-unavailable');
				button.removeAttribute('disabled');
			}
		}
	}

	toggle.addEventListener('change', apply);
	if (form && window.jQuery) {
		window.jQuery(form).on('woocommerce_variation_has_changed hide_variation reset_data', function () {
			if (toggle.checked) {
				setTimeout(apply, 0);
			}
		});
	}
	apply();
})();

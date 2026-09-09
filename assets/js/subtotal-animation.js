(function () {
	'use strict';

	function parseAmount(text) {
		var match = String(text || '').replace(/,/g, '').match(/-?\d+(?:\.\d+)?/);
		return match ? Number(match[0]) : 0;
	}

	function initSubtotalAnimation() {
		var subtotal = document.querySelector('[data-qs-subtotal]');
		if (!subtotal || typeof MutationObserver === 'undefined') {
			return;
		}

		var formatter = new Intl.NumberFormat('en-AU', {
			minimumFractionDigits: 2,
			maximumFractionDigits: 2
		});
		var frame = 0;
		var currentValue = parseAmount(subtotal.textContent);
		var reducedMotion = window.matchMedia &&
			window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		var observer;

		function render(value) {
			currentValue = Number(value) || 0;
			observer.disconnect();
			subtotal.textContent = '$' + formatter.format(currentValue) + ' AUD';
			observer.observe(subtotal, {
				childList: true,
				characterData: true,
				subtree: true
			});
		}

		function animateTo(target) {
			target = Number(target);
			if (!Number.isFinite(target)) {
				return;
			}

			if (frame) {
				window.cancelAnimationFrame(frame);
				frame = 0;
			}

			var start = currentValue;
			if (reducedMotion || Math.abs(target - start) < 0.005) {
				render(target);
				return;
			}

			var duration = 500;
			var started = performance.now();
			var difference = target - start;

			function tick(now) {
				var progress = Math.min(1, (now - started) / duration);
				var eased = 1 - Math.pow(1 - progress, 3);
				render(start + (difference * eased));

				if (progress < 1) {
					frame = window.requestAnimationFrame(tick);
				} else {
					frame = 0;
					render(target);
				}
			}

			frame = window.requestAnimationFrame(tick);
		}

		observer = new MutationObserver(function () {
			var target = parseAmount(subtotal.textContent);
			if (Math.abs(target - currentValue) < 0.005) {
				return;
			}

			animateTo(target);
		});

		observer.observe(subtotal, {
			childList: true,
			characterData: true,
			subtree: true
		});
	}

	/**
	 * Client feedback helpers for Quote Builder item defaults.
	 *
	 * The main item configuration script builds these selects dynamically, so
	 * this runs after its DOMContentLoaded setup has finished.
	 */
	function initBuilderSelectionDefaults() {
		var form = document.querySelector('.qs-builder-form');
		if (!form) return;

		function configField(root, key) {
			return root ? root.querySelector('[data-item-config-field="' + key + '"]') : null;
		}

		function configFrom(root) {
			return {
				timber: configField(root, 'timber') ? configField(root, 'timber').value : '',
				finish: configField(root, 'finish') ? configField(root, 'finish').value : '',
				paint_colour: configField(root, 'paint_colour') ? configField(root, 'paint_colour').value : ''
			};
		}

		function applyConfig(root, values) {
			if (!root || !values) return;
			['timber', 'finish', 'paint_colour'].forEach(function (key) {
				var input = configField(root, key);
				if (input) input.value = values[key] || '';
			});

			// Keep Painted Oak behaviour in sync when timber is carried forward.
			var timber = configField(root, 'timber');
			var finish = configField(root, 'finish');
			var paint = configField(root, 'paint_colour');
			var timberLabel = timber && timber.selectedOptions && timber.selectedOptions[0]
				? timber.selectedOptions[0].textContent
				: '';
			var painted = /paint/i.test(timberLabel || '');
			if (paint) {
				paint.hidden = !painted;
				paint.disabled = !painted;
				if (!painted) paint.value = '';
			}
			if (finish) {
				finish.hidden = painted;
				finish.disabled = painted;
				if (painted) finish.value = '';
			}
		}

		function componentIsEditing(section) {
			if (!section) return false;
			var value = section.dataset.editingIndex;
			return value !== undefined && value !== '';
		}

		function carryToComponents(values, sourceSection) {
			form.querySelectorAll('.qs-configured-component').forEach(function (section) {
				if (section === sourceSection || componentIsEditing(section)) return;
				applyConfig(section.querySelector('.qs-component-editor'), values);
			});
		}

		// A brand-new quote must start with headings/placeholders rather than
		// silently selecting the first Profile/Timber/Handle/Finish option.
		var params = new URLSearchParams(window.location.search);
		var isFreshQuote = !params.get('quote_id') && !form.querySelector('.qs-repeater-row');
		if (isFreshQuote) {
			form.querySelectorAll('.qs-item-editor, .qs-component-editor').forEach(function (root) {
				['door_profile', 'timber', 'handle_profile', 'finish', 'paint_colour'].forEach(function (key) {
					var input = configField(root, key);
					if (input) input.value = '';
				});
				var paint = configField(root, 'paint_colour');
				if (paint) {
					paint.hidden = true;
					paint.disabled = true;
				}
				var finish = configField(root, 'finish');
				if (finish) {
					finish.hidden = false;
					finish.disabled = false;
				}
			});
		}

		// The latest Timber/Finish selection becomes the default for downstream
		// End Panels, Fillers and Kickboards. Existing saved rows are untouched;
		// only the visible editor for the next item is updated.
		form.addEventListener('change', function (event) {
			if (!event.target.matches('[data-item-config-field="timber"], [data-item-config-field="finish"], [data-item-config-field="paint_colour"]')) {
				return;
			}

			var root = event.target.closest('.qs-item-editor, .qs-component-editor');
			if (!root) return;

			window.setTimeout(function () {
				var values = configFrom(root);
				var sourceSection = root.closest('.qs-configured-component');
				carryToComponents(values, sourceSection);
			}, 0);
		});

		// Builder should show the same End Panel guidance already shown on the
		// Review page and quotation PDF.
		var endPanels = form.querySelector('.qs-end-panels');
		if (endPanels && !endPanels.querySelector('.qs-flat-panel-note')) {
			var heading = endPanels.querySelector('h3');
			var note = document.createElement('p');
			note.className = 'qs-grain-note qs-flat-panel-note';
			note.textContent = 'Flat panels / no profile';
			if (heading) heading.insertAdjacentElement('afterend', note);
			else endPanels.insertBefore(note, endPanels.firstChild);
		}
	}

	function init() {
		initSubtotalAnimation();
		window.setTimeout(initBuilderSelectionDefaults, 0);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());

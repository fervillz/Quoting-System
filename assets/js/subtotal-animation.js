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

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initSubtotalAnimation);
	} else {
		initSubtotalAnimation();
	}
}());

/** Preview-only box model overlay, following animated elements as in WP Swiper. */
(function () {
	'use strict';
	if (!document.documentElement.hasAttribute('data-top-info-bar-preview')) return;
	let enabled = false;
	let layer = null;
	let frame = 0;
	let previous = '';
	const sides = ['top', 'right', 'bottom', 'left'];
	const round = value => Math.round(value * 10) / 10;
	const number = value => parseFloat(value) || 0;

	function rect(left, top, width, height) {
		return {left, top, width: Math.max(0, width), height: Math.max(0, height)};
	}

	function box(fragment, type, bounds, label, target, side, value) {
		if (bounds.width < 0.1 || bounds.height < 0.1) return;
		const node = document.createElement('div');
		node.className = 'wp-top-info-bar-dev__' + type;
		node.dataset.target = target;
		if (side) node.dataset.side = side;
		if (value !== undefined) node.dataset.value = round(value);
		Object.assign(node.style, {
			left: bounds.left + 'px', top: bounds.top + 'px',
			width: bounds.width + 'px', height: bounds.height + 'px'
		});
		if (label) {
			const text = document.createElement('span');
			text.className = 'wp-top-info-bar-dev__label';
			text.textContent = label;
			node.appendChild(text);
		}
		fragment.appendChild(node);
	}

	function measurements() {
		return Array.from(document.querySelectorAll('.wp-top-info-bar, .wp-top-info-bar__inner, .wp-top-info-bar__image')).filter(el => {
			return el.getClientRects().length && getComputedStyle(el).display !== 'none';
		}).map((el, index) => {
			const bounds = el.getBoundingClientRect();
			const css = getComputedStyle(el);
			const padding = sides.map(side => number(css.getPropertyValue('padding-' + side)));
			const margin = sides.map(side => number(css.getPropertyValue('margin-' + side)));
			const border = sides.map(side => number(css.getPropertyValue('border-' + side + '-width')));
			const target = el.classList.contains('wp-top-info-bar') ? 'bar' : el.classList.contains('wp-top-info-bar__inner') ? 'text' : 'image-' + index;
			return {bounds, padding, margin, border, target};
		});
	}

	function draw(items) {
		const fragment = document.createDocumentFragment();
		items.forEach(({bounds: b, padding: p, margin: m, border: edge, target}) => {
			const inner = rect(b.left + edge[3], b.top + edge[0], b.width - edge[1] - edge[3], b.height - edge[0] - edge[2]);
			const bands = [
				rect(inner.left, inner.top, inner.width, p[0]),
				rect(inner.left + inner.width - p[1], inner.top + p[0], p[1], inner.height - p[0] - p[2]),
				rect(inner.left, inner.top + inner.height - p[2], inner.width, p[2]),
				rect(inner.left, inner.top + p[0], p[3], inner.height - p[0] - p[2])
			];
			bands.forEach((band, index) => {
				if (p[index] > 0) box(fragment, 'padding', band, 'P ' + round(p[index]) + 'px', target, sides[index], p[index]);
			});
			m.forEach((value, index) => {
				if (!value) return;
				const size = Math.abs(value);
				// Positive margins sit outside the border box. Negative margins
				// are hatched inward, with their signed value, rather than hidden.
				const bands = [
					rect(b.left, value > 0 ? b.top - size : b.top, b.width, size),
					rect(value > 0 ? b.right : b.right - size, b.top, size, b.height),
					rect(b.left, value > 0 ? b.bottom : b.bottom - size, b.width, size),
					rect(value > 0 ? b.left - size : b.left, b.top, size, b.height)
				];
				box(fragment, value < 0 ? 'negative' : 'margin', bands[index], 'M ' + round(value) + 'px', target, sides[index], value);
			});
			box(fragment, 'bounds', b, target + ' ' + round(b.width) + ' × ' + round(b.height) + 'px', target);
		});
		layer.replaceChildren(fragment);
	}

	function tick() {
		frame = 0;
		if (!enabled || document.hidden) return;
		const items = measurements();
		const signature = JSON.stringify(items.map(item => [
			item.target, ...['left', 'top', 'width', 'height'].map(key => round(item.bounds[key])),
			...item.padding, ...item.margin, ...item.border
		]));
		if (signature !== previous) {
			previous = signature;
			draw(items);
		}
		frame = requestAnimationFrame(tick);
	}

	window.wpTopInfoBarDev = function (on) {
		const next = !!on;
		if (next === enabled) return;
		enabled = next;
		if (enabled) {
			layer = document.createElement('div');
			layer.className = 'wp-top-info-bar-dev';
			layer.setAttribute('aria-hidden', 'true');
			document.body.appendChild(layer);
			previous = '';
			tick();
		} else {
			cancelAnimationFrame(frame);
			frame = 0;
			if (layer) layer.remove();
			layer = null;
			previous = '';
		}
	};
	document.addEventListener('visibilitychange', function () {
		if (enabled && !document.hidden && !frame) tick();
	});
})();

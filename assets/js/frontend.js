/** Move each image once between the bar's existing groups at device breakpoints. */
(function () {
	'use strict';
	const tablet = window.matchMedia('(max-width: 1024px)');
	const mobile = window.matchMedia('(max-width: 767px)');
	function positionImages() {
		document.querySelectorAll('.wp-top-info-bar').forEach(function (bar) {
			const groups = {
				left: bar.querySelector('.wp-top-info-bar__group--left'),
				right: bar.querySelector('.wp-top-info-bar__group--right'),
				'center-top': bar.querySelector('.wp-top-info-bar__center-images--top'),
				'center-bottom': bar.querySelector('.wp-top-info-bar__center-images--bottom')
			};
			const images = Array.from(bar.querySelectorAll('.wp-top-info-bar__image[data-position]'));
			images.sort((a, b) => Number(a.dataset.imageOrder) - Number(b.dataset.imageOrder));
			images.forEach(function (image) {
				let position = image.dataset.position || 'left';
				if (tablet.matches) position = image.dataset.positionTablet || position;
				if (mobile.matches) position = image.dataset.positionMobile || position;
				const group = groups[position] || groups.left;
				if (!group) return;
				// Insert in source order, including when an earlier image joins
				// a group that already contains a later image on this device.
				const next = Array.from(group.children).find(node =>
					node !== image && node.matches('.wp-top-info-bar__image') && Number(node.dataset.imageOrder) > Number(image.dataset.imageOrder));
				if (image.parentNode !== group || (next && image.nextElementSibling !== next)) {
					const button = group.querySelector('.wp-top-info-bar__button--text-left, .wp-top-info-bar__button--screen-right');
					group.insertBefore(image, next || button || null);
				}
			});
		});
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', positionImages, {once: true});
	else positionImages();
	tablet.addEventListener('change', positionImages);
	mobile.addEventListener('change', positionImages);
})();

/** Resolve each device from the larger screen without duplicating the bar. */
(function () {
 'use strict';
 function update() {
  document.querySelectorAll('[data-animation-devices]').forEach(function (track) {
   const config = JSON.parse(track.dataset.animationDevices);
   const width = window.innerWidth;
   let resolved = {animation:'none', delay:'0s', speed:60};
   ['desktop', 'tablet', 'mobile'].forEach(function (device) {
    if (device === 'tablet' && width > 1024 || device === 'mobile' && width > 767) return;
    const own = config[device] || {};
    Object.keys(resolved).forEach(key => { if (own[key] !== '' && own[key] != null) resolved[key] = own[key]; });
   });
   if (track.dataset.animationEnabled !== '1') resolved.animation = 'none';
   const inners = track.querySelectorAll('.wp-top-info-bar__inner');
   const targetContent = resolved.animation === 'none' ? '' : resolved.animation;
   Array.from(track.classList).filter(name => name.startsWith('wp-top-info-bar__content--')).forEach(name => {
    if (name !== 'wp-top-info-bar__content--ordered' && name !== 'wp-top-info-bar__content--' + targetContent) track.classList.remove(name);
   });
   if (targetContent) track.classList.add('wp-top-info-bar__content--' + targetContent);
   inners.forEach(function (inner) {
    const effect = resolved.animation === 'marquee' ? 'marquee' : '';
    Array.from(inner.classList).filter(name => name.startsWith('wp-top-info-bar__inner--')).forEach(name => {
     if (name !== 'wp-top-info-bar__inner--' + effect) inner.classList.remove(name);
    });
    if (effect) inner.classList.add('wp-top-info-bar__inner--' + effect);
    inner.style.setProperty('--wp-top-info-bar-text-delay', resolved.delay);
   });
   track.style.setProperty('--wp-top-info-bar-text-delay', resolved.delay);
   track.style.setProperty('--wp-top-info-bar-marquee-speed', resolved.speed);
  });
 }
 if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', update, {once:true});
 else update();
 window.addEventListener('resize', update);
})();
/** Keep the scrolling speed constant as the text and available width change. */
(function () {
 'use strict';
 function init() {
  document.querySelectorAll('[data-animation-devices]').forEach(function (text) {
   const viewport = text.parentElement;
   function update() {
    const speed = Math.max(1, parseFloat(getComputedStyle(text).getPropertyValue('--wp-top-info-bar-marquee-speed')) || 60);
    text.style.setProperty('--wp-top-info-bar-marquee-start', (viewport.clientWidth - parseFloat(getComputedStyle(viewport).paddingLeft || 0) - parseFloat(getComputedStyle(viewport).paddingRight || 0)) + 'px');
    text.style.setProperty('--wp-top-info-bar-marquee-duration', (viewport.clientWidth + text.scrollWidth) / speed + 's');
   }
   update();
   if (window.ResizeObserver) { const observer = new ResizeObserver(update); observer.observe(viewport); observer.observe(text); }
   else window.addEventListener('resize', update);
   if (document.fonts) document.fonts.ready.then(update);
  });
 }
 if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, {once:true});
 else init();
})();

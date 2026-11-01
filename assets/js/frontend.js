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

/** Resolve responsive global and zone animations without moving static zones. */
(function () {
 'use strict';
 const states = new WeakMap(), motion = window.matchMedia('(prefers-reduced-motion: reduce)');
 let queued = false;
 function deviceValue(values, key, fallback) {
  let value = values[key] == null || values[key] === '' ? fallback : values[key];
  if (window.innerWidth <= 1024 && values[key + '_tablet'] !== '' && values[key + '_tablet'] != null) value = values[key + '_tablet'];
  if (window.innerWidth <= 767 && values[key + '_mobile'] !== '' && values[key + '_mobile'] != null) value = values[key + '_mobile'];
  return value;
 }
 function time(value) {
  const text = String(value || '0').trim();
  return Math.max(0, (parseFloat(text) || 0) * (/s$/i.test(text) && !/ms$/i.test(text) ? 1000 : 1));
 }
 function textEffect(container, effect) {
  container.querySelectorAll('.wp-top-info-bar__inner').forEach(function (inner) {
   Array.from(inner.classList).filter(name => name.startsWith('wp-top-info-bar__inner--')).forEach(name => inner.classList.remove(name));
   if (effect === 'marquee') inner.classList.add('wp-top-info-bar__inner--marquee');
  });
 }
 function animateZone(entry, epoch, sharedSpan, sharedEnd) {
  const {zone, node, effect, delay, speed, mode} = entry;
  zone.classList.toggle('wp-top-info-bar__layout-zone--static', mode === 'none');
  zone.classList.toggle('wp-top-info-bar__layout-zone--marquee', effect === 'marquee');
  textEffect(node, effect);
  const style = getComputedStyle(zone);
  const width = Math.max(0, zone.clientWidth - parseFloat(style.paddingLeft || 0) - parseFloat(style.paddingRight || 0));
  const start = mode === 'inherit' ? sharedSpan : width;
  const end = mode === 'inherit' ? sharedEnd : node.scrollWidth;
  const signature = JSON.stringify([effect,delay,speed,width,node.scrollWidth,sharedSpan,sharedEnd,motion.matches]);
  let state = states.get(node);
  if (state && state.signature === signature) return;
  if (state && state.animation) state.animation.cancel();
  state = {signature:signature,animation:null}; states.set(node,state);
  if (effect === 'none' || motion.matches || !node.animate) return;
  let frames = [{opacity:0},{opacity:1}], duration=900, iterations=1, easing='ease-out';
  switch (effect) {
   case 'slide-up': frames=[{opacity:0,transform:'translateY(18px)'},{opacity:1,transform:'translateY(0)'}];break;
   case 'from-left': frames=[{opacity:0,transform:'translateX(-24px)'},{opacity:1,transform:'translateX(0)'}];break;
   case 'from-right': frames=[{opacity:0,transform:'translateX(24px)'},{opacity:1,transform:'translateX(0)'}];break;
   case 'page-left': frames=[{transform:'translateX(-100vw)'},{transform:'translateX(0)'}];duration=2000;break;
   case 'page-right': frames=[{transform:'translateX(100vw)'},{transform:'translateX(0)'}];duration=2000;break;
   case 'blink': frames=[{opacity:1},{opacity:.15},{opacity:1}];duration=1200;iterations=Infinity;easing='ease-in-out';break;
   case 'scale-pulse': frames=[{transform:'scale(1)'},{transform:'scale(1.08)'},{transform:'scale(1)'}];duration=1600;iterations=Infinity;easing='ease-in-out';break;
   case 'marquee': frames=[{transform:'translateX('+start+'px)'},{transform:'translateX(-'+end+'px)'}];duration=Math.max(1,(start+end)/speed*1000);iterations=Infinity;easing='linear';break;
  }
  state.animation=node.animate(frames,{duration:duration,delay:time(delay),iterations:iterations,easing:easing,fill:'both'});
  // Inherited zones use the same clock; their initial positioning stays intact.
  if (mode === 'inherit') state.animation.startTime=epoch;
 }
 function update() {
  document.querySelectorAll('[data-animation-devices]').forEach(function (track) {
   const config=JSON.parse(track.dataset.animationDevices), resolved={animation:'none',delay:'0s',speed:60};
   ['desktop','tablet','mobile'].forEach(function (device) {
    if (device==='tablet' && window.innerWidth>1024 || device==='mobile' && window.innerWidth>767) return;
    const own=config[device]||{};
    Object.keys(resolved).forEach(key=>{if(own[key]!==''&&own[key]!=null)resolved[key]=own[key]});
   });
   if(track.dataset.animationEnabled!=='1')resolved.animation='none';
   const entries=Array.from(track.querySelectorAll('[data-zone-animation]')).map(function(zone){
    const own=JSON.parse(zone.dataset.zoneAnimation||'{}'),mode=deviceValue(own,'animation_mode','inherit');
    const center=zone.classList.contains('wp-top-info-bar__layout-zone--center');
    const configuredWidth=deviceValue(own,'width',center?'fill':'auto');
    const width=center&&configuredWidth==='auto'?'fill':configuredWidth;
    zone.style.width=width==='auto'?'max-content':'100%';
    return {zone:zone,node:zone.querySelector('.wp-top-info-bar__zone-track'),mode:mode,width:width,
     effect:mode==='none'?'none':mode==='custom'?deviceValue(own,'animation','fade-in'):resolved.animation,
     delay:mode==='custom'?deviceValue(own,'animation_delay','0s'):resolved.delay,
     speed:Math.max(1,Number(mode==='custom'?deviceValue(own,'animation_speed',60):resolved.speed)||60)};
   }).filter(entry=>entry.node);
   if(entries.length)track.style.gridTemplateColumns=entries.map(entry=>entry.width==='auto'?'max-content':entry.width==='fill'?'minmax(0,1fr)':entry.width).join(' ');
   const split=entries.some(entry=>entry.mode!=='inherit');
   Array.from(track.classList).filter(name=>name.startsWith('wp-top-info-bar__content--')&&name!=='wp-top-info-bar__content--ordered').forEach(name=>{
    const target=split?'wp-top-info-bar__content--zone-animation':'wp-top-info-bar__content--'+resolved.animation;
    if(name!==target)track.classList.remove(name);
   });
   track.style.setProperty('--wp-top-info-bar-text-delay',resolved.delay);
   track.style.setProperty('--wp-top-info-bar-marquee-speed',resolved.speed);
   if(!split){
    if(resolved.animation!=='none')track.classList.add('wp-top-info-bar__content--'+resolved.animation);
    entries.forEach(function(entry){const state=states.get(entry.node);if(state&&state.animation)state.animation.cancel();states.delete(entry.node);entry.zone.classList.remove('wp-top-info-bar__layout-zone--static','wp-top-info-bar__layout-zone--marquee')});
    textEffect(track,resolved.animation);
    return;
   }
   track.classList.add('wp-top-info-bar__content--zone-animation');
   let state=states.get(track),key=JSON.stringify(resolved);
   if(!state||state.key!==key){state={key:key,epoch:document.timeline.currentTime||performance.now()};states.set(track,state)}
   const inherited=entries.filter(entry=>entry.mode==='inherit'),widths=inherited.map(entry=>entry.zone.getBoundingClientRect());
   const span=widths.length?Math.max(...widths.map(rect=>rect.right))-Math.min(...widths.map(rect=>rect.left)):0;
   const sharedEnd=Math.max(0,...inherited.map(entry=>entry.node.scrollWidth));
   entries.forEach(entry=>animateZone(entry,state.epoch,span,sharedEnd));
  });
 }
 function schedule(){if(!queued){queued=true;requestAnimationFrame(function(){queued=false;update()})}}
 function init(){
  update();
  if(window.ResizeObserver){const observer=new ResizeObserver(schedule);document.querySelectorAll('[data-zone-animation], .wp-top-info-bar__zone-track').forEach(node=>observer.observe(node))}
  if(document.fonts)document.fonts.ready.then(schedule);
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
 window.addEventListener('resize',schedule);motion.addEventListener('change',schedule);
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

(function($){'use strict';$(function(){
 const $box=$('.wp-top-info-bar-admin'),$form=$box.closest('form'),$preview=$box.find('.wp-top-info-bar-preview');
 if(!$preview.length)return;
 const $stage=$preview.find('.wp-top-info-bar-preview__stage'),$update=$preview.find('.wp-top-info-bar-preview__update');
 const previewFrame=$preview.find('iframe')[0];
 const $dev = $preview.find('.wp-top-info-bar-preview__dev');
 let devOn = false;
 try { devOn = localStorage.getItem('wpTopInfoBarDev') === '1'; } catch(e) {}
 function applyDev() {
  let available = false;
  try {
   const win = previewFrame.contentWindow;
   available = !!(win.wpTopInfoBarDev && win.document.querySelector('.wp-top-info-bar'));
   if(win.wpTopInfoBarDev) win.wpTopInfoBarDev(devOn && available);
  } catch(e) {}
  $dev.prop('disabled', !available).attr('aria-pressed', devOn && available ? 'true' : 'false');
 }
 $dev.on('click', function() {
  devOn = !devOn;
  try { localStorage.setItem('wpTopInfoBarDev', devOn ? '1' : '0'); } catch(e) {}
  applyDev();
 });
 const devices=['desktop','tablet','mobile'];let currentDevice='desktop';
 try{const kept=localStorage.getItem('wpTopInfoBarDevice');if(devices.includes(kept))currentDevice=kept;}catch(e){}
 function state() {
  const overrides = new Map();
  $box.find('[data-image-row]').each(function() {
   const name = $(this).find('[data-image-id]').attr('name') || '', own = new Set();
   $(this).find('[data-image-override-field]').each(function() { if(this.value === '1') own.add($(this).data('image-override-field')); });
   overrides.set(name.replace(/\[image_id\]$/, ''), own);
  });
  return JSON.stringify($form.serializeArray().filter(function(item) {
   if(item.name.indexOf((window.wpTopInfoBarAdmin||{}).optionName+'[') !== 0) return false;
   const match = /^(.*\[images\]\[\d+\])\[([^\]]+)\]/.exec(item.name);
   if(!match || !overrides.has(match[1])) return true;
   const key = match[2];
   if(['image_id','override_enabled','override_fields','position','position_tablet','position_mobile'].includes(key)) return true;
   const group = /^size/.test(key) ? 'size' : /^margin/.test(key) ? 'margin' : /^animation/.test(key) ? 'animation' : /^custom_class/.test(key) ? 'custom_class' : key;
   return overrides.get(match[1]).has(group);
  }));
 }
 let renderedState=null,requestedState=null;
 function syncStale(){$update.toggleClass('is-stale',state()!==renderedState);}
 function refresh(){
  requestedState=state();
  const $post=$('<form method="post" hidden></form>').attr({action:$preview.data('url'),target:previewFrame.name});
  function add(name,value){$('<input type="hidden">').attr('name',name).val(value).appendTo($post);}
  add('action',$preview.data('action'));add('_wpnonce',$preview.data('nonce'));
  $form.serializeArray().filter(item=>item.name.indexOf((window.wpTopInfoBarAdmin||{}).optionName+'[')===0).forEach(item=>add(item.name,item.value));
  $post.appendTo(document.body);$post[0].submit();$post.remove();
 }
 $(previewFrame).on('load',function(){
  const doc=previewFrame.contentDocument;
  if(!doc||!doc.querySelector('body'))return;
  const valid=doc.documentElement.hasAttribute('data-top-info-bar-preview');
  $preview.find('.wp-top-info-bar-preview__error').prop('hidden',!!valid);
  if(valid){renderedState=requestedState;syncStale();}
  applyDev();
 });
 const $error=$('<p class="description wp-top-info-bar-preview__error" role="status" hidden></p>').text((window.wpTopInfoBarAdmin||{}).previewError||'Could not load the preview. Try Update again.').appendTo($preview);
 $box.on('input change','input,textarea,select',syncStale);
 $box.on('wpTopInfoBarTextsChanged wpTopInfoBarButtonsChanged',syncStale);
 // Saving uses WordPress's Publish box. Preview updates never save the form.
 const savedState = state() + ($('#title').val() || '');
 let saving = false;
 $(window).on('beforeunload.wpTopInfoBar', function(event) {
  if (!saving && state() + ($('#title').val() || '') !== savedState) {
   event.preventDefault();
   event.originalEvent.returnValue = '';
  }
 });
 $form.on('submit.wpTopInfoBar', function() { saving = true; });
 new MutationObserver(syncStale).observe($box.find('[data-images-list]')[0],{childList:true});
 $update.on('click',refresh);
 function syncTextSizes() {
  let inherited = '14px';
  devices.forEach(function(device) {
   const $input = $box.find('.wp-top-info-bar-admin__device-input[data-device="' + device + '"]');
   const value = $.trim($input.val() || '');
   $input.attr('placeholder', inherited);
   $box.find('.wp-top-info-bar-field-font_size .wp-top-info-bar-admin__device[data-device="' + device + '"]').toggleClass('has-value', device !== 'desktop' && value !== '');
   if(value) inherited = value;
  });
 }
 function setDevice(device) {
  currentDevice = device;
  $box.find('.wp-top-info-bar-preview__device-btn, .wp-top-info-bar-admin__device').each(function() {
   const on = $(this).data('device') === device;
   $(this).attr('aria-pressed', on ? 'true' : 'false').toggleClass('is-active', on);
  });
  $box.find('.wp-top-info-bar-admin__device-input').each(function() { this.hidden = $(this).data('device') !== device; });
  $box.find('.wp-top-info-bar-admin__spacing-inputs').each(function() { this.hidden = $(this).data('device') !== device; });
  $box.find('.wp-top-info-bar-admin__responsive-device').each(function() { this.hidden = $(this).data('device') !== device; });
  $(document).trigger('wpTopInfoBarDeviceChanged');
  fitPreview();
  try { localStorage.setItem('wpTopInfoBarDevice', device); } catch(e) {}
 }
 $box.on('click', '.wp-top-info-bar-admin__device', function() {
  setDevice($(this).data('device'));
  $(this).closest('.wp-top-info-bar-image-row__field, tr').find('input:visible').first().trigger('focus');
 });
 $box.on('input change', '.wp-top-info-bar-admin__device-input', syncTextSizes);
 syncTextSizes();
 function syncSpacing() {
  $box.find('.wp-top-info-bar-field-padding, .wp-top-info-bar-field-margin, .wp-top-info-bar-image-margin-field').each(function() {
   const $row = $(this);
   const inherited = {top:'0', right:'0', bottom:'0', left:'0'};
   devices.forEach(function(device) {
    let own = false;
    $row.find('.wp-top-info-bar-admin__spacing-inputs[data-device="' + device + '"] input[data-side]').each(function() {
     const side = $(this).data('side'), value = $.trim($(this).siblings('.wp-top-info-bar-admin__spacing-value').val());
     this.placeholder = this.hasAttribute('data-inherited-group') ? 'Inherit' : (value && this.value === '' ? value : inherited[side]);
     if(value) { inherited[side] = value.replace(/px$/i, ''); own = true; }
    });
    $row.find('.wp-top-info-bar-admin__device[data-device="' + device + '"]').toggleClass('has-value', device !== 'desktop' && own);
   });
  });
 }
 $box.on('input change', '.wp-top-info-bar-admin__spacing-inputs input[data-side]', function() {
  $(this).siblings('.wp-top-info-bar-admin__spacing-value').val(this.value);
  syncSpacing();
 });
 syncSpacing();
 function syncImageSizes() {
  $box.find('.wp-top-info-bar-image-size-field').each(function() {
   const $field = $(this);
   let inherited = '40';
   devices.forEach(function(device) {
    const $input = $field.find('.wp-top-info-bar-admin__responsive-device[data-device="' + device + '"] [data-image-size]');
    const value = $.trim($input.siblings('.wp-top-info-bar-image-size-value').val() || '');
    $input.attr('placeholder', $input.is('[data-inherited-group]') ? 'Inherit' : (value && $input.val() === '' ? value : inherited));
    $field.find('.wp-top-info-bar-admin__device[data-device="' + device + '"]').toggleClass('has-value', device !== 'desktop' && value !== '');
    if(value) inherited = value.replace(/px$/i, '');
   });
  });
 }
 $box.on('input change', '[data-image-size]', function() {
  $(this).siblings('.wp-top-info-bar-image-size-value').val(this.value);
  syncImageSizes();
 });
 $box.on('wpTopInfoBarImagesChanged', function() {
  syncImageSizes();
  syncImagePositions();
  syncSpacing();
  setDevice(currentDevice);
 });
 syncImageSizes();
 function syncImagePositions() {
  $box.find('.wp-top-info-bar-image-position-field').each(function() {
   const $field = $(this);
   let inherited = 'left';
   devices.forEach(function(device) {
    const $select = $field.find('.wp-top-info-bar-admin__responsive-device[data-device="' + device + '"] select');
    const value = $select.val() || '';
    const $inherit = $select.find('option[value=""]');
    const label = $select.find('option').filter(function() { return this.value === inherited; }).text();
    $inherit.text(($inherit.data('label') || 'Inherit') + ' (' + label + ')');
    $field.find('.wp-top-info-bar-admin__device[data-device="' + device + '"]').toggleClass('has-value', device !== 'desktop' && value !== '');
    if(value) inherited = value;
   });
  });
 }
 $box.on('change', '[data-image-position]', syncImagePositions);
 syncImagePositions();
 $preview.on('click','.wp-top-info-bar-preview__device-btn',function(){setDevice($(this).data('device'));});
 $box.on('click', '.wp-top-info-bar-admin__menu-item', function() {
  const page = $(this).data('page');
  $box.find('.wp-top-info-bar-admin__menu-item').each(function() {
   $(this).attr('aria-current', $(this).data('page') === page ? 'true' : 'false');
  });
  $box.find('.wp-top-info-bar-admin__page').each(function() { this.hidden = $(this).data('page') !== page; });
  $box.trigger('wpTopInfoBarPageChange');
 });
		var sizes = $preview.data( 'sizes' ) || {};
		var drawings = $preview.data( 'devices' ) || {};
		var $drawings = $preview.find( '.wp-top-info-bar-preview__device' );
		var $turn = $preview.find( '.wp-top-info-bar-preview__rotate' );
		var TURN_KEY = 'wpTopInfoBarTurned';
		var turned = {};
		try {
			turned = JSON.parse( window.localStorage.getItem( TURN_KEY ) ) || {};
		} catch ( e ) {}

		function canTurn() {
			return 'desktop' !== currentDevice && !! drawings[ currentDevice ];
		}

		function isTurned() {
			return canTurn() && !! turned[ currentDevice ];
		}

		function fitPreview() {
			if ( ! previewFrame || ! sizes[ currentDevice ] ) {
				return;
			}
			var turn = isTurned();
			var size = turn ? [ sizes[ currentDevice ][ 1 ], sizes[ currentDevice ][ 0 ] ] : sizes[ currentDevice ];
			var device = drawings[ currentDevice ];
			var image = $drawings.filter( '[data-device="' + currentDevice + '"]' )[ 0 ];
			var inDevice = !! ( device && image );
			// px of the frame per unit of the drawing (its screen as wide as the
			// upright frame); the whole device's size, and the screen's place in it.
			var unit = inDevice ? sizes[ currentDevice ][ 0 ] / device.screen[ 2 ] : 1;
			var upright = inDevice ? [ device.width * unit, device.height * unit ] : size;
			var outer = turn ? [ upright[ 1 ], upright[ 0 ] ] : upright;
			var offset = [ 0, 0 ];
			if ( inDevice ) {
				// A quarter turn clockwise takes a point ( x, y ) of the drawing to ( height - y, x ).
				offset = turn ?
					[ ( device.height - device.screen[ 1 ] - device.screen[ 3 ] ) * unit, device.screen[ 0 ] * unit ] :
					[ device.screen[ 0 ] * unit, device.screen[ 1 ] * unit ];
			}
			var width = $stage.width();
			// The whole device in view: no taller than the window leaves room for.
			var height = Math.max( 300, window.innerHeight - 180 );
			var scale = Math.min( 1, width / outer[ 0 ], height / outer[ 1 ] );
			var left = Math.max( 0, ( width - outer[ 0 ] * scale ) / 2 );

			previewFrame.style.width = size[ 0 ] + 'px';
			previewFrame.style.height = size[ 1 ] + 'px';
			previewFrame.style.transform = 'scale(' + scale + ')';
			previewFrame.style.left = ( left + offset[ 0 ] * scale ) + 'px';
			previewFrame.style.top = ( offset[ 1 ] * scale ) + 'px';
			previewFrame.style.borderRadius = inDevice ? ( device.radius * unit ) + 'px' : '';
			$preview.toggleClass( 'wp-top-info-bar-preview--device', inDevice );
			$drawings.each( function () {
				this.hidden = this !== image || ! inDevice;
			} );
			if ( inDevice ) {
				// Turned: drawn upright, then turned round its top left corner,
				// which goes to the right of its box.
				image.style.left = ( left + ( turn ? outer[ 0 ] * scale : 0 ) ) + 'px';
				image.style.width = ( upright[ 0 ] * scale ) + 'px';
				image.style.height = ( upright[ 1 ] * scale ) + 'px';
				image.style.transform = turn ? 'rotate(90deg)' : '';
			}
			$stage.css( 'height', Math.ceil( outer[ 1 ] * scale ) + 'px' );
			// The button (Tablet, Mobile) and the note of the device turned.
			$turn.prop( 'disabled', ! canTurn() ).attr( 'aria-pressed', turn ? 'true' : 'false' );
			$preview.find( '.wp-top-info-bar-preview__turned' ).each( function () {
				this.hidden = ! turn || $( this ).data( 'device' ) !== currentDevice;
			} );
		}

		/*
		 * The turn itself, seen: the device as it was (its box on screen) turned
		 * a quarter round into its new place and size (the stage drawn turned
		 * back, then let go). Not with reduced motion.
		 */
		function shownBox() {
			var image = $drawings.filter( function () {
				return ! this.hidden;
			} )[ 0 ];
			return ( image || previewFrame ).getBoundingClientRect();
		}

		$turn.on( 'click', function () {
			if ( ! canTurn() ) {
				return;
			}
			var from = shownBox();
			turned[ currentDevice ] = ! turned[ currentDevice ];
			try {
				window.localStorage.setItem( TURN_KEY, JSON.stringify( turned ) );
			} catch ( e ) {}
			var stage = $stage[ 0 ];
			stage.style.transition = 'none';
			stage.style.transform = '';
			fitPreview();
			if ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
				return;
			}
			var to = shownBox();
			var origin = stage.getBoundingClientRect();
			// Round the new box's middle: back the other way, its sides as they were, where it was.
			var middle = [ to.left + to.width / 2 - origin.left, to.top + to.height / 2 - origin.top ];
			var shift = [ from.left + from.width / 2 - ( to.left + to.width / 2 ), from.top + from.height / 2 - ( to.top + to.height / 2 ) ];
			var grow = to.width ? from.height / to.width : 1;
			$stage.addClass( 'is-turning' );
			stage.style.transformOrigin = middle[ 0 ] + 'px ' + middle[ 1 ] + 'px';
			stage.style.transform = 'translate(' + shift[ 0 ] + 'px, ' + shift[ 1 ] + 'px) rotate(' + ( turned[ currentDevice ] ? -90 : 90 ) + 'deg) scale(' + grow + ')';
			// Drawn so, then let go.
			void stage.offsetWidth;
			stage.style.transition = 'transform 0.5s ease';
			stage.style.transform = '';
			var finished = false;
			function done() {
				if ( finished ) {
					return;
				}
				finished = true;
				$stage.removeClass( 'is-turning' );
				stage.style.transition = '';
				stage.style.transformOrigin = '';
			}
			$stage.one( 'transitionend', done );
			setTimeout( done, 700 );
		} );

		$( window ).on( 'resize', fitPreview );
		if ( 'ResizeObserver' in window && $stage.length ) {
			new ResizeObserver( fitPreview ).observe( $stage[ 0 ] );
		}

setDevice(currentDevice);refresh();
});})(jQuery);

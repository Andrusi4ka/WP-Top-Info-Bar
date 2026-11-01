(function ($) {
	'use strict';

	const config = window.wpTopInfoBarAdmin || {};
	const optionName = config.optionName || 'wp_top_info_bar_settings';
	const labels = config.labels || {};
	let activeImage = null;
	let returnFocus = null;
	function numberImages() {
		$('[data-images-list] [data-image-row]').each(function (index) {
			$(this).find('[data-image-heading]').text((labels.imageNumber || 'Image %d').replace('%d', index + 1));
		});
		syncGallery();
	}

	function getNextIndex($list) {
		let maxIndex = -1;

		$list.find('[data-image-row]').each(function () {
			const name = $(this).find('[data-image-id]').attr('name') || '';
			const match = name.match(/\[images]\[(\d+)]/);

			if (match) {
				maxIndex = Math.max(maxIndex, parseInt(match[1], 10));
			}
		});

		return maxIndex + 1;
	}

	function setAttachment($row, attachment) {
		let filename = attachment.filename || String(attachment.url || '').split(/[?#]/)[0].split('/').pop() || '';
		try { filename = decodeURIComponent(filename); } catch(e) {}
		$row.find('[data-image-filename]').text(filename);
		let previewUrl = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
		$row.find('[data-image-id]').val(attachment.id).trigger('change');
		$row.find('[data-replace-image]').text(labels.replaceImage || 'Replace image');
		$row.find('[data-image-preview]').addClass('has-image').empty().append($('<img alt="">').attr('src', previewUrl));
		numberImages();
	}

	function bindImagePicker($row) {
		$row.find('[data-select-image]').off('click').on('click', function () {
			const frame = wp.media({title: labels.chooseImage || 'Choose image', button: {text: labels.useImage || 'Use this image'}, library: {type: 'image'}, multiple: false});
			frame.on('select', function () { setAttachment($row, frame.state().get('selection').first().toJSON()); });
			frame.open();
		});
	}

	function imageOptionEnabled($row, key) {
		const $toggle = $row.find('[data-image-option="' + key + '"]');
		return $toggle.length ? $toggle.prop('checked') : $row.find('input[type="hidden"][name$="[' + key + '_enabled]"]').val() === '1';
	}

	function imageStyle($row) {
		const px = value => /^-?(?:\d+(?:\.\d+)?|\.\d+)(px)?$/i.test(value) ? parseFloat(value) + 'px' : value;
		const sizes = [], margins = [];
		let size = '40px';
		const sides = {top:'0px', right:'0px', bottom:'0px', left:'0px'};
		['desktop', 'tablet', 'mobile'].forEach(function(device) {
			const own = $row.find('.wp-top-info-bar-image-size-field [data-device="' + device + '"] .wp-top-info-bar-image-size-value').val() || '';
			if(own) size = px(own.trim());
			sizes.push(size);
			$row.find('.wp-top-info-bar-image-margin-field .wp-top-info-bar-admin__spacing-inputs[data-device="' + device + '"] input[data-side]').each(function() {
				const value = $(this).siblings('input[type="hidden"]').val() || '';
				if(value !== '') sides[$(this).data('side')] = px(value.trim());
			});
			margins.push(imageOptionEnabled($row, 'margin') ? Object.values(sides).join(' ') : '0px 0px 0px 0px');
		});
		const animation = imageOptionEnabled($row, 'animation') ? $row.find('select[name$="[animation]"]').val() : 'none';
		const rawDelay = String($row.find('input[type="text"][name$="[delay]"]').val() || '').trim();
		const delay = animation !== 'none' ? (parseFloat(rawDelay) || 0) * (/s$/i.test(rawDelay) && !/ms$/i.test(rawDelay) ? 1000 : 1) : 0;
		const classes = imageOptionEnabled($row, 'custom_class') ? String($row.find('input[type="text"][name$="[custom_class]"]').val() || '').trim().split(/\s+/).sort().join(' ') : '';
		return {size: JSON.stringify(sizes), margin: JSON.stringify(margins), animation: animation || 'none', delay: delay, custom_class: classes};
	}

	let layoutTargetZone = 'center';
	function layoutZones() { try { const value = JSON.parse($('[data-layout-zones]').val() || '{}'); return value && !Array.isArray(value) ? value : {}; } catch (e) { return {}; } }
	function saveLayoutZones() {
		const order = [], zones = {};
		$('[data-layout-zone]').each(function () {
			const zone = $(this).attr('data-layout-zone');
			$(this).children('[data-layout-tile]').each(function () { const key = $(this).attr('data-layout-key'); order.push(key); zones[key] = zone; });
			$(this).children('[data-layout-add]').appendTo(this);
		});
		$('[data-layout-zones]').val(JSON.stringify(zones)).trigger('change');
		$('[data-layout-order]').val(JSON.stringify(order)).trigger('change');
	}
	window.wpTopInfoBarLayout = { placeNew:function (key, target) {
		const zones = layoutZones(); zones[key] = target || layoutTargetZone;
		$('[data-layout-zones]').val(JSON.stringify(zones)).trigger('change');
		let order = []; try { order = JSON.parse($('[data-layout-order]').val() || '[]'); } catch(e) {}
		$('[data-layout-tile]').each(function () { const id = $(this).attr('data-layout-key'); if (!order.includes(id)) order.push(id); });
		if (!order.includes(key)) order.push(key);
		$('[data-layout-order]').val(JSON.stringify(order)).trigger('change');
		layoutTargetZone = 'center';
	}, cloneAfter:function (source, key) {
		const keys = $('[data-layout-tile]').map(function () { return $(this).attr('data-layout-key'); }).get().filter(id => id !== key);
		const index = keys.indexOf(source); keys.splice(index < 0 ? keys.length : index + 1, 0, key);
		const zones = layoutZones();
		const $source = $('[data-layout-tile]').filter(function () { return $(this).attr('data-layout-key') === source; });
		zones[key] = zones[source] || $source.closest('[data-layout-zone]').attr('data-layout-zone') || 'center';
		$('[data-layout-zones]').val(JSON.stringify(zones)).trigger('change');
		$('[data-layout-order]').val(JSON.stringify(keys)).trigger('change');
		syncLayoutTiles();
	} };
	let layoutDragging = false;
	let layoutDropUntil = 0;
	function syncLayoutTiles() {
		if (layoutDragging) return;
		const $add = $('[data-layout-add]');
		if (!$add.length) return;
		const $bar = $('[data-layout-zone]');
		const zones = layoutZones();
		$bar.find('[data-layout-tile]').remove();
		const entries = [];
		const labelFor = page => $('[data-layout-element="' + page + '"]').text().trim();
		$('[data-images-list] [data-image-row]').each(function (index) {
			const $row = $(this);
			if (!Number($row.find('[data-image-id]').val())) return;
			const position = $row.find('[data-image-legacy-position="desktop"]').val() || 'left';
			entries.push({key:'image:' + ((/\[images\]\[([^\]]+)\]/.exec($row.find('[data-image-id]').attr('name') || '') || [])[1] || index), rank: {left:10, 'center-top':30, 'center-bottom':50, right:70}[position] || 10, page:'pictures', row:this, src:$row.find('[data-image-preview] img').attr('src'), label:$row.find('[data-image-heading]').text() || labelFor('pictures')});
		});
		$('[data-text-element]').each(function () {
			const id = $(this).attr('data-text-element');
			const html = $(this).find('[data-text-field="message"]').val() || '';
			const plain = new DOMParser().parseFromString(html, 'text/html').body.textContent.trim();
			entries.push({key:'text:' + id, rank:40, page:'text', textId:id, label:labelFor('text'), text:plain});
		});
		$('[data-button-element]').each(function () {
			const $row = $(this), id = $row.attr('data-button-element');
			const text = String($row.find('[data-button-field="button_text"]').val() || '').trim();
			const position = $row.find('[data-button-field="button_position"]').val();
			entries.push({key:'button:' + id, buttonId:id, rank:{'screen-left':0, 'text-left':20, 'text-right':60, 'screen-right':80, 'center-top':30, 'center-bottom':50}[position] ?? 60, page:'button', label:labelFor('button'), text:text});
		});
		let saved = [];
		try { saved = JSON.parse($('[data-layout-order]').val() || '[]'); } catch (error) {}
		if (!Array.isArray(saved)) saved = [];
		saved = saved.map(key => key === 'text' ? 'text:legacy' : (key === 'button' ? 'button:legacy' : key));
		entries.sort((a,b) => {
			if (!saved.length) return a.rank - b.rank;
			const ai = saved.indexOf(a.key), bi = saved.indexOf(b.key);
			return (ai < 0 ? saved.length : ai) - (bi < 0 ? saved.length : bi) || a.rank - b.rank;
		}).forEach(function (entry) {
			const $tile = $('<button type="button" class="wp-top-info-bar-layout-builder__tile" data-layout-tile></button>').attr({'aria-label':entry.label, title:entry.text || entry.label}).attr('data-layout-key', entry.key).data('row', entry.row || null);
			if (entry.page === 'pictures') {
				if (entry.src) $tile.append($('<img alt="">').attr('src', entry.src));
			} else {
				$tile.append($('<span class="wp-top-info-bar-layout-builder__tile-label"></span>').text(entry.page === 'button' ? 'Btn' : 'Text'));
			}
			$tile.on('click', function () {
				if (Date.now() < layoutDropUntil) return;
				if (entry.row) openImage($(entry.row), this);
				else if (entry.page === 'text' && window.wpTopInfoBarTexts) window.wpTopInfoBarTexts.open(entry.textId, this);
				else if (entry.page === 'button' && window.wpTopInfoBarButtons) window.wpTopInfoBarButtons.open(entry.buttonId, this);
				else {
					$('.wp-top-info-bar-admin__menu-item[data-page="' + entry.page + '"]').trigger('click');
					if (entry.page === 'button') $('#wp-top-info-bar-button-text').trigger('focus');
				}
			}).insertBefore($bar.filter('[data-layout-zone="' + (zones[entry.key] || 'center') + '"]').children('[data-layout-add]'));
		});
	}

	function syncGallery() { syncLayoutTiles(); }

	function openImage($row, opener) {
		if ($row.data('syncOptions')) $row.data('syncOptions')();
		activeImage = $row[0]; returnFocus = opener;
		$('[data-images-list] [data-image-row]').prop('hidden', true);
		$row.prop('hidden', false);
		$('#wp-top-info-bar-image-modal-title').text($row.find('[data-image-heading]').text());
		$('[data-image-modal]').prop('hidden', false);
		$('body').addClass('wp-top-info-bar-modal-open');
		$row.closest('.wp-top-info-bar-admin').trigger('wpTopInfoBarImagesChanged');
		$('[data-image-modal] .wp-top-info-bar-image-modal__close').trigger('focus');
	}

	function closeImage() {
		$('[data-image-modal]').prop('hidden', true);
		$('[data-images-list] [data-image-row]').prop('hidden', true);
		$('body').removeClass('wp-top-info-bar-modal-open');
		const image = activeImage; activeImage = null;
		const $tile = $('[data-layout-tile]').filter(function () { return $(this).data('row') === image; }).first();
		if ($tile.length) $tile.trigger('focus');
		else $('[data-layout-zone="center"] [data-layout-add]').trigger('focus');
	}

	function imageFieldGroup(key) {
		if (/^size/.test(key)) return 'size';
		if (/^margin/.test(key)) return 'margin';
		if (/^animation/.test(key)) return 'animation';
		if (/^custom_class/.test(key)) return 'custom_class';
		return key === 'delay' ? 'delay' : '';
	}

	function bindRemoveRow($row) {
		$row.find('[data-remove-image-row]').off('click').on('click', function () {
			const $box = $row.closest('.wp-top-info-bar-admin');
			const wasOpen = activeImage === $row[0];
			$row.remove();
			numberImages();
			if(wasOpen) closeImage();
			$box.trigger('wpTopInfoBarImagesChanged');
		});
	}

	// A delay only matters with an animation: without one the delay field is
	// greyed out, and keeps its value (readonly fields are still submitted).
	function syncDelay($animation, $delay, $wrap) {
		const $enabled = $animation.closest('[data-image-row], [data-image-shared]').find('[data-image-option="animation"]');
		const off = 'none' === $animation.val() || ($enabled.length && !$enabled.prop('checked'));

		$delay.prop('readOnly', off).attr('aria-disabled', off ? 'true' : null);
		$wrap.toggleClass('is-disabled', off);
	}

	function bindDelay($animation, $delay, $wrap) {
		$animation.off('change.wpTopInfoBar').on('change.wpTopInfoBar', function () {
			syncDelay($animation, $delay, $wrap);
		});
		syncDelay($animation, $delay, $wrap);
	}

	function bindRow($row) {
		const formId = $('[data-image-modal]').data('formId');
		if(formId && $row.closest('[data-image-modal]').length) $row.find('input, select, textarea, button').attr('form', formId);
		const $delay = $row.find('input[type="text"][name$="[delay]"]');

		bindImagePicker($row);
		bindRemoveRow($row);
		function syncOptions() {
			$row.find('[data-image-option]').each(function () {
				const $field = $(this).closest('.wp-top-info-bar-image-row__field');
				$field.find('[data-image-option-value]').prop('hidden', !this.checked);
				if ($(this).data('image-option') === 'margin') {
					$field.find('.wp-top-info-bar-admin__devices').prop('hidden', !this.checked);
				}
			});
			$row.find('[data-image-delay-field]').prop('hidden', !imageOptionEnabled($row, 'animation'));
			syncDelay($row.find('select[name$="[animation]"]'), $delay, $delay.closest('.wp-top-info-bar-image-row__field'));
		}
		$row.find('[data-image-option]').off('change.wpTopInfoBarOptions').on('change.wpTopInfoBarOptions', syncOptions);
		$row.data('syncOptions', syncOptions);
		$row.off('input.wpTopInfoBarOverride change.wpTopInfoBarOverride').on('input.wpTopInfoBarOverride change.wpTopInfoBarOverride', '[data-image-individual-options] input, [data-image-individual-options] select', function() {
			if ($(this).is('[data-image-position]')) { setTimeout(syncGallery, 0); return; }
			const key = (/[\[]images\]\[[^\]]+\]\[([^\]]+)/.exec(this.name || '') || [])[1] || '';
			const group = $(this).is('[data-image-size]') ? 'size' : ($(this).is('[data-side]') ? 'margin' : imageFieldGroup(key));
			if (!group) return;
			const changedInput = this;
			$row.find('[data-inherited-group="' + group + '"]').each(function () {
				if (this !== changedInput) $(this).val($(this).data('inheritedValue'));
				$(this).removeAttr('data-inherited-group').removeData('inheritedValue');
			});
			$(this).find('[data-inherited-option]').remove();
			// Editing an individual option activates it without a checkbox.
			if (!$row.find('[data-image-option="' + group + '"]').length) $row.find('input[type="hidden"][name$="[' + group + '_enabled]"]').val('1');
			$row.find('[data-image-override-field="' + group + '"]').val('1');
			$row.find('[data-image-override]').val('1');
			$row.find('[data-override-initialized]').val('1');
			setTimeout(syncGallery, 0);
		});
		syncOptions();
		bindDelay($row.find('select[name$="[animation]"]'), $delay, $delay.closest('.wp-top-info-bar-image-row__field'));
	}

	$(function () {
		// Render help outside the scrollable modal and clamp it to the viewport.
		let $floatingHelp = null, $helpSource = null;
		function hideHelp() {
			if ($floatingHelp) $floatingHelp.remove();
			if ($helpSource) $helpSource.removeAttr('aria-describedby');
			$floatingHelp = null; $helpSource = null;
		}
		function showHelp() {
			hideHelp();
			const $source = $(this), text = $source.find('.wp-top-info-bar-tooltip__content').text();
			if (!text) return;
			$helpSource = $source;
			$floatingHelp = $('<span class="wp-top-info-bar-tooltip__content wp-top-info-bar-tooltip__floating" id="wp-top-info-bar-floating-help" role="tooltip"></span>').text(text).appendTo(document.body);
			$source.attr('aria-describedby','wp-top-info-bar-floating-help');
			const rect = this.getBoundingClientRect(), popup = $floatingHelp[0].getBoundingClientRect();
			const left = Math.max(12, Math.min(rect.left + rect.width / 2 - popup.width / 2, window.innerWidth - popup.width - 12));
			const below = rect.top - popup.height - 8 < 12;
			$floatingHelp.css({left:left, top:below ? rect.bottom + 8 : rect.top - popup.height - 8}).toggleClass('is-below',below);
			$floatingHelp[0].style.setProperty('--tooltip-arrow', Math.max(10, Math.min(popup.width - 10, rect.left + rect.width / 2 - left)) + 'px');
		}
		$(document).on('mouseenter.wpTopHelp focusin.wpTopHelp','.wp-top-info-bar-tooltip',showHelp)
			.on('mouseleave.wpTopHelp focusout.wpTopHelp','.wp-top-info-bar-tooltip',hideHelp);
		document.addEventListener('scroll',hideHelp,true); window.addEventListener('resize',hideHelp);
		$(document).on('click.wpTopHelp keydown.wpTopHelp','[data-close-zone-modal], [data-close-text-modal], [data-close-button-modal], [data-close-image-modal]',hideHelp);

		$(document).on('input.wpTopInfoBarTiles change.wpTopInfoBarTiles', '.wp-top-info-bar-admin input, .wp-top-info-bar-admin select, .wp-top-info-bar-admin textarea', function () { setTimeout(syncLayoutTiles, 0); });
		$('.wp-top-info-bar-admin').on('wpTopInfoBarImagesChanged wpTopInfoBarPageChange wpTopInfoBarTextsChanged wpTopInfoBarButtonsChanged', function () { setTimeout(syncLayoutTiles, 0); });
		function syncAppearanceSpacing() {
			$('[data-spacing-toggle]').each(function () { $(this).closest('tr').find('[data-spacing-options], .wp-top-info-bar-admin__devices').prop('hidden', !this.checked); });
		}
		$('[data-spacing-toggle]').on('change',syncAppearanceSpacing); syncAppearanceSpacing();
		function syncBarDecorations() {
			$('[data-bar-decoration]').each(function () { $('[data-bar-decoration-options="' + $(this).attr('data-bar-decoration') + '"]').prop('hidden', !this.checked); });
		}
		$('[data-bar-decoration]').on('change', syncBarDecorations); syncBarDecorations();
		const $zoneModal = $('[data-zone-modal]');
		const $zoneForm = $zoneModal.closest('form');
		if (!$zoneForm.attr('id')) $zoneForm.attr('id', 'wp-top-info-bar-form');
		$zoneModal.find('input, select, textarea, button').attr('form', $zoneForm.attr('id'));
		$zoneModal.appendTo(document.body);
		let zoneOpener = null;
		function syncZoneSpacing() {
			$zoneModal.find('[data-zone-spacing-toggle]').each(function () {
				$(this).closest('.wp-top-info-bar-zone-modal__field').find('[data-zone-spacing-options], .wp-top-info-bar-admin__devices').prop('hidden', !this.checked);
			});
		}
		$zoneModal.on('change', '[data-zone-spacing-toggle]', syncZoneSpacing);
		syncZoneSpacing();
		function syncZoneAnimationFields() {
			$zoneModal.find('[data-zone-animation-block]').each(function () {
				const $block = $(this); let mode = 'inherit', effect = 'fade-in', currentMode = mode, currentEffect = effect;
				['desktop', 'tablet', 'mobile'].forEach(function (device) {
					const $device = $block.find('.wp-top-info-bar-admin__responsive-device[data-device="' + device + '"]');
					mode = $device.find('[data-zone-animation-input="animation_mode"]').val() || mode;
					effect = $device.find('[data-zone-animation-input="animation"]').val() || effect;
					if (!$device.first().prop('hidden')) { currentMode = mode; currentEffect = effect; }
				});
				$block.find('[data-zone-animation-field="animation"]').prop('hidden', currentMode === 'inherit');
				$block.find('[data-zone-animation-field="animation_delay"]').prop('hidden', currentMode === 'inherit' || currentMode === 'none' || currentEffect === 'none');
				$block.find('[data-zone-animation-field="animation_speed"]').prop('hidden', currentMode !== 'custom' || currentEffect !== 'marquee');
			});
		}
		$zoneModal.on('change', '[data-zone-animation-toggle], [data-zone-animation-input="animation"]', function () {
			const $block = $(this).closest('[data-zone-animation-block]');
			const device = $(this).closest('[data-device]').attr('data-device');
			const $device = $block.find('.wp-top-info-bar-admin__responsive-device[data-device="' + device + '"]');
			const enabled = $device.find('[data-zone-animation-toggle]').prop('checked');
			const effect = $device.find('[data-zone-animation-input="animation"]').val();
			$device.find('[data-zone-animation-input="animation_mode"]').val(enabled ? (effect === 'none' ? 'none' : 'custom') : 'inherit').trigger('input');
			syncZoneAnimationFields();
		});
		$(document).on('wpTopInfoBarDeviceChanged', syncZoneAnimationFields);
		syncZoneAnimationFields();
		function closeZoneModal() {
			$zoneModal.find('.wp-top-info-bar-color-pick').each(function () { const picker = $(this).data('pickr'); if (picker) picker.hide(); });
			$zoneModal.prop('hidden', true); $('body').removeClass('wp-top-info-bar-modal-open');
			if (zoneOpener) $(zoneOpener).trigger('focus');
		}
		$('[data-layout-zone]').each(function () {
			const $zone = $(this), zone = $zone.attr('data-layout-zone');
			const $tools = $('<div class="wp-top-info-bar-zone-tools"></div>').prependTo($zone);
			$('<button type="button" class="wp-top-info-bar-zone-tools__toggle" data-zone-edit aria-haspopup="dialog" aria-label="Edit zone"><span class="dashicons dashicons-edit" aria-hidden="true"></span></button>').appendTo($tools).on('click', function () {
				zoneOpener = this;
				$zoneModal.find('[data-zone-settings]').each(function () { this.hidden = $(this).attr('data-zone-settings') !== zone; });
				$('#wp-top-info-bar-zone-modal-title').text(zone.charAt(0).toUpperCase() + zone.slice(1) + ' zone');
				$zoneModal.prop('hidden', false); $('body').addClass('wp-top-info-bar-modal-open');
				$zoneModal.find('.wp-top-info-bar-image-modal__close').trigger('focus');
			});
		});
		$zoneModal.find('[data-close-zone-modal]').on('click', closeZoneModal);
		$(document).on('keydown.wpTopInfoBarZoneModal', function (event) {
			if ($zoneModal.prop('hidden') || $(event.target).closest('.pcr-app').length) return;
			if (event.key === 'Escape') { event.preventDefault(); closeZoneModal(); }
			if (event.key === 'Tab') {
				const $items = $zoneModal.find('button, input, select, textarea, [tabindex="0"]').filter(':visible:not(:disabled)');
				const first = $items[0], last = $items[$items.length-1];
				if (event.shiftKey && event.target === first) { event.preventDefault(); last.focus(); }
				else if (!event.shiftKey && event.target === last) { event.preventDefault(); first.focus(); }
			}
		});
		$('[data-layout-zone]').sortable({
			items:'> [data-layout-tile]', connectWith:'[data-layout-zone]', cancel:'', distance:5, tolerance:'pointer',
			placeholder:'wp-top-info-bar-layout-builder__tile wp-top-info-bar-layout-builder__placeholder', forcePlaceholderSize:true,
			start:function () { layoutDragging=true; $(this).sortable('instance').floating=true; },
			activate:function () { $(this).sortable('instance').floating=true; },
			stop:function () { saveLayoutZones(); layoutDragging=false; layoutDropUntil=Date.now()+250; setTimeout(syncLayoutTiles,0); }
		});
		// Layout's element picker currently opens the existing element editors.
		const $layoutAdd = $('[data-layout-add]');
		let $pickerOpener = $layoutAdd.filter(function () { return $(this).closest('[data-layout-zone]').attr('data-layout-zone') === 'center'; });
		const $layoutPicker = $('[data-layout-picker]').appendTo(document.body);
		function closeLayoutPicker(restoreFocus) {
			const wasOpen = !$layoutPicker.prop('hidden');
			$layoutPicker.prop('hidden', true);
			if (wasOpen) $('body').removeClass('wp-top-info-bar-modal-open');
			$layoutAdd.attr('aria-expanded', 'false');
			if (restoreFocus) $pickerOpener.trigger('focus');
		}
		$layoutAdd.on('click', function () {
			$pickerOpener = $(this);
			layoutTargetZone = $(this).closest('[data-layout-zone]').attr('data-layout-zone') || 'center';
			const open = $layoutPicker.prop('hidden');
			$layoutPicker.prop('hidden', !open);
			$('body').toggleClass('wp-top-info-bar-modal-open', open);
			$pickerOpener.attr('aria-expanded', open ? 'true' : 'false');
			if (open) $layoutPicker.find('button').first().trigger('focus');
		});
		$('[data-close-layout-picker]').on('click', function () { closeLayoutPicker(true); });
		$('[data-layout-element]').on('click', function () {
			const page = $(this).data('layout-element');
			closeLayoutPicker(false);
			$('.wp-top-info-bar-admin__menu-item[data-page="' + page + '"]').trigger('click');
			if (page === 'pictures') $('[data-add-image-row]').trigger('click');
			else if (page === 'button' && window.wpTopInfoBarButtons) window.wpTopInfoBarButtons.add(this);
			else if (page === 'text' && window.wpTopInfoBarTexts) window.wpTopInfoBarTexts.add(this);
		});
		$(document).on('click.wpTopInfoBarLayout', function (event) {
			if (!$(event.target).closest('[data-layout-picker], [data-layout-add]').length) closeLayoutPicker(false);
		}).on('keydown.wpTopInfoBarLayout', function (event) {
			if (event.key === 'Tab' && !$layoutPicker.prop('hidden')) {
				const $buttons = $layoutPicker.find('button').filter(':visible');
				const first = $buttons[0], last = $buttons[$buttons.length - 1];
				if (event.shiftKey && event.target === first) { event.preventDefault(); last.focus(); }
				else if (!event.shiftKey && event.target === last) { event.preventDefault(); first.focus(); }
			}
			if (event.key === 'Escape' && !$layoutPicker.prop('hidden')) {
				event.preventDefault(); closeLayoutPicker(true);
			}
		});
		const $list = $('[data-images-list]');
		const $modal = $('[data-image-modal]');
		const $owner = $modal.closest('form');
		if(!$owner.attr('id')) $owner.attr('id', 'wp-top-info-bar-form');
		$modal.data('formId', $owner.attr('id')).addClass('wp-top-info-bar-admin').appendTo(document.body);
		const template = wp.template('wp-top-info-bar-image-row');
		const $textDelay = $('#wp-top-info-bar-text-delay');
		function syncAnimationEnabled() {
			$('.wp-top-info-bar-admin__animation-options').prop('hidden', !$('#wp-top-info-bar-animation-enabled').prop('checked'));
		}
		$('#wp-top-info-bar-animation-enabled').on('change', syncAnimationEnabled);
		syncAnimationEnabled();
		function syncAnimationDevices() {
			let animation = 'none';
			['desktop', 'tablet', 'mobile'].forEach(function (device) {
				const suffix = device === 'desktop' ? '' : '_' + device;
				const $animation = $('#wp-top-info-bar-animation' + suffix);
				animation = $animation.val() || animation;
				if ($animation.val() === '') $animation.find('option[value=""]').text('Inherit (' + animation + ')');
				const $delay = $('#wp-top-info-bar-text-delay' + suffix);
				$delay.prop('readOnly', animation === 'none').attr('aria-disabled', animation === 'none' ? 'true' : null);
				if ($animation.closest('[data-device]').prop('hidden') === false) {
					$('#wp-top-info-bar-marquee-speed').closest('tr').prop('hidden', animation !== 'marquee');
				}
			});
		}
		$('[id^="wp-top-info-bar-animation"]').on('change', syncAnimationDevices);
		$(document).on('wpTopInfoBarDeviceChanged', syncAnimationDevices);
		syncAnimationDevices();

		$list.find('[data-image-row]').each(function () {
			bindRow($(this));
		});
		numberImages();

		$('[data-add-image-row]').on('click', function () {
			const targetZone = layoutTargetZone; layoutTargetZone = 'center';
			const frame = wp.media({title: labels.chooseImage || 'Choose image', button: {text: labels.useImage || 'Use this image'}, library: {type: 'image'}, multiple: true});
			frame.on('select', function () {
				frame.state().get('selection').toJSON().forEach(function (attachment) {
					const $row = $(template({index: getNextIndex($list), optionName: optionName}));
					$list.append($row); bindRow($row); setAttachment($row, attachment);
					const key = (/\[images\]\[([^\]]+)\]/.exec($row.find('[data-image-id]').attr('name')) || [])[1];
					window.wpTopInfoBarLayout.placeNew('image:' + key, targetZone);
				});
				$list.closest('.wp-top-info-bar-admin').trigger('wpTopInfoBarImagesChanged');
			});
			frame.open();
		});
		$('[data-clone-active-image]').on('click', function () {
			if (!activeImage) return;
			const $source = $(activeImage), index = getNextIndex($list);
			const oldIndex = (/\[images\]\[([^\]]+)\]/.exec($source.find('[data-image-id]').attr('name')) || [])[1];
			const $copy = $source.clone(false, false).prop('hidden',true);
			$copy.find('[name]').each(function () { this.name = this.name.replace(/\[images\]\[[^\]]+\]/, '[images][' + index + ']'); });
			$copy.find('select').each(function (i) { $(this).val($source.find('select').eq(i).val()); });
			$copy.insertAfter($source); bindRow($copy);
			window.wpTopInfoBarLayout.cloneAfter('image:' + oldIndex, 'image:' + index);
			numberImages(); closeImage();
			$('.wp-top-info-bar-admin').trigger('wpTopInfoBarImagesChanged');
			setTimeout(function () { $('[data-layout-key="image:' + index + '"]').trigger('focus'); }, 0);
		});
		$('[data-remove-active-image]').on('click', function () {
			if (!activeImage) return;
			$(activeImage).remove();
			numberImages();
			closeImage();
			$('.wp-top-info-bar-admin').trigger('wpTopInfoBarImagesChanged');
		});
		$('[data-close-image-modal]').on('click', closeImage);
		$(document).on('keydown.wpTopInfoBarModal', function (event) {
			if(!activeImage || $(event.target).closest('.media-modal, .media-modal-backdrop').length || $('.media-modal:visible').length) return;
			if(event.key === 'Escape') { event.preventDefault(); closeImage(); }
			if(event.key === 'Tab') {
				const $focusable = $('[data-image-modal]').find('button, input, select, textarea, [tabindex="0"]').filter(':visible:not(:disabled)');
				const first = $focusable[0], last = $focusable[$focusable.length - 1];
				if(event.shiftKey && event.target === first) { event.preventDefault(); last.focus(); }
				else if(!event.shiftKey && event.target === last) { event.preventDefault(); first.focus(); }
			}
		});
	});
}(jQuery));

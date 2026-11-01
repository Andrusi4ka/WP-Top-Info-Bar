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

	function hasIndividualSettings($row) {
		const own = imageStyle($row), shared = imageStyle($('[data-image-shared]'));
		return Object.keys(own).some(group => $row.find('[data-image-override-field="' + group + '"]').val() === '1' && own[group] !== shared[group]);
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

	function syncGallery() {
		const $gallery = $('[data-images-gallery]');
		$gallery.find('[data-image-gallery-item]').remove();
		$('[data-images-list] [data-image-row]').each(function (index) {
			const $row = $(this), src = $row.find('[data-image-preview] img').attr('src');
			const $button = $('<button type="button" class="wp-top-info-bar-gallery__item" data-image-gallery-item></button>')
				.attr({'aria-label': (labels.editImage || 'Edit Image %d').replace('%d', index + 1), 'aria-haspopup': 'dialog'})
				.toggleClass('has-settings', hasIndividualSettings($row)).data('row', this);
			if(src) $button.append($('<img alt="">').attr('src', src));
			$button.on('click', function () { openImage($row, this); }).insertBefore($gallery.find('[data-add-image-row]'));
		});
		syncLayoutTiles();
	}

	function openImage($row, opener) {
		copySharedSettings($row);
		showImageInheritance($row);
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
		const $tile = $('[data-image-gallery-item]').filter(function () { return $(this).data('row') === activeImage; }).first();
		activeImage = null;
		const $layoutTile = $('[data-layout-tile]').filter(function () { return $(this).data('row') === $tile.data('row'); }).first();
		if ($(returnFocus).is('[data-layout-tile]') && $layoutTile.length) $layoutTile.trigger('focus');
		else if($tile.length) $tile.trigger('focus');
		else if(returnFocus && document.contains(returnFocus)) $(returnFocus).trigger('focus');
		else $('[data-add-image-row]').trigger('focus');
	}

	function showImageInheritance($row) {
		$row.find('[data-image-size], input[data-side], input[type="text"], select[name$="[animation]"]').each(function () {
			const $input = $(this);
			const key = (/\[images\]\[[^\]]+\]\[([^\]]+)/.exec(this.name || '') || [])[1] || '';
			const group = $input.is('[data-image-size]') ? 'size' : ($input.is('[data-side]') ? 'margin' : imageFieldGroup(key));
			if (!group || $row.find('[data-image-override-field="' + group + '"]').val() === '1') return;
			if (this.tagName === 'SELECT') {
				$input.find('[data-inherited-option]').remove();
				$('<option data-inherited-option></option>').val(this.value).text('Inherit').prop('selected', true).prependTo($input);
			} else {
				$input.data('inheritedValue', this.value).attr('data-inherited-group', group).val('').attr('placeholder', 'Inherit');
			}
		});
	}

	function copySharedSettings($row) {
		const $shared = $('[data-image-shared]');
		$row.find('[data-image-individual-options] [name]').each(function () {
			const suffix = this.name.replace(/^[^\[]+\[images\]\[[^\]]+\]/, '');
			const group = imageFieldGroup((/^\[([^\]]+)/.exec(suffix) || [])[1] || '');
			if (!group || $row.find('[data-image-override-field="' + group + '"]').val() === '1') return;
			if (/_enabled\]$/.test(suffix)) {
				this.value = imageOptionEnabled($shared, group) ? '1' : '0';
				return;
			}
			const type = this.type;
			const $source = $shared.find('[name]').filter(function () {
				return this.name.replace(/^[^\[]+\[image_defaults\]/, '') === suffix && this.type === type;
			}).first();
			if(!$source.length) return;
			if(type === 'checkbox') this.checked = $source.prop('checked');
			else this.value = $source.val();
		});
		$row.find('[data-image-size], input[data-side]').each(function () {
			const raw = $(this).siblings('input[type="hidden"]').val() || '';
			this.value = /^-?(?:\d+(?:\.\d+)?|\.\d+)(px)?$/i.test(raw) ? parseFloat(raw) : '';
		});
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
			$row.find('[data-image-delay-field]').prop('hidden', $row.is('[data-image-shared]') && !imageOptionEnabled($row, 'animation'));
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
			$row.find('input[type="hidden"][name$="[' + group + '_enabled]"]').val('1');
			$row.find('[data-image-override-field="' + group + '"]').val('1');
			$row.find('[data-image-override]').val('1');
			$row.find('[data-override-initialized]').val('1');
			setTimeout(syncGallery, 0);
		});
		syncOptions();
		bindDelay($row.find('select[name$="[animation]"]'), $delay, $delay.closest('.wp-top-info-bar-image-row__field'));
	}

	$(function () {
		$(document).on('input.wpTopInfoBarTiles change.wpTopInfoBarTiles', '.wp-top-info-bar-admin input, .wp-top-info-bar-admin select, .wp-top-info-bar-admin textarea', function () { setTimeout(syncLayoutTiles, 0); });
		$('.wp-top-info-bar-admin').on('wpTopInfoBarImagesChanged wpTopInfoBarPageChange wpTopInfoBarTextsChanged wpTopInfoBarButtonsChanged', function () { setTimeout(syncLayoutTiles, 0); });
		const $zoneModal = $('[data-zone-modal]');
		const $zoneForm = $zoneModal.closest('form');
		if (!$zoneForm.attr('id')) $zoneForm.attr('id', 'wp-top-info-bar-form');
		$zoneModal.find('input, select, textarea, button').attr('form', $zoneForm.attr('id'));
		$zoneModal.appendTo(document.body);
		let zoneOpener = null;
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
		function syncSpacingToggles() {
			$('[data-spacing-toggle]').each(function () {
				const $row = $(this).closest('tr');
				$row.find('[data-spacing-options], .wp-top-info-bar-admin__devices').prop('hidden', !this.checked);
			});
		}
		$('[data-spacing-toggle]').on('change', syncSpacingToggles);
		syncSpacingToggles();
		const $list = $('[data-images-list]');
		const $modal = $('[data-image-modal]');
		const $owner = $modal.closest('form');
		if(!$owner.attr('id')) $owner.attr('id', 'wp-top-info-bar-form');
		$modal.data('formId', $owner.attr('id')).addClass('wp-top-info-bar-admin').appendTo(document.body);
		const template = wp.template('wp-top-info-bar-image-row');
		const $textDelay = $('#wp-top-info-bar-text-delay');
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
		bindRow($('[data-image-shared]'));
		$('[data-image-shared]').on('input change', 'input, select', function() { setTimeout(syncGallery, 0); });
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
		$('[data-reset-image-settings]').on('click', function () {
			if (!activeImage) return;
			const $row = $(activeImage);
			$row.find('[data-image-override-field], [data-image-override]').val('0');
			$row.find('[data-override-initialized]').val('1');
			$row.find('[data-inherited-option]').remove();
			$row.find('[data-image-position]').val(function () {
				return $(this).closest('[data-device]').data('device') === 'desktop' ? 'left' : '';
			});
			copySharedSettings($row);
			showImageInheritance($row);
			if ($row.data('syncOptions')) $row.data('syncOptions')();
			syncGallery();
			$row.closest('.wp-top-info-bar-admin').trigger('wpTopInfoBarImagesChanged');
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

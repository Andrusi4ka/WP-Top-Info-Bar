(function ($) {
	'use strict';

	const config = window.wpTopInfoBarAdmin || {};
	const optionName = config.optionName || 'wp_top_info_bar_settings';
	const labels = config.labels || {};

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

	function bindImagePicker($row) {
		$row.find('[data-select-image]').off('click').on('click', function () {
			const $preview = $row.find('[data-image-preview]');
			const frame = wp.media({
				title: labels.chooseImage || 'Choose image',
				button: {
					text: labels.useImage || 'Use this image'
				},
				library: {
					type: 'image'
				},
				multiple: false
			});

			frame.on('select', function () {
				const attachment = frame.state().get('selection').first().toJSON();
				let filename = attachment.filename || String(attachment.url || '').split(/[?#]/)[0].split('/').pop() || '';
				try { filename = decodeURIComponent(filename); } catch (e) {}
				$row.find('[data-image-filename]').text(filename);
				$row.find('[data-replace-image]').text(labels.replaceImage || 'Replace image');
				let previewUrl = attachment.url;

				if (attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url) {
					previewUrl = attachment.sizes.thumbnail.url;
				}

				$row.find('[data-image-id]').val(attachment.id).trigger('change');
				$preview
					.addClass('has-image')
					.attr('aria-label', labels.changeImage || 'Change image')
					.attr('title', labels.changeImage || 'Change image')
					.html(
						'<img src="' + previewUrl + '" alt="" />' +
						'<span class="wp-top-info-bar-image-row__preview-plus" aria-hidden="true">+</span>' +
						'<span class="wp-top-info-bar-image-row__preview-edit" aria-hidden="true"></span>'
					);
			});

			frame.open();
		});
	}

	function bindRemoveRow($row) {
		$row.find('[data-remove-image-row]').off('click').on('click', function () {
			const $box = $row.closest('.wp-top-info-bar-admin');
			$row.remove();
			$box.trigger('wpTopInfoBarImagesChanged');
		});
	}

	// A delay only matters with an animation: without one the delay field is
	// greyed out, and keeps its value (readonly fields are still submitted).
	function syncDelay($animation, $delay, $wrap) {
		const off = 'none' === $animation.val();

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
		const $delay = $row.find('input[name$="[delay]"]');

		bindImagePicker($row);
		bindRemoveRow($row);
		bindDelay($row.find('select[name$="[animation]"]'), $delay, $delay.closest('.wp-top-info-bar-image-row__field'));
	}

	$(function () {
		const editorId = 'wp-top-info-bar-message';
		const $message = $('#' + editorId);
		let editorStarted = false;
		function startTextEditor() {
			if (editorStarted || !$message.is(':visible') || !window.wp || !wp.editor || !wp.editor.initialize) return;
			editorStarted = true;
			const palette = Array.isArray(config.textColors) ? config.textColors : [];
			wp.editor.initialize(editorId, {
				tinymce: window.tinymce ? {
					toolbar1: 'formatselect,bold,italic,underline,forecolor,alignleft,aligncenter,alignright,bullist,numlist,link,unlink,removeformat,undo,redo',
					toolbar2: '',
					block_formats: 'Paragraph=p;Heading 1=h1;Heading 2=h2;Heading 3=h3;Heading 4=h4;Heading 5=h5;Heading 6=h6',
					height: 160,
					forecolor_map: palette,
					forecolor_cols: 8,
					forecolor_rows: Math.ceil((palette.length / 2 + 1) / 8),
					setup: function (instance) {
						instance.on('change input keyup undo redo', function () {
							instance.save();
							$message.trigger('change');
						});
					}
				} : false,
				quicktags: {buttons: 'strong,em,link,ul,ol,li'},
				mediaButtons: false
			});
		}
		$('.wp-top-info-bar-admin').on('wpTopInfoBarPageChange', startTextEditor);
		startTextEditor();

		const $list = $('[data-images-list]');
		const template = wp.template('wp-top-info-bar-image-row');
		const $textDelay = $('#wp-top-info-bar-text-delay');

		bindDelay($('#wp-top-info-bar-animation'), $textDelay, $textDelay.closest('tr'));

		$list.find('[data-image-row]').each(function () {
			bindRow($(this));
		});

		$('[data-add-image-row]').on('click', function () {
			const index = getNextIndex($list);
			const html = template({ index: index, optionName: optionName });
			const $row = $(html);

			$list.append($row);
			bindRow($row);
			$list.closest('.wp-top-info-bar-admin').trigger('wpTopInfoBarImagesChanged');
		});
	});
}(jQuery));

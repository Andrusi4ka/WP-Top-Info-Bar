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
				let previewUrl = attachment.url;

				if (attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url) {
					previewUrl = attachment.sizes.thumbnail.url;
				}

				$row.find('[data-image-id]').val(attachment.id);
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
			$row.remove();
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
		});
	});
}(jQuery));

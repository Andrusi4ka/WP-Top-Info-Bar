<?php

if (! defined('ABSPATH')) {
	exit;
}

final class WP_Top_Info_Bar_Frontend {
	private WP_Top_Info_Bar_Settings $settings;

	public function __construct(WP_Top_Info_Bar_Settings $settings) {
		$this->settings = $settings;

		add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
		add_action('wp_body_open', array($this, 'render_info_bar'));
	}

	public function enqueue_frontend_assets(): void {
		$settings = $this->settings->get_settings();
		$images = is_array($settings['images']) ? $settings['images'] : array();
		$has_message = '' !== trim($settings['message']);

		if (empty($settings['enabled']) || (! $has_message && empty($images))) {
			return;
		}

		wp_enqueue_style(
			'wp-top-info-bar-frontend',
			WP_TOP_INFO_BAR_URL . 'assets/css/frontend.css',
			array(),
			WP_TOP_INFO_BAR_VERSION
		);
	}

	public function render_info_bar(): void {
		$settings = $this->settings->get_settings();
		$images = is_array($settings['images']) ? $settings['images'] : array();
		$has_message = '' !== trim($settings['message']);
		$left_images = $this->filter_images_by_position($images, 'left');
		$right_images = $this->filter_images_by_position($images, 'right');
		$center_top_images = $this->filter_images_by_position($images, 'center-top');
		$center_bottom_images = $this->filter_images_by_position($images, 'center-bottom');

		if (empty($settings['enabled']) || (! $has_message && empty($left_images) && empty($right_images) && empty($center_top_images) && empty($center_bottom_images))) {
			return;
		}

		$attributes = array(
			'class="wp-top-info-bar' . $this->build_class_suffix($settings['custom_class']) . '"',
			'style="' . esc_attr($this->build_style_attribute($settings)) . '"',
		);

		if ('' !== $settings['custom_id']) {
			$attributes[] = 'id="' . esc_attr($settings['custom_id']) . '"';
		}

		$content_classes = 'wp-top-info-bar__content' . $this->build_content_animation_class_suffix($settings['animation']);
		$text_classes = 'wp-top-info-bar__inner' . $this->build_text_animation_class_suffix($settings['animation']);
		$text_style_attribute = $this->build_text_style_attribute($settings);

		echo '<div ' . implode(' ', $attributes) . '>';
		echo '<div class="' . esc_attr($content_classes) . '">';
		echo '<div class="wp-top-info-bar__group wp-top-info-bar__group--left">';
		$this->render_images($left_images);
		echo '</div>';

		echo '<div class="wp-top-info-bar__center">';

		if (! empty($center_top_images)) {
			echo '<div class="wp-top-info-bar__center-images wp-top-info-bar__center-images--top">';
			$this->render_images($center_top_images);
			echo '</div>';
		}

		if ($has_message) {
			echo '<div class="' . esc_attr($text_classes) . '" style="' . esc_attr($text_style_attribute) . '">' . nl2br(esc_html($settings['message'])) . '</div>';
		}

		if (! empty($center_bottom_images)) {
			echo '<div class="wp-top-info-bar__center-images wp-top-info-bar__center-images--bottom">';
			$this->render_images($center_bottom_images);
			echo '</div>';
		}

		echo '</div>';

		echo '<div class="wp-top-info-bar__group wp-top-info-bar__group--right">';
		$this->render_images($right_images);
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}

	private function build_class_suffix(string $custom_class): string {
		return '' === $custom_class ? '' : ' ' . $custom_class;
	}

	private function build_style_attribute(array $settings): string {
		$styles = array(
			'--wp-top-info-bar-background-color:' . $settings['background_color'],
			'--wp-top-info-bar-text-color:' . $settings['text_color'],
			'--wp-top-info-bar-padding:' . $settings['padding'],
			'--wp-top-info-bar-font-size:' . $settings['font_size']
		);

		if ('' !== $settings['margin']) {
			$styles[] = '--wp-top-info-bar-margin:' . $settings['margin'];
		}

		if (! empty($settings['font_size_tablet'])) {
			$styles[] = '--wp-top-info-bar-font-size-tablet:' . $settings['font_size_tablet'];
		}

		if (! empty($settings['font_size_mobile'])) {
			$styles[] = '--wp-top-info-bar-font-size-mobile:' . $settings['font_size_mobile'];
		}

		if (! empty($settings['padding_tablet'])) {
			$styles[] = '--wp-top-info-bar-padding-tablet:' . $settings['padding_tablet'];
		}

		if (! empty($settings['padding_mobile'])) {
			$styles[] = '--wp-top-info-bar-padding-mobile:' . $settings['padding_mobile'];
		}

		if (! empty($settings['margin_tablet'])) {
			$styles[] = '--wp-top-info-bar-margin-tablet:' . $settings['margin_tablet'];
		}

		if (! empty($settings['margin_mobile'])) {
			$styles[] = '--wp-top-info-bar-margin-mobile:' . $settings['margin_mobile'];
		}

		return implode(';', $styles) . ';';
	}

	private function build_text_style_attribute(array $settings): string {
		$styles = array();

		if (! empty($settings['text_delay'])) {
			$styles[] = '--wp-top-info-bar-text-delay:' . $settings['text_delay'];
		}

		return empty($styles) ? '' : implode(';', $styles) . ';';
	}

	private function build_content_animation_class_suffix(string $animation): string {
		if ('page-left' === $animation) {
			return ' wp-top-info-bar__content--page-left';
		}

		if ('page-right' === $animation) {
			return ' wp-top-info-bar__content--page-right';
		}

		return '';
	}

	private function build_text_animation_class_suffix(string $animation): string {
		if ('fade-in' === $animation) {
			return ' wp-top-info-bar__inner--fade-in';
		}

		if ('slide-up' === $animation) {
			return ' wp-top-info-bar__inner--slide-up';
		}

		if ('from-left' === $animation) {
			return ' wp-top-info-bar__inner--from-left';
		}

		if ('from-right' === $animation) {
			return ' wp-top-info-bar__inner--from-right';
		}

		if ('blink' === $animation) {
			return ' wp-top-info-bar__inner--blink';
		}

		if ('scale-pulse' === $animation) {
			return ' wp-top-info-bar__inner--scale-pulse';
		}

		return '';
	}

	private function filter_images_by_position(array $images, string $position): array {
		return array_values(
			array_filter(
				$images,
				static function ($image) use ($position): bool {
					return is_array($image) && ($image['position'] ?? 'left') === $position;
				}
			)
		);
	}

	private function render_images(array $images): void {
		foreach ($images as $image) {
			$image_id = isset($image['image_id']) ? absint($image['image_id']) : 0;

			if ($image_id <= 0) {
				continue;
			}

			$image_url = wp_get_attachment_image_url($image_id, 'full');

			if (! $image_url) {
				continue;
			}

			$image_url = set_url_scheme($image_url, is_ssl() ? 'https' : 'http');

			$styles = array();
			$image_size = ! empty($image['size']) ? $image['size'] : '40px';
			$image_size_tablet = isset($image['size_tablet']) ? trim((string) $image['size_tablet']) : '';
			$image_size_mobile = isset($image['size_mobile']) ? trim((string) $image['size_mobile']) : '';
			$image_animation = isset($image['animation']) ? (string) $image['animation'] : 'none';
			$image_delay = isset($image['delay']) ? trim((string) $image['delay']) : '';
			$image_margin_tablet = isset($image['margin_tablet']) ? trim((string) $image['margin_tablet']) : '';
			$image_margin_mobile = isset($image['margin_mobile']) ? trim((string) $image['margin_mobile']) : '';

			$styles[] = '--wp-top-info-bar-image-size:' . $image_size;

			if (! empty($image['margin'])) {
				$styles[] = '--wp-top-info-bar-image-margin:' . $image['margin'];
			}

			if ('' !== $image_size_tablet) {
				$styles[] = '--wp-top-info-bar-image-size-tablet:' . $image_size_tablet;
			}

			if ('' !== $image_size_mobile) {
				$styles[] = '--wp-top-info-bar-image-size-mobile:' . $image_size_mobile;
			}

			if ('' !== $image_margin_tablet) {
				$styles[] = '--wp-top-info-bar-image-margin-tablet:' . $image_margin_tablet;
			}

			if ('' !== $image_margin_mobile) {
				$styles[] = '--wp-top-info-bar-image-margin-mobile:' . $image_margin_mobile;
			}

			if ('' !== $image_delay) {
				$styles[] = '--wp-top-info-bar-image-delay:' . $image_delay;
			}

			$style_attribute = empty($styles) ? '' : ' style="' . esc_attr(implode(';', $styles) . ';') . '"';
			$class_attribute = 'wp-top-info-bar__image';

			$animation_class_suffix = $this->build_image_animation_class_suffix($image_animation);

			if ('' !== $animation_class_suffix) {
				$class_attribute .= $animation_class_suffix;
			}

			if (! empty($image['custom_class'])) {
				$class_attribute .= ' ' . $image['custom_class'];
			}

			echo '<img src="' . esc_url($image_url) . '" class="' . esc_attr($class_attribute) . '"' . $style_attribute . ' alt="" />';
		}
	}

	private function build_image_animation_class_suffix(string $animation): string {
		if ('rotate-swing-top' === $animation) {
			return ' wp-top-info-bar__image--rotate-swing-top';
		}

		if ('rotate-swing-bottom' === $animation) {
			return ' wp-top-info-bar__image--rotate-swing-bottom';
		}

		if ('slide-sideways' === $animation) {
			return ' wp-top-info-bar__image--slide-sideways';
		}

		if ('blink' === $animation) {
			return ' wp-top-info-bar__image--blink';
		}

		if ('scale-pulse' === $animation) {
			return ' wp-top-info-bar__image--scale-pulse';
		}

		return '';
	}
}

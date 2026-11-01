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
		add_shortcode('wp_top_info_bar', array($this, 'shortcode'));
	}

	public function enqueue_frontend_assets(): void {
		$settings = $this->settings->get_settings();
		$images = is_array($settings['images']) ? $settings['images'] : array();
		$has_buttons = false;
		foreach ((new WP_Top_Info_Bar_Sanitizer())->sanitize_button_elements($settings) as $element) { if ('' !== $element['button_text']) { $has_buttons = true; } }
		$has_message = false;
		foreach ((new WP_Top_Info_Bar_Sanitizer())->sanitize_text_elements($settings) as $element) { if ('' !== $element['message']) { $has_message = true; } }

		global $post;
		$has_shortcode = $post && has_shortcode($post->post_content, 'wp_top_info_bar');
		if (! $has_shortcode && (empty($settings['enabled']) || (! $has_message && empty($images) && ! $has_buttons))) {
			return;
		}

		wp_enqueue_style(
			'wp-top-info-bar-frontend',
			WP_TOP_INFO_BAR_URL . 'assets/css/frontend.css',
			array(),
			(string) filemtime(WP_TOP_INFO_BAR_PATH . 'assets/css/frontend.css')
		);
		wp_enqueue_script('wp-top-info-bar-frontend', WP_TOP_INFO_BAR_URL . 'assets/js/frontend.js', array(), (string) filemtime(WP_TOP_INFO_BAR_PATH . 'assets/js/frontend.js'), true);
	}

	public function render_info_bar(): void {
		$settings = $this->settings->get_settings();
		$this->render_settings($settings);
	}

	public function shortcode($atts): string {
		$atts = shortcode_atts(array('id' => 0), $atts, 'wp_top_info_bar');
		$id = absint($atts['id']);
		$post = $id ? get_post($id) : null;
		if (! $post || WP_Top_Info_Bar_Settings::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status || '' !== $post->post_password) {
			return '';
		}
		$settings = (new WP_Top_Info_Bar_Settings($id))->get_settings();
		if (empty($settings['enabled'])) {
			return '';
		}
		wp_enqueue_style('wp-top-info-bar-frontend', WP_TOP_INFO_BAR_URL . 'assets/css/frontend.css', array(), (string) filemtime(WP_TOP_INFO_BAR_PATH . 'assets/css/frontend.css'));
		wp_enqueue_script('wp-top-info-bar-frontend', WP_TOP_INFO_BAR_URL . 'assets/js/frontend.js', array(), (string) filemtime(WP_TOP_INFO_BAR_PATH . 'assets/js/frontend.js'), true);
		ob_start();
		$this->render_settings($settings);
		return (string) ob_get_clean();
	}

	/** Shared rendering for the site and the unsaved settings preview. */
	public function render_settings(array $settings): void {
		$images = is_array($settings['images']) ? $settings['images'] : array();
		$defaults = (new WP_Top_Info_Bar_Sanitizer())->sanitize_image_defaults($settings['image_defaults'] ?? array());
		foreach ($images as $index => &$image) {
			if (is_array($image)) {
				$mask = (new WP_Top_Info_Bar_Sanitizer())->image_overrides($image, $defaults);
				$resolved = $defaults;
				foreach (WP_Top_Info_Bar_Sanitizer::IMAGE_OVERRIDE_GROUPS as $group => $fields) {
					if (! empty($mask[$group])) foreach ($fields as $key) { if (array_key_exists($key, $image)) $resolved[$key] = $image[$key]; }
				}
				foreach (array('position', 'position_tablet', 'position_mobile') as $key) { $resolved[$key] = $image[$key] ?? ('position' === $key ? 'left' : ''); }
				$image = array_merge($resolved, array('image_id' => $image['image_id'] ?? 0));
				$image['_order'] = $index;
			}
		}
		unset($image);
		$has_buttons = false;
		foreach ((new WP_Top_Info_Bar_Sanitizer())->sanitize_button_elements($settings) as $element) { if ('' !== $element['button_text']) { $has_buttons = true; } }
		$texts = (new WP_Top_Info_Bar_Sanitizer())->sanitize_text_elements($settings);
		$has_message = false;
		foreach ($texts as $element) { if ('' !== $element['message']) { $has_message = true; } }
		$left_images = $this->filter_images_by_position($images, 'left');
		$right_images = $this->filter_images_by_position($images, 'right');
		$center_top_images = $this->filter_images_by_position($images, 'center-top');
		$center_bottom_images = $this->filter_images_by_position($images, 'center-bottom');

		if (empty($settings['enabled']) || (! $has_buttons && ! $has_message && empty($left_images) && empty($right_images) && empty($center_top_images) && empty($center_bottom_images))) {
			return;
		}

		$attributes = array(
			'class="wp-top-info-bar' . $this->build_class_suffix($settings['custom_class']) . '"',
			'style="' . esc_attr($this->build_style_attribute($settings)) . '"',
		);

		if ('' !== $settings['custom_id']) {
			$attributes[] = 'id="' . esc_attr($settings['custom_id']) . '"';
		}

		$animation = isset($settings['animation_enabled']) && empty($settings['animation_enabled']) ? 'none' : $settings['animation'];
		$content_classes = ('marquee' === $animation ? 'wp-top-info-bar__content wp-top-info-bar__content--marquee' : 'wp-top-info-bar__content') . $this->build_content_animation_class_suffix($animation);
		if (! empty($settings['layout_order']) || ! empty($settings['zone_settings'])) { $content_classes .= ' wp-top-info-bar__content--ordered'; }
		$text_classes = 'wp-top-info-bar__inner' . $this->build_text_animation_class_suffix($animation);
		$text_style_attribute = $this->build_text_style_attribute($settings);
		$text_style_attribute .= ';--wp-top-info-bar-marquee-speed:' . max(1, min(1000, (float) ($settings['marquee_speed'] ?? 60)));

		echo '<div ' . implode(' ', $attributes) . '>';
		 $animation_devices = array();
		foreach (array('desktop' => '', 'tablet' => '_tablet', 'mobile' => '_mobile') as $device => $suffix) {
			$animation_devices[$device] = array('animation' => $settings['animation' . $suffix] ?? '', 'delay' => $settings['text_delay' . $suffix] ?? '', 'speed' => $settings['marquee_speed' . $suffix] ?? '');
		}
		echo '<div data-animation-enabled="' . (empty($settings['animation_enabled']) ? '0' : '1') . '" data-animation-devices="' . esc_attr(wp_json_encode($animation_devices)) . '" class="' . esc_attr($content_classes) . '"' . (' style="' . esc_attr($text_style_attribute) . '"') . '>';
		if ((! empty($settings['layout_order']) && is_array($settings['layout_order'])) || ! empty($settings['zone_settings'])) {
			$elements = array();
			foreach ($images as $index => $image) {
				ob_start(); $this->render_images(array($image)); $elements['image:' . $index] = ob_get_clean();
			}
			foreach ($texts as $id => $element) { $elements['text:' . $id] = $this->render_text_element($element, $text_classes, $text_style_attribute); }
			if (isset($texts['legacy'])) { $elements['text'] = ''; }
			foreach ((new WP_Top_Info_Bar_Sanitizer())->sanitize_button_elements($settings) as $id => $element) {
				ob_start(); $this->render_button($element, $element['button_position']); $elements['button:' . $id] = ob_get_clean();
			}
			if (! empty($settings['layout_zones']) || ! empty($settings['zone_settings'])) {
				$buckets = array('left' => '', 'center' => '', 'right' => '');
				$order = array_unique(array_merge($settings['layout_order'], array_keys($elements)));
				foreach ($order as $key) {
					if (! is_string($key) || ! isset($elements[$key])) { continue; }
					$zone = $settings['layout_zones'][$key] ?? 'center';
					if (! isset($buckets[$zone])) { $zone = 'center'; }
					$buckets[$zone] .= $elements[$key];
				}
				foreach ($buckets as $zone => $html) { echo '<div class="wp-top-info-bar__layout-zone wp-top-info-bar__layout-zone--' . esc_attr($zone) . '" style="' . esc_attr($this->zone_style($settings, $zone)) . '">' . $html . '</div>'; }
				echo '</div></div>'; return;
			}
			foreach ($settings['layout_order'] as $key) {
				if ('button' === $key && isset($elements['button:legacy'])) { $key = 'button:legacy'; }
				if ('text' === $key && isset($texts['legacy'])) { $key = 'text:legacy'; }
				if (is_string($key) && isset($elements[$key])) { echo $elements[$key]; unset($elements[$key]); } // Already escaped by renderers.
			}
			foreach ($elements as $html) { echo $html; } // Newly added elements follow the saved layout.
			echo '</div></div>';
			return;
		}
		echo '<div class="wp-top-info-bar__group wp-top-info-bar__group--left">';
		$this->render_buttons_at($settings, 'screen-left');
		$this->render_images($left_images);
		$this->render_buttons_at($settings, 'text-left');
		echo '</div>';

		echo '<div class="wp-top-info-bar__center">';

		echo '<div class="wp-top-info-bar__center-images wp-top-info-bar__center-images--top">';
		$this->render_images($center_top_images);
		$this->render_buttons_at($settings, 'center-top');
		echo '</div>';

		if ($has_message) {
			foreach ($texts as $element) { echo $this->render_text_element($element, $text_classes, $text_style_attribute); }
		}

		echo '<div class="wp-top-info-bar__center-images wp-top-info-bar__center-images--bottom">';
		$this->render_images($center_bottom_images);
		$this->render_buttons_at($settings, 'center-bottom');
		echo '</div>';

		echo '</div>';

		echo '<div class="wp-top-info-bar__group wp-top-info-bar__group--right">';
		$this->render_buttons_at($settings, 'text-right');
		$this->render_images($right_images);
		$this->render_buttons_at($settings, 'screen-right');
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}

	private function zone_style(array $settings, string $zone): string {
		$zones = (new WP_Top_Info_Bar_Sanitizer())->sanitize_zone_settings($settings['zone_settings'] ?? array());
		$values = $zones[$zone] ?? array();
		$style = 'background-color:' . ($values['background_color'] ?? 'transparent');
		foreach (array('padding', 'margin') as $key) {
			foreach (array('', '_tablet', '_mobile') as $suffix) {
				if ('' !== ($values[$key . $suffix] ?? '')) { $style .= ';--top-zone-' . $key . str_replace('_', '-', $suffix) . ':' . $values[$key . $suffix]; }
			}
		}
		return $style;
	}

	private function render_text_element(array $element, string $classes, string $style): string {
		if ('' === $element['message']) { return ''; }
		$style .= ';--wp-top-info-bar-font-size:' . $element['font_size'];
		$style .= ';--wp-top-info-bar-font-size-tablet:' . ('' !== $element['font_size_tablet'] ? $element['font_size_tablet'] : 'var(--wp-top-info-bar-font-size)');
		$style .= ';--wp-top-info-bar-font-size-mobile:' . ('' !== $element['font_size_mobile'] ? $element['font_size_mobile'] : 'var(--wp-top-info-bar-font-size-tablet)');
		$style .= ';color:' . $element['text_color'];
		return '<div class="' . esc_attr($classes) . '" style="' . esc_attr($style) . '">' . wpautop($element['message']) . '</div>';
	}

	private function render_buttons_at(array $settings, string $position): void {
		foreach ((new WP_Top_Info_Bar_Sanitizer())->sanitize_button_elements($settings) as $element) { $this->render_button($element, $position); }
	}

	private function render_button(array $settings, string $position): void {
		if ('' === trim((string) ($settings['button_text'] ?? '')) || ($settings['button_position'] ?? 'text-right') !== $position) { return; }
		$class = 'wp-top-info-bar__button wp-top-info-bar__button--' . ($settings['button_size'] ?? 'default') . ' wp-top-info-bar__button--' . ($settings['button_shape'] ?? 'square') . ' wp-top-info-bar__button--' . ($settings['button_style'] ?? 'filled') . ' wp-top-info-bar__button--' . $position;
		$style = '--top-button-color:' . ($settings['button_color'] ?? '#2271b1') . ';--top-button-text:' . ($settings['button_text_color'] ?? '#ffffff');
		$url = $settings['button_url'] ?? '';
		$tag = '' !== $url ? 'a' : 'span';
		$attributes = '' !== $url ? ' href="' . esc_url($url, array('http', 'https', 'mailto', 'tel')) . '"' : '';
		if ('' !== $url && ! empty($settings['button_new_tab'])) { $attributes .= ' target="_blank" rel="noopener noreferrer"'; }
		echo '<' . $tag . ' class="' . esc_attr($class) . '" style="' . esc_attr($style) . '"' . $attributes . '>' . esc_html($settings['button_text']) . '</' . $tag . '>';
	}

	private function build_class_suffix(string $custom_class): string {
		return '' === $custom_class ? '' : ' ' . $custom_class;
	}

	private function build_style_attribute(array $settings): string {
		foreach (array('padding', 'margin') as $key) {
			if (isset($settings[$key . '_enabled']) && empty($settings[$key . '_enabled'])) {
				foreach (array('', '_tablet', '_mobile') as $suffix) { $settings[$key . $suffix] = '0px'; }
			}
		}
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
		if (in_array($animation, array('fade-in', 'slide-up', 'from-left', 'from-right', 'blink', 'scale-pulse'), true)) {
			return ' wp-top-info-bar__content--' . $animation;
		}
		if ('page-left' === $animation) {
			return ' wp-top-info-bar__content--page-left';
		}

		if ('page-right' === $animation) {
			return ' wp-top-info-bar__content--page-right';
		}

		return '';
	}

	private function build_text_animation_class_suffix(string $animation): string {
		return 'marquee' === $animation ? ' wp-top-info-bar__inner--marquee' : '';
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
			$margin_enabled = WP_Top_Info_Bar_Sanitizer::image_option_enabled($image, 'margin');
			$image_size = ! empty($image['size']) ? $image['size'] : '40px';
			$image_size_tablet = isset($image['size_tablet']) ? trim((string) $image['size_tablet']) : '';
			$image_size_mobile = isset($image['size_mobile']) ? trim((string) $image['size_mobile']) : '';
			$image_animation = isset($image['animation']) ? (string) $image['animation'] : 'none';
			if (! WP_Top_Info_Bar_Sanitizer::image_option_enabled($image, 'animation')) { $image_animation = 'none'; }
			$image_delay = isset($image['delay']) ? trim((string) $image['delay']) : '';
			if ('none' === $image_animation) { $image_delay = ''; }
			$image_margin_tablet = isset($image['margin_tablet']) ? trim((string) $image['margin_tablet']) : '';
			$image_margin_mobile = isset($image['margin_mobile']) ? trim((string) $image['margin_mobile']) : '';

			$styles[] = '--wp-top-info-bar-image-size:' . $image_size;

			if ($margin_enabled && ! empty($image['margin'])) {
				$styles[] = '--wp-top-info-bar-image-margin:' . $image['margin'];
			}

			if ('' !== $image_size_tablet) {
				$styles[] = '--wp-top-info-bar-image-size-tablet:' . $image_size_tablet;
			}

			if ('' !== $image_size_mobile) {
				$styles[] = '--wp-top-info-bar-image-size-mobile:' . $image_size_mobile;
			}

			if ($margin_enabled && '' !== $image_margin_tablet) {
				$styles[] = '--wp-top-info-bar-image-margin-tablet:' . $image_margin_tablet;
			}

			if ($margin_enabled && '' !== $image_margin_mobile) {
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

			if (WP_Top_Info_Bar_Sanitizer::image_option_enabled($image, 'custom_class') && ! empty($image['custom_class'])) {
				$class_attribute .= ' ' . $image['custom_class'];
			}

			echo '<img src="' . esc_url($image_url) . '" class="' . esc_attr($class_attribute) . '" data-position="' . esc_attr($image['position'] ?? 'left') . '" data-position-tablet="' . esc_attr($image['position_tablet'] ?? '') . '" data-position-mobile="' . esc_attr($image['position_mobile'] ?? '') . '" data-image-order="' . esc_attr((string) ($image['_order'] ?? 0)) . '"' . $style_attribute . ' alt="" />';
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

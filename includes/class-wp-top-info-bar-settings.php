<?php

if (! defined('ABSPATH')) {
	exit;
}

final class WP_Top_Info_Bar_Settings {
	public const OPTION_NAME = 'wp_top_info_bar_settings';
	public const POST_TYPE = 'wp_top_info_bar';
	public const META_KEY = '_wp_top_info_bar_settings';
	public const GLOBAL_POST_OPTION = 'wp_top_info_bar_global_post';
	private int $post_id;

	public function __construct(int $post_id = 0) {
		$this->post_id = $post_id;
	}
	public const MENU_SLUG = 'wp-top-info-bar';
	public const SETTINGS_GROUP = 'wp_top_info_bar_group';
	public const SETTINGS_SECTION = 'wp_top_info_bar_main_section';

	public function get_defaults(): array {
		return array(
			'enabled'          => 0,
			'message'          => '',
			'layout_order' => array(),
			'layout_zones' => array(),
			'zone_settings' => array(),
			'button_text' => '', 'button_size' => 'default', 'button_shape' => 'square',
			'button_style' => 'filled', 'button_color' => '#2271b1', 'button_text_color' => '#ffffff',
			'button_url' => '', 'button_new_tab' => 0, 'button_position' => 'text-right',
			'background_color' => '#1f2937',
			'border_enabled' => 0, 'border_width' => 2, 'border_width_tablet' => '', 'border_width_mobile' => '', 'border_color' => '#ffffff',
			'shadow_enabled' => 0, 'shadow_x' => 0, 'shadow_y' => 8, 'shadow_blur' => 20, 'shadow_spread' => 0, 'shadow_color' => 'rgba(0,0,0,0.3)',
			'text_color'       => '#ffffff',
			'animation'        => 'none',
			'animation_enabled' => 0,
			'text_delay'       => '',
			'marquee_speed'    => 60,
			'animation_tablet' => '', 'animation_mobile' => '',
			'text_delay_tablet' => '', 'text_delay_mobile' => '',
			'marquee_speed_tablet' => '', 'marquee_speed_mobile' => '',
			'font_size'        => '14px',
			'font_size_tablet' => '',
			'font_size_mobile' => '',
			'padding_enabled' => 0,
			'margin_enabled' => 0,
			'padding'          => '12px 16px',
			'padding_tablet'   => '',
			'padding_mobile'   => '',
			'margin'           => '',
			'margin_tablet'    => '',
			'margin_mobile'    => '',
			'custom_class'     => '',
			'custom_id'        => '',
			'images'           => array(),
			'image_defaults'   => array(),
		);
	}

	public function get_settings(): array {
		$post_id = $this->post_id ?: absint(get_option(self::GLOBAL_POST_OPTION, 0));
		$settings = $post_id ? get_post_meta($post_id, self::META_KEY, true) : get_option(self::OPTION_NAME, array());

		if (! is_array($settings)) { $settings = array(); }
		// Preserve spacing for saved bars created before the switches existed.
		if (! empty($settings)) {
			foreach (array('padding', 'margin') as $key) {
				if (! array_key_exists($key . '_enabled', $settings)) { $settings[$key . '_enabled'] = 1; }
			}
		}
		// First opening of the gallery: use the first legacy image as the
		// common style; retain different existing images as individual styles.
		if (! array_key_exists('image_defaults', $settings)) {
			$images = isset($settings['images']) && is_array($settings['images']) ? $settings['images'] : array();
			$sanitizer = new WP_Top_Info_Bar_Sanitizer();
			$first = reset($images);
			$settings['image_defaults'] = $sanitizer->sanitize_image_defaults(is_array($first) ? $first : array());
			foreach ($images as &$image) {
				if (is_array($image) && ! array_key_exists('override_enabled', $image)) {
					$image['override_enabled'] = $sanitizer->sanitize_image_defaults($image) === $settings['image_defaults'] ? 0 : 1;
					$image['override_initialized'] = 1;
				}
			}
			unset($image);
			$settings['images'] = $images;
		}
		if (empty($settings['image_position_independent'])) {
			foreach ($settings['images'] ?? array() as $index => $image) {
				if (is_array($image) && isset($image['override_enabled']) && empty($image['override_enabled'])) {
					foreach (array('position', 'position_tablet', 'position_mobile') as $key) {
						$settings['images'][$index][$key] = $settings['image_defaults'][$key] ?? ('position' === $key ? 'left' : '');
					}
				}
			}
			$settings['image_position_independent'] = 1;
		}

		if (! array_key_exists('animation_enabled', $settings)) {
			$settings['animation_enabled'] = isset($settings['animation']) && 'none' !== $settings['animation'] ? 1 : 0;
		}
		$settings = wp_parse_args($settings, $this->get_defaults());
		$settings['images'] = is_array($settings['images']) ? $settings['images'] : array();
		$settings['image_defaults'] = is_array($settings['image_defaults']) ? $settings['image_defaults'] : array();
		$sanitizer = new WP_Top_Info_Bar_Sanitizer();
		foreach ($settings['images'] as &$image) {
			if (is_array($image)) {
				$image['override_fields'] = $sanitizer->image_overrides($image, $settings['image_defaults']);
				$image['override_enabled'] = in_array(1, $image['override_fields'], true) ? 1 : 0;
			}
		}
		unset($image);
		// Drafts, scheduled, private and trashed bars must not appear publicly.
		if (! $this->post_id && $post_id) {
			$post = get_post($post_id);
			if (! $post || self::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status || '' !== $post->post_password) {
				$settings['enabled'] = 0;
			}
		}
		$settings['buttons'] = $sanitizer->sanitize_button_elements($settings);
		if (isset($settings['buttons']['legacy']) && is_array($settings['layout_order'])) {
			$settings['layout_order'] = array_map(static function ($key) { return 'button' === $key ? 'button:legacy' : $key; }, $settings['layout_order']);
		}
		$settings['texts'] = $sanitizer->sanitize_text_elements($settings);
		if (isset($settings['texts']['legacy']) && is_array($settings['layout_order'])) {
			$settings['layout_order'] = array_map(static function ($key) { return 'text' === $key ? 'text:legacy' : $key; }, $settings['layout_order']);
		}
		$settings['images'] = $sanitizer->independent_images($settings);
		$settings['images_independent'] = 1;
		return $settings;
	}
}

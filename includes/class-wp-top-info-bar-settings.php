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
			'background_color' => '#1f2937',
			'text_color'       => '#ffffff',
			'animation'        => 'none',
			'text_delay'       => '',
			'font_size'        => '14px',
			'font_size_tablet' => '',
			'font_size_mobile' => '',
			'padding'          => '12px 16px',
			'padding_tablet'   => '',
			'padding_mobile'   => '',
			'margin'           => '',
			'margin_tablet'    => '',
			'margin_mobile'    => '',
			'custom_class'     => '',
			'custom_id'        => '',
			'images'           => array(),
		);
	}

	public function get_settings(): array {
		$post_id = $this->post_id ?: absint(get_option(self::GLOBAL_POST_OPTION, 0));
		$settings = $post_id ? get_post_meta($post_id, self::META_KEY, true) : get_option(self::OPTION_NAME, array());

		if (! is_array($settings)) {
			return $this->get_defaults();
		}

		$settings = wp_parse_args($settings, $this->get_defaults());
		// Drafts, scheduled, private and trashed bars must not appear publicly.
		if (! $this->post_id && $post_id) {
			$post = get_post($post_id);
			if (! $post || self::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status || '' !== $post->post_password) {
				$settings['enabled'] = 0;
			}
		}
		return $settings;
	}
}

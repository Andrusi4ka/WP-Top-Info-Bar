<?php

if (! defined('ABSPATH')) {
	exit;
}

final class WP_Top_Info_Bar_Settings {
	public const OPTION_NAME = 'wp_top_info_bar_settings';
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
		$settings = get_option(self::OPTION_NAME, array());

		if (! is_array($settings)) {
			return $this->get_defaults();
		}

		return wp_parse_args($settings, $this->get_defaults());
	}
}

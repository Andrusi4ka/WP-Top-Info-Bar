<?php

if (! defined('ABSPATH')) {
	exit;
}

final class WP_Top_Info_Bar {
	private static ?WP_Top_Info_Bar $instance = null;

	private WP_Top_Info_Bar_Settings $settings;
	private WP_Top_Info_Bar_Sanitizer $sanitizer;
	private WP_Top_Info_Bar_Admin $admin;
	private WP_Top_Info_Bar_Frontend $frontend;

	private function __construct() {
		$this->settings = new WP_Top_Info_Bar_Settings();
		$this->sanitizer = new WP_Top_Info_Bar_Sanitizer();
		$this->admin = new WP_Top_Info_Bar_Admin($this->settings, $this->sanitizer);
		$this->frontend = new WP_Top_Info_Bar_Frontend($this->settings);
	}

	public static function instance(): WP_Top_Info_Bar {
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function activate(): void {
		$settings = new WP_Top_Info_Bar_Settings();

		if (! get_option(WP_Top_Info_Bar_Settings::OPTION_NAME)) {
			add_option(WP_Top_Info_Bar_Settings::OPTION_NAME, $settings->get_defaults());
		}
	}
}

<?php
/**
 * Plugin Name:       WP Top Info Bar
 * Plugin URI:        https://github.com/Andrusi4ka/WP-Top-Info-Bar
 * Description:       Adds a configurable information bar at the very top of the site.
 * Version:           3.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Relevant AS (Andrii Boiko)
 * Author URI:        https://relevant.no
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-top-info-bar
 * Domain Path:       /languages
 * Update URI:        https://github.com/Andrusi4ka/WP-Top-Info-Bar
 */

// "Update URI": updates come from this plugin's GitHub releases
// (wp_top_info_bar_updates()), never from a wordpress.org plugin of the same folder name.

if (! defined('ABSPATH')) {
	exit;
}

define('WP_TOP_INFO_BAR_VERSION', '3.0.0');
define('WP_TOP_INFO_BAR_REPOSITORY', 'https://github.com/Andrusi4ka/WP-Top-Info-Bar/');
define('WP_TOP_INFO_BAR_FILE', __FILE__);
define('WP_TOP_INFO_BAR_PATH', plugin_dir_path(__FILE__));
define('WP_TOP_INFO_BAR_URL', plugin_dir_url(__FILE__));

require_once WP_TOP_INFO_BAR_PATH . 'includes/class-wp-top-info-bar-settings.php';
require_once WP_TOP_INFO_BAR_PATH . 'includes/class-wp-top-info-bar-sanitizer.php';
require_once WP_TOP_INFO_BAR_PATH . 'includes/class-wp-top-info-bar-admin.php';
require_once WP_TOP_INFO_BAR_PATH . 'includes/class-wp-top-info-bar-frontend.php';
require_once WP_TOP_INFO_BAR_PATH . 'includes/class-wp-top-info-bar.php';

/**
 * Updates from the GitHub releases, shown under Plugins like any other update.
 */
function wp_top_info_bar_updates() {
	$library = WP_TOP_INFO_BAR_PATH . 'vendor/plugin-update-checker/plugin-update-checker.php';
	// Only where WordPress looks for updates: the admin and its cron.
	if ((! is_admin() && ! wp_doing_cron()) || ! file_exists($library)) {
		return;
	}
	require_once $library;

	$checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(WP_TOP_INFO_BAR_REPOSITORY, WP_TOP_INFO_BAR_FILE, 'wp-top-info-bar');
	// The release's zip, which holds just the plugin (not the repository's dev files).
	$checker->getVcsApi()->enableReleaseAssets('/wp-top-info-bar\.zip($|[?&#])/i');
}
add_action('plugins_loaded', 'wp_top_info_bar_updates');

/**
 * Translations from the plugin's languages folder (wp-top-info-bar-{locale}.mo).
 */
function wp_top_info_bar_load_textdomain() {
	load_plugin_textdomain('wp-top-info-bar', false, dirname(plugin_basename(WP_TOP_INFO_BAR_FILE)) . '/languages');
}
add_action('init', 'wp_top_info_bar_load_textdomain');

register_activation_hook(__FILE__, array('WP_Top_Info_Bar', 'activate'));

WP_Top_Info_Bar::instance();

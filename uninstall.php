<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

$option_name = 'wp_top_info_bar_settings';

function wp_top_info_bar_delete_data() {
	global $wpdb;
	// Every bar whatever its status (post_status=any leaves out trash and auto-drafts).
	$ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'wp_top_info_bar'));
	foreach ($ids as $id) {
		wp_delete_post((int) $id, true);
	}
	delete_option('wp_top_info_bar_settings');
	delete_option('wp_top_info_bar_global_post');
}

if (is_multisite()) {
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ($site_ids as $site_id) {
		switch_to_blog((int) $site_id);
		wp_top_info_bar_delete_data();
		restore_current_blog();
	}

	delete_site_option($option_name);
} else {
	wp_top_info_bar_delete_data();
}

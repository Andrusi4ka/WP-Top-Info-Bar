<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

$option_name = 'wp_top_info_bar_settings';

function wp_top_info_bar_delete_data() {
	foreach (get_posts(array('post_type' => 'wp_top_info_bar', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids')) as $id) {
		wp_delete_post($id, true);
	}
	// Trashed posts are excluded by post_status=any.
	foreach (get_posts(array('post_type' => 'wp_top_info_bar', 'post_status' => 'trash', 'numberposts' => -1, 'fields' => 'ids')) as $id) {
		wp_delete_post($id, true);
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

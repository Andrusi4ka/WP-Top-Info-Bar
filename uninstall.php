<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

$option_name = 'wp_top_info_bar_settings';

if (is_multisite()) {
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ($site_ids as $site_id) {
		switch_to_blog((int) $site_id);
		delete_option($option_name);
		restore_current_blog();
	}

	delete_site_option($option_name);
} else {
	delete_option($option_name);
}

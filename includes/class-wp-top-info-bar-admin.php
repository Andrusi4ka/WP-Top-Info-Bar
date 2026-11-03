<?php

if (! defined('ABSPATH')) {
	exit;
}

final class WP_Top_Info_Bar_Admin {
	private WP_Top_Info_Bar_Settings $settings;
	private WP_Top_Info_Bar_Sanitizer $sanitizer;
	private const SAVE_NONCE = 'wp_top_info_bar_save';
	private const CLONE_ACTION = 'wp_top_info_bar_clone';

	public function __construct(WP_Top_Info_Bar_Settings $settings, WP_Top_Info_Bar_Sanitizer $sanitizer) {
		$this->settings = $settings;
		$this->sanitizer = $sanitizer;

		add_action('init', array($this, 'register_post_type'));
		add_action('admin_init', array($this, 'migrate_settings'));
		// Settings fields are registered by render_settings_box(), for the bar being edited.
		add_action('add_meta_boxes_' . WP_Top_Info_Bar_Settings::POST_TYPE, array($this, 'add_meta_boxes'));
		add_action('save_post_' . WP_Top_Info_Bar_Settings::POST_TYPE, array($this, 'save'), 10, 2);
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
		add_filter('parent_file', array($this, 'parent_menu'));
		add_filter('manage_' . WP_Top_Info_Bar_Settings::POST_TYPE . '_posts_columns', array($this, 'list_columns'));
		add_action('manage_' . WP_Top_Info_Bar_Settings::POST_TYPE . '_posts_custom_column', array($this, 'list_column'), 10, 2);
		add_filter('manage_edit-' . WP_Top_Info_Bar_Settings::POST_TYPE . '_sortable_columns', array($this, 'sortable_columns'));
		add_action('pre_get_posts', array($this, 'list_order'));
		add_filter('posts_orderby', array($this, 'priority_orderby'), 10, 2);
		add_filter('wp_insert_post_data', array($this, 'save_priority'), 10, 2);
		add_action('wp_ajax_wp_top_info_bar_priority', array($this, 'ajax_priority'));
		add_action('wp_ajax_wp_top_info_bar_toggle', array($this, 'ajax_toggle'));
		add_filter('post_row_actions', array($this, 'clone_row_action'), 10, 2);
		add_action('admin_post_' . self::CLONE_ACTION, array($this, 'handle_clone'));
		add_action('admin_notices', array($this, 'clone_notice'));
	}

	public function parent_menu($parent) {
		$screen = get_current_screen();
		return $screen && WP_Top_Info_Bar_Settings::POST_TYPE === $screen->post_type ? 'edit.php?post_type=' . WP_Top_Info_Bar_Settings::POST_TYPE : $parent;
	}

	public function register_post_type(): void {
		register_post_type(WP_Top_Info_Bar_Settings::POST_TYPE, array(
			'labels' => array(
				'name' => __('Top Bars', 'wp-top-info-bar'),
				'menu_name' => __('WP Top Bar', 'wp-top-info-bar'),
				'all_items' => __('All Top Bars', 'wp-top-info-bar'),
				'add_new' => __('Add New', 'wp-top-info-bar'),
				'singular_name' => __('Top Bar', 'wp-top-info-bar'),
				'edit_item' => __('Edit Top Bar', 'wp-top-info-bar'),
				'add_new_item' => __('Add Top Bar', 'wp-top-info-bar'),
			),
			'public' => false, 'show_ui' => true, 'show_in_menu' => true,
			'menu_icon' => 'dashicons-megaphone', 'menu_position' => 26,
			'show_in_rest' => false, 'supports' => array('title'),
			'capabilities' => array(
				'edit_post' => 'manage_options', 'read_post' => 'manage_options', 'delete_post' => 'manage_options',
				'edit_posts' => 'manage_options', 'edit_others_posts' => 'manage_options', 'publish_posts' => 'manage_options',
				'read_private_posts' => 'manage_options', 'delete_posts' => 'manage_options', 'delete_private_posts' => 'manage_options',
				'delete_published_posts' => 'manage_options', 'delete_others_posts' => 'manage_options',
				'edit_private_posts' => 'manage_options', 'edit_published_posts' => 'manage_options', 'create_posts' => 'manage_options',
			),
		));
	}

	/** Copy the existing global bar once; retain the original option as a backup. */
	public function migrate_settings(): void {
		if (! current_user_can('manage_options') || get_option(WP_Top_Info_Bar_Settings::GLOBAL_POST_OPTION, 0)) {
			return;
		}
		$id = wp_insert_post(array('post_type' => WP_Top_Info_Bar_Settings::POST_TYPE, 'post_title' => __('Top Bar', 'wp-top-info-bar'), 'post_status' => 'publish'), true);
		if (is_wp_error($id) || ! $id) {
			return;
		}
		update_post_meta($id, WP_Top_Info_Bar_Settings::META_KEY, wp_slash($this->sanitizer->sanitize_settings($this->settings->get_settings())));
		update_option(WP_Top_Info_Bar_Settings::GLOBAL_POST_OPTION, $id, false);
	}

	public function add_meta_boxes(): void {
		add_meta_box('wp-top-info-bar-layout', __('Layout', 'wp-top-info-bar'), array($this, 'render_layout_box'), null, 'normal', 'high');
		add_meta_box('wp-top-info-bar-settings', __('Bar settings', 'wp-top-info-bar'), array($this, 'render_settings_box'), null, 'normal', 'default');
		add_meta_box('wp-top-info-bar-priority', __('Display priority', 'wp-top-info-bar'), array($this, 'render_priority_box'), null, 'side', 'default');
		add_meta_box('wp-top-info-bar-shortcode', __('Shortcode', 'wp-top-info-bar'), array($this, 'render_shortcode_box'), null, 'side', 'high');
	}

	private static function priority($value): int {
		return max(1, min(9999, is_scalar($value) ? absint($value) : 1));
	}

	public function render_priority_box($post): void {
		echo '<label for="wp-top-info-bar-priority">' . esc_html__('Priority', 'wp-top-info-bar') . '</label> ';
		echo '<input type="number" id="wp-top-info-bar-priority" class="small-text" name="wp_top_info_bar_priority" min="1" max="9999" step="1" value="' . esc_attr((string) self::priority($post->menu_order)) . '">';
		echo '<p class="description">' . esc_html__('Lower numbers appear higher. Only published, enabled bars are displayed.', 'wp-top-info-bar') . '</p>';
	}

	public function save_priority(array $data, array $postarr): array {
		if (WP_Top_Info_Bar_Settings::POST_TYPE !== $data['post_type'] || ! isset($_POST['wp_top_info_bar_priority'])) { return $data; }
		$nonce = $_POST[self::SAVE_NONCE . '_nonce'] ?? '';
		if (! is_string($nonce) || ! wp_verify_nonce($nonce, self::SAVE_NONCE) || ! current_user_can('manage_options')) { return $data; }
		if (! empty($postarr['ID']) && ! current_user_can('edit_post', (int) $postarr['ID'])) { return $data; }
		$data['menu_order'] = self::priority(wp_unslash($_POST['wp_top_info_bar_priority']));
		return $data;
	}

	public function list_columns(array $columns): array {
		$out = array();
		foreach ($columns as $key => $label) {
			$out[$key] = $label;
			if ('title' === $key) { $out['wp_top_info_bar_priority'] = __('Priority', 'wp-top-info-bar'); $out['wp_top_info_bar_shortcode'] = __('Shortcode', 'wp-top-info-bar'); $out['wp_top_info_bar_enabled'] = __('Enabled', 'wp-top-info-bar'); }
		}
		return $out;
	}

	public function list_column(string $column, int $post_id): void {
		if ('wp_top_info_bar_priority' === $column) {
			$post = get_post($post_id);
			echo '<div class="wp-top-info-bar-priority-row" data-priority-row="' . (int) $post_id . '"><label class="screen-reader-text" for="top-bar-priority-' . (int) $post_id . '">' . esc_html__('Priority', 'wp-top-info-bar') . '</label><input type="number" id="top-bar-priority-' . (int) $post_id . '" class="small-text" data-priority-input min="1" max="9999" step="1" value="' . (int) self::priority($post->menu_order) . '"> <button type="button" class="button button-small" data-save-priority>' . esc_html__('Save', 'wp-top-info-bar') . '</button><span class="description" data-priority-status aria-live="polite"></span></div>';
		} elseif ('wp_top_info_bar_enabled' === $column) {
			$settings = (new WP_Top_Info_Bar_Settings($post_id))->get_settings();
			echo '<label class="wp-top-info-bar-list-switch"><input type="checkbox" data-toggle-bar data-post-id="' . (int) $post_id . '" aria-label="' . esc_attr__('Enable Top Bar', 'wp-top-info-bar') . '"' . checked(! empty($settings['enabled']), true, false) . '></label><span class="description" data-toggle-status aria-live="polite"></span>';
		} elseif ('wp_top_info_bar_shortcode' === $column) {
			echo '<input type="text" class="wp-top-info-bar-list-shortcode" data-copy-shortcode readonly aria-label="' . esc_attr__('Copy shortcode', 'wp-top-info-bar') . '" value="' . esc_attr('[wp_top_info_bar id="' . $post_id . '"]') . '">';
		}
	}

	public function sortable_columns(array $columns): array { $columns['wp_top_info_bar_priority'] = 'wp_top_info_bar_priority'; return $columns; }

	public function list_order($query): void {
		if (is_admin() && $query->is_main_query() && WP_Top_Info_Bar_Settings::POST_TYPE === $query->get('post_type') && ! $query->get('orderby')) { $query->set('orderby', 'wp_top_info_bar_priority'); $query->set('order', 'ASC'); }
	}

	public function priority_orderby(string $orderby, $query): string {
		if (! is_admin() || ! $query->is_main_query() || WP_Top_Info_Bar_Settings::POST_TYPE !== $query->get('post_type') || 'wp_top_info_bar_priority' !== $query->get('orderby')) { return $orderby; }
		global $wpdb;
		$order = 'DESC' === strtoupper((string) $query->get('order')) ? 'DESC' : 'ASC';
		return "GREATEST(1, {$wpdb->posts}.menu_order) {$order}, {$wpdb->posts}.ID ASC";
	}

	public function ajax_priority(): void {
		check_ajax_referer('wp_top_info_bar_priority', 'nonce');
		$id = isset($_POST['post_id']) && is_scalar($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		if (! $id || WP_Top_Info_Bar_Settings::POST_TYPE !== get_post_type($id) || ! current_user_can('manage_options') || ! current_user_can('edit_post', $id)) { wp_send_json_error(array('message' => __('You cannot edit this bar.', 'wp-top-info-bar')), 403); }
		if (! isset($_POST['priority']) || ! is_scalar($_POST['priority'])) { wp_send_json_error(array('message' => __('Enter a priority number.', 'wp-top-info-bar')), 400); }
		$priority = self::priority(wp_unslash($_POST['priority']));
		$result = wp_update_post(array('ID' => $id, 'menu_order' => $priority), true);
		if (is_wp_error($result)) { wp_send_json_error(array('message' => __('Could not save priority.', 'wp-top-info-bar')), 500); }
		wp_send_json_success(array('priority' => $priority));
	}

	public function ajax_toggle(): void {
		check_ajax_referer('wp_top_info_bar_toggle', 'nonce');
		$id = isset($_POST['post_id']) && is_scalar($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		if (! $id || WP_Top_Info_Bar_Settings::POST_TYPE !== get_post_type($id) || ! current_user_can('manage_options') || ! current_user_can('edit_post', $id)) { wp_send_json_error(array('message' => __('You cannot edit this bar.', 'wp-top-info-bar')), 403); }
		if (! isset($_POST['enabled']) || ! is_scalar($_POST['enabled']) || ! in_array((string) $_POST['enabled'], array('0', '1'), true)) { wp_send_json_error(array('message' => __('Invalid enabled state.', 'wp-top-info-bar')), 400); }
		$settings = get_post_meta($id, WP_Top_Info_Bar_Settings::META_KEY, true);
		if (! is_array($settings)) { $settings = (new WP_Top_Info_Bar_Settings($id))->get_settings(); }
		$settings['enabled'] = (int) $_POST['enabled'];
		update_post_meta($id, WP_Top_Info_Bar_Settings::META_KEY, wp_slash($settings));
		$saved = get_post_meta($id, WP_Top_Info_Bar_Settings::META_KEY, true);
		if (! is_array($saved) || (int) ($saved['enabled'] ?? -1) !== $settings['enabled']) { wp_send_json_error(array('message' => __('Could not update the bar.', 'wp-top-info-bar')), 500); }
		wp_send_json_success(array('enabled' => $settings['enabled']));
	}

	public function can_clone($post): bool {
		if (! $post || WP_Top_Info_Bar_Settings::POST_TYPE !== $post->post_type || 'trash' === $post->post_status) { return false; }
		$type = get_post_type_object(WP_Top_Info_Bar_Settings::POST_TYPE);
		return current_user_can('edit_post', $post->ID) && current_user_can($type->cap->create_posts);
	}

	public function clone_bar(int $post_id): int {
		$post = get_post($post_id);
		if (! $this->can_clone($post)) { return 0; }
		$id = wp_insert_post(wp_slash(array(
			'post_type' => WP_Top_Info_Bar_Settings::POST_TYPE,
			'post_status' => 'draft',
			'post_title' => trim(sprintf(__('%s (copy)', 'wp-top-info-bar'), $post->post_title)),
			'post_author' => get_current_user_id(),
			'menu_order' => $post->menu_order,
		)), true);
		if (is_wp_error($id) || ! $id) { return 0; }
		$settings = get_post_meta($post_id, WP_Top_Info_Bar_Settings::META_KEY, true);
		if (! is_array($settings)) { $settings = (new WP_Top_Info_Bar_Settings($post_id))->get_settings(); }
		// Keep explicitly assigned HTML IDs unique if both bars are published later.
		if (! empty($settings['custom_id'])) { $settings['custom_id'] .= '-copy-' . $id; }
		if (false === update_post_meta($id, WP_Top_Info_Bar_Settings::META_KEY, wp_slash($settings))) { wp_delete_post($id, true); return 0; }
		return (int) $id;
	}

	public function clone_row_action(array $actions, $post): array {
		if (! $this->can_clone($post)) { return $actions; }
		$url = wp_nonce_url(add_query_arg(array('action' => self::CLONE_ACTION, 'post' => $post->ID), admin_url('admin-post.php')), self::CLONE_ACTION . '_' . $post->ID);
		$link = '<a href="' . esc_url($url) . '" aria-label="' . esc_attr(sprintf(__('Clone %s', 'wp-top-info-bar'), _draft_or_post_title($post))) . '">' . esc_html__('Clone', 'wp-top-info-bar') . '</a>';
		$out = array();
		foreach ($actions as $key => $action) { $out[$key] = $action; if ('inline hide-if-no-js' === $key) { $out['wp_top_info_bar_clone'] = $link; } }
		if (! isset($out['wp_top_info_bar_clone'])) { $out = isset($out['edit']) ? array_merge(array('edit' => $out['edit'], 'wp_top_info_bar_clone' => $link), $out) : array('wp_top_info_bar_clone' => $link) + $out; }
		return $out;
	}

	public function handle_clone(): void {
		$id = isset($_GET['post']) && is_scalar($_GET['post']) ? absint($_GET['post']) : 0;
		check_admin_referer(self::CLONE_ACTION . '_' . $id);
		$copy = $this->clone_bar($id);
		if (! $copy) { wp_die(esc_html__('Could not clone this Top Bar.', 'wp-top-info-bar'), '', array('response' => 403, 'back_link' => true)); }
		wp_safe_redirect(add_query_arg(array('post_type' => WP_Top_Info_Bar_Settings::POST_TYPE, 'wp_top_info_bar_cloned' => $copy), admin_url('edit.php')));
		exit;
	}

	public function clone_notice(): void {
		$screen = get_current_screen();
		if (! $screen || 'edit-' . WP_Top_Info_Bar_Settings::POST_TYPE !== $screen->id || empty($_GET['wp_top_info_bar_cloned']) || ! is_scalar($_GET['wp_top_info_bar_cloned'])) { return; }
		$id = absint($_GET['wp_top_info_bar_cloned']); $post = get_post($id);
		if (! $post || WP_Top_Info_Bar_Settings::POST_TYPE !== $post->post_type || ! current_user_can('edit_post', $id)) { return; }
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Top Bar cloned as a draft.', 'wp-top-info-bar') . ' <a href="' . esc_url(get_edit_post_link($id, 'raw')) . '">' . esc_html__('Edit copy', 'wp-top-info-bar') . '</a></p></div>';
	}

	public function render_shortcode_box($post): void {
		if ('auto-draft' === $post->post_status) {
			echo '<p>' . esc_html__('Save the bar to get its shortcode.', 'wp-top-info-bar') . '</p>';
			return;
		}
		printf('<input type="text" class="widefat wp-top-info-bar-shortcode" readonly value="%s" onclick="this.select()">', esc_attr('[wp_top_info_bar id="' . $post->ID . '"]'));
		echo '<p class="description">' . esc_html__('Paste it into any page to show the bar there.', 'wp-top-info-bar') . '</p>';
	}

	public function save($post_id, $post): void {
		if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
			return;
		}
		if (WP_Top_Info_Bar_Settings::POST_TYPE !== $post->post_type || ! current_user_can('manage_options') || ! current_user_can('edit_post', $post_id)) {
			return;
		}
		$nonce = $_POST[self::SAVE_NONCE . '_nonce'] ?? '';
		if (! is_string($nonce) || ! wp_verify_nonce($nonce, self::SAVE_NONCE) || ! isset($_POST[WP_Top_Info_Bar_Settings::OPTION_NAME])) {
			return;
		}
		$values = $this->sanitizer->sanitize_settings(wp_unslash($_POST[WP_Top_Info_Bar_Settings::OPTION_NAME]));
		update_post_meta($post_id, WP_Top_Info_Bar_Settings::META_KEY, wp_slash($values));
	}

	public function register_settings(): void {
		foreach (self::pages() as $page => $meta) {
			add_settings_section(WP_Top_Info_Bar_Settings::SETTINGS_SECTION . '_' . $page, '', '__return_false', WP_Top_Info_Bar_Settings::MENU_SLUG);
		}

		$fields = array(
			'message'          => array(
				'label'    => __('Bar text', 'wp-top-info-bar'),
				'callback' => 'render_message_field',
				'tooltip'  => __('Enter the message shown in the bar. Example: Free shipping on orders over $50.', 'wp-top-info-bar'),
			),
			'font_size'        => array(
				'label'    => __('Text size', 'wp-top-info-bar'),
				'callback' => 'render_font_size_field',
				'tooltip'  => __('Use a CSS font-size value. Examples: 14px, 1rem, clamp(14px, 2vw, 18px). Empty on tablet or mobile: inherit from the larger screen.', 'wp-top-info-bar'),
			),
			'text_color'       => array(
				'label'    => __('Text Color', 'wp-top-info-bar'),
				'callback' => 'render_text_color_field',
				'tooltip'  => __('Choose the text color shown inside the bar. Example: #ffffff for white text.', 'wp-top-info-bar'),
			),
			'background_color' => array(
				'label'    => __('Background color', 'wp-top-info-bar'),
				'callback' => 'render_background_color_field',
				'tooltip'  => __('Choose the bar background color. Example: #1f2937 for a dark blue-gray background.', 'wp-top-info-bar'),
			),
			'animation'        => array(
				'label'    => __('Animation', 'wp-top-info-bar'),
				'callback' => 'render_animation_field',
				'tooltip'  => __('Choose how the bar text animates. Examples: Fade in, Fade from left, Slide from page left, Blink or Scale up.', 'wp-top-info-bar'),
			),
			'marquee_speed' => array(
				'label' => __('Speed (px/s)', 'wp-top-info-bar'),
				'callback' => 'render_marquee_speed_field',
				'tooltip' => __('Text scrolling speed in pixels per second. Higher values move faster.', 'wp-top-info-bar'),
			),
			'text_delay'       => array(
				'label'    => __('Delay', 'wp-top-info-bar'),
				'callback' => 'render_text_delay_field',
				'tooltip'  => __('Set when the text animation should start. You can enter 2000, 2000ms or 2s. A plain number is treated as milliseconds.', 'wp-top-info-bar'),
			),
			'padding'          => array(
				'label'    => __('Padding', 'wp-top-info-bar'),
				'callback' => 'render_padding_field',
				'tooltip'  => __('Inner spacing in pixels: Top, Right, Bottom and Left. Enter a number, for example 12, without px. Values cannot be negative. Empty tablet/mobile sides inherit from the larger screen; use 0 to remove a spacing.', 'wp-top-info-bar'),
			),
			'margin'           => array(
				'label'    => __('Margin', 'wp-top-info-bar'),
				'callback' => 'render_margin_field',
				'tooltip'  => __('Outer spacing in pixels: Top, Right, Bottom and Left. Enter a number, for example 20 or -10, without px. Negative values are allowed. Empty tablet/mobile sides inherit from the larger screen; use 0 to remove a spacing.', 'wp-top-info-bar'),
			),
			'custom_class'     => array(
				'label'    => __('Custom classes', 'wp-top-info-bar'),
				'callback' => 'render_custom_class_field',
				'tooltip'  => __('Add one or more CSS classes to target the whole bar. Example: promo-bar seasonal-banner.', 'wp-top-info-bar'),
			),
			'custom_id'        => array(
				'label'    => __('Custom ID', 'wp-top-info-bar'),
				'callback' => 'render_custom_id_field',
				'tooltip'  => __('Add a unique HTML id to the bar container. Example: top-sale-banner.', 'wp-top-info-bar'),
			),
			'images'           => array(
				'label'    => __('Images', 'wp-top-info-bar'),
				'callback' => 'render_images_field',
				'tooltip'  => __('Add one or more images with individual size, margin, custom class and position settings.', 'wp-top-info-bar'),
			),
		);

		foreach ($fields as $id => $field) {
			$page = in_array($id, array('padding', 'margin'), true) ? 'spacing' : (in_array($id, array('message', 'font_size', 'text_color'), true) ? 'text' : (in_array($id, array('animation', 'marquee_speed', 'text_delay'), true) ? 'animation' : (in_array($id, array('custom_class', 'custom_id'), true) ? 'custom' : 'generell')));
			if ('background_color' === $id) { $page = 'background'; }
			if ('images' === $id) { $page = 'pictures'; }
			$title = $this->get_settings_field_title_markup($field['label'], $field['tooltip']);
			if ('background_color' === $id) { $title = esc_html($field['label']); }
			if (in_array($id, array('padding', 'margin'), true)) {
				$enabled = ! empty($this->settings->get_settings()[$id . '_enabled']);
				$name = WP_Top_Info_Bar_Settings::OPTION_NAME . '[' . $id . '_enabled]';
				$title = '<div class="wp-top-info-bar-admin__switch"><input type="hidden" name="' . esc_attr($name) . '" value="0"><label><input type="checkbox" data-spacing-toggle="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="1"' . checked($enabled, true, false) . '> ' . esc_html($field['label']) . '</label></div>';
			}
			if (in_array($id, array('font_size', 'padding', 'margin', 'animation', 'text_delay', 'marquee_speed'), true)) {
				$title .= '<span class="wp-top-info-bar-admin__devices" role="group" aria-label="' . esc_attr($field['label'] . ' - ' . __('Device', 'wp-top-info-bar')) . '">';
				foreach (array('desktop' => array(__('Desktop', 'wp-top-info-bar'), 'dashicons-desktop'), 'tablet' => array(__('Tablet', 'wp-top-info-bar'), 'dashicons-tablet'), 'mobile' => array(__('Mobile', 'wp-top-info-bar'), 'dashicons-smartphone')) as $device => $meta) {
					$title .= '<button type="button" class="wp-top-info-bar-admin__device" data-device="' . esc_attr($device) . '" aria-label="' . esc_attr($meta[0]) . '" title="' . esc_attr($meta[0]) . '" aria-pressed="' . ('desktop' === $device ? 'true' : 'false') . '"><span class="dashicons ' . esc_attr($meta[1]) . '" aria-hidden="true"></span></button>';
				}
				$title .= '</span>';
			}
			add_settings_field(
				$id,
				$title,
				array($this, $field['callback']),
				WP_Top_Info_Bar_Settings::MENU_SLUG,
				WP_Top_Info_Bar_Settings::SETTINGS_SECTION . '_' . $page,
				array('class' => 'wp-top-info-bar-field-' . $id . ('font_size' === $id ? ' wp-top-info-bar-font-size-row' : ''))
			);
		}
	}

	private static function pages(): array {
		return array(
			'generell' => array(__('Generell', 'wp-top-info-bar'), 'dashicons-admin-generic'),
		);
	}

	public function render_settings_page(): void {
		echo '<div class="wrap"><p>' . esc_html__('Open the Top Bar editor to change its settings.', 'wp-top-info-bar') . '</p></div>';
	}

	public function render_layout_box($post): void {
		$this->settings = new WP_Top_Info_Bar_Settings((int) $post->ID);
		?>
		<div class="wp-top-info-bar-admin wp-top-info-bar-layout-field">
		<button type="button" class="wp-top-info-bar-layout-icon dashicons dashicons-admin-generic" data-bar-settings-open aria-haspopup="dialog" aria-expanded="false" aria-controls="wp-top-info-bar-settings-modal" aria-label="<?php esc_attr_e('Bar settings', 'wp-top-info-bar'); ?>"></button>
		<div class="wp-top-info-bar-layout-builder" data-layout-builder>
						<input type="hidden" data-layout-order name="<?php echo esc_attr(WP_Top_Info_Bar_Settings::OPTION_NAME); ?>[layout_order]" value="<?php echo esc_attr(wp_json_encode($this->settings->get_settings()['layout_order'])); ?>">
							<input type="hidden" data-layout-zones name="<?php echo esc_attr(WP_Top_Info_Bar_Settings::OPTION_NAME); ?>[layout_zones]" value="<?php echo esc_attr(wp_json_encode($this->settings->get_settings()['layout_zones'])); ?>">
<div class="wp-top-info-bar-layout-builder__frame">
			<div class="wp-top-info-bar-layout-builder__bar wp-top-info-bar-layout-builder__side" data-layout-zone="left"><button type="button" class="wp-top-info-bar-zone-icon dashicons dashicons-admin-generic" data-zone-edit aria-haspopup="dialog" aria-expanded="false" aria-controls="wp-top-info-bar-zone-modal" aria-label="<?php esc_attr_e('Edit zone', 'wp-top-info-bar'); ?>"></button><button type="button" class="wp-top-info-bar-layout-builder__add" data-layout-add aria-haspopup="dialog" aria-expanded="false" aria-controls="wp-top-info-bar-element-picker" aria-label="<?php esc_attr_e('Add element on the left', 'wp-top-info-bar'); ?>"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span></button></div>
			<div class="wp-top-info-bar-layout-builder__bar" data-layout-zone="center"><button type="button" class="wp-top-info-bar-zone-icon dashicons dashicons-admin-generic" data-zone-edit aria-haspopup="dialog" aria-expanded="false" aria-controls="wp-top-info-bar-zone-modal" aria-label="<?php esc_attr_e('Edit zone', 'wp-top-info-bar'); ?>"></button>
								<button type="button" class="wp-top-info-bar-layout-builder__add" data-layout-add aria-haspopup="dialog" aria-label="<?php esc_attr_e('Add element', 'wp-top-info-bar'); ?>" title="<?php esc_attr_e('Add element', 'wp-top-info-bar'); ?>" aria-expanded="false" aria-controls="wp-top-info-bar-element-picker"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span></button>
							</div>
			<div class="wp-top-info-bar-layout-builder__bar wp-top-info-bar-layout-builder__side" data-layout-zone="right"><button type="button" class="wp-top-info-bar-zone-icon dashicons dashicons-admin-generic" data-zone-edit aria-haspopup="dialog" aria-expanded="false" aria-controls="wp-top-info-bar-zone-modal" aria-label="<?php esc_attr_e('Edit zone', 'wp-top-info-bar'); ?>"></button><button type="button" class="wp-top-info-bar-layout-builder__add" data-layout-add aria-haspopup="dialog" aria-expanded="false" aria-controls="wp-top-info-bar-element-picker" aria-label="<?php esc_attr_e('Add element on the right', 'wp-top-info-bar'); ?>"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span></button></div>
			</div>
							<div class="wp-top-info-bar-image-modal wp-top-info-bar-element-modal" id="wp-top-info-bar-element-picker" data-layout-picker role="dialog" aria-modal="true" aria-labelledby="wp-top-info-bar-element-picker-title" hidden>
								<div class="wp-top-info-bar-image-modal__backdrop" data-close-layout-picker></div>
								<div class="wp-top-info-bar-image-modal__panel">
								<div class="wp-top-info-bar-image-modal__header"><h2 id="wp-top-info-bar-element-picker-title"><?php esc_html_e('Add element', 'wp-top-info-bar'); ?></h2><button type="button" class="wp-top-info-bar-image-modal__close" data-close-layout-picker aria-label="<?php esc_attr_e('Close', 'wp-top-info-bar'); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></div>
								<div class="wp-top-info-bar-image-modal__body">
								<div class="wp-top-info-bar-layout-builder__choices">
								<?php foreach (array('pictures' => array(__('Image', 'wp-top-info-bar'), 'dashicons-format-image'), 'text' => array(__('Text', 'wp-top-info-bar'), 'dashicons-editor-textcolor'), 'button' => array(__('Button', 'wp-top-info-bar'), 'dashicons-button')) as $element => $choice) : ?>
									<button type="button" class="wp-top-info-bar-layout-builder__choice" data-layout-element="<?php echo esc_attr($element); ?>"><span class="dashicons <?php echo esc_attr($choice[1]); ?>" aria-hidden="true"></span><span><?php echo esc_html($choice[0]); ?></span></button>
								<?php endforeach; ?>
								</div>
								</div>
								</div>
							</div>
						</div>
						
		</div>
		<?php
	}

	public function render_settings_box($post): void {
		$this->settings = new WP_Top_Info_Bar_Settings((int) $post->ID);
		// Field titles contain spacing switches: rebuild them for this bar, not the global one.
		$this->register_settings();
		wp_nonce_field(self::SAVE_NONCE, self::SAVE_NONCE . '_nonce');
		?>
		<div class="wp-top-info-bar-settings">
		<?php $this->render_text_elements(); $this->render_button_elements(); $this->render_zone_settings_modal(); $this->render_images_field(); ?>
				<div class="wp-top-info-bar-admin wp-top-info-bar-workspace">
				
				<div class="wp-top-info-bar-admin__settings">
				<div class="wp-top-info-bar-image-modal wp-top-info-bar-bar-settings-modal" id="wp-top-info-bar-settings-modal" role="dialog" aria-modal="true" aria-labelledby="wp-top-info-bar-settings-modal-title" hidden>
					<div class="wp-top-info-bar-image-modal__backdrop" data-bar-settings-close></div>
					<div class="wp-top-info-bar-image-modal__panel">
						<div class="wp-top-info-bar-image-modal__header">
							<h2 id="wp-top-info-bar-settings-modal-title"><?php esc_html_e('Bar settings', 'wp-top-info-bar'); ?></h2>
							<button type="button" class="wp-top-info-bar-image-modal__close" data-bar-settings-close aria-label="<?php esc_attr_e('Close', 'wp-top-info-bar'); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
						</div>
						<div class="wp-top-info-bar-image-modal__body">
				
				<?php foreach (self::pages() as $page => $meta) : ?>
					<fieldset class="wp-top-info-bar-admin__page" id="wp-top-info-bar-page-<?php echo esc_attr($page); ?>" data-page="<?php echo esc_attr($page); ?>"<?php echo 'generell' === $page ? '' : ' hidden'; ?>>
						<legend><?php echo esc_html($meta[0]); ?></legend>
						<?php if ('generell' === $page) : ?>
						
						<section class="wp-top-info-bar-admin__general-block wp-top-info-bar-admin__appearance">
						<div class="wp-top-info-bar-admin__background-block"><table class="form-table" role="presentation"><tbody><?php do_settings_fields(WP_Top_Info_Bar_Settings::MENU_SLUG, WP_Top_Info_Bar_Settings::SETTINGS_SECTION . '_background'); ?></tbody></table></div>
						<div class="wp-top-info-bar-admin__spacing-block"><table class="form-table" role="presentation"><tbody><?php do_settings_fields(WP_Top_Info_Bar_Settings::MENU_SLUG, WP_Top_Info_Bar_Settings::SETTINGS_SECTION . '_spacing'); ?></tbody></table></div>
						<?php $this->render_bar_decoration_fields(); ?>
						</section>
						<div class="wp-top-info-bar-admin__animation-block wp-top-info-bar-admin__general-block">
						<div class="wp-top-info-bar-admin__switch">
							<input type="hidden" name="<?php echo esc_attr(WP_Top_Info_Bar_Settings::OPTION_NAME); ?>[animation_enabled]" value="0">
							<label><input type="checkbox" id="wp-top-info-bar-animation-enabled" name="<?php echo esc_attr(WP_Top_Info_Bar_Settings::OPTION_NAME); ?>[animation_enabled]" value="1" <?php checked(! empty($this->settings->get_settings()['animation_enabled'])); ?>> <?php esc_html_e('Animation', 'wp-top-info-bar'); ?></label>
						</div>
							<div class="wp-top-info-bar-admin__animation-options"<?php echo empty($this->settings->get_settings()['animation_enabled']) ? ' hidden' : ''; ?>>
								<table class="form-table" role="presentation"><tbody><?php do_settings_fields(WP_Top_Info_Bar_Settings::MENU_SLUG, WP_Top_Info_Bar_Settings::SETTINGS_SECTION . '_animation'); ?></tbody></table>
							</div>
						</div>
						<section class="wp-top-info-bar-admin__general-block wp-top-info-bar-admin__custom">
						<table class="form-table" role="presentation"><tbody><?php do_settings_fields(WP_Top_Info_Bar_Settings::MENU_SLUG, WP_Top_Info_Bar_Settings::SETTINGS_SECTION . '_custom'); ?></tbody></table>
						</section>
						<?php endif; ?>
						<?php if (! in_array($page, array('generell'), true)) : ?>
						<table class="form-table" role="presentation"><tbody><?php do_settings_fields(WP_Top_Info_Bar_Settings::MENU_SLUG, WP_Top_Info_Bar_Settings::SETTINGS_SECTION . '_' . $page); ?></tbody></table>
						<?php endif; ?>
					</fieldset>
				<?php endforeach; ?>
						</div>
						<div class="wp-top-info-bar-image-modal__footer"><button type="button" class="button" data-bar-settings-close><?php esc_html_e('Done', 'wp-top-info-bar'); ?></button></div>
					</div>
				</div>
				</div>
				<?php
				ob_start();
				$this->render_enabled_field();
				$enabled_control = ob_get_clean();
				WP_Top_Info_Bar_Preview::render_panel($enabled_control);
				?>
				</div>
		</div>
		<?php
	}

	public function enqueue_admin_assets(string $hook_suffix): void {
		$screen = get_current_screen();
		if ($screen && WP_Top_Info_Bar_Settings::POST_TYPE === $screen->post_type && 'edit.php' === $hook_suffix) {
			wp_enqueue_style('wp-top-info-bar-list', WP_TOP_INFO_BAR_URL . 'assets/css/list.css', array(), (string) filemtime(WP_TOP_INFO_BAR_PATH . 'assets/css/list.css'));
			wp_enqueue_script('wp-top-info-bar-list', WP_TOP_INFO_BAR_URL . 'assets/js/list.js', array('jquery'), (string) filemtime(WP_TOP_INFO_BAR_PATH . 'assets/js/list.js'), true);
			wp_localize_script('wp-top-info-bar-list', 'wpTopInfoBarList', array('toggleNonce' => wp_create_nonce('wp_top_info_bar_toggle'), 'toggleError' => __('Could not update the bar.', 'wp-top-info-bar'), 'nonce' => wp_create_nonce('wp_top_info_bar_priority'), 'saved' => __('Saved', 'wp-top-info-bar'), 'copied' => __('Copied!', 'wp-top-info-bar'), 'error' => __('Could not save priority.', 'wp-top-info-bar')));
			return;
		}
		if (! $screen || WP_Top_Info_Bar_Settings::POST_TYPE !== $screen->post_type || ! in_array($hook_suffix, array('post.php', 'post-new.php'), true)) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_editor();
		wp_enqueue_style('wp-top-info-bar-pickr', WP_TOP_INFO_BAR_URL . 'assets/vendor/pickr/classic.min.css', array(), '1.9.1');
		wp_enqueue_script('wp-top-info-bar-pickr', WP_TOP_INFO_BAR_URL . 'assets/vendor/pickr/pickr.min.js', array(), '1.9.1', true);
		wp_enqueue_style(
			'wp-top-info-bar-admin',
			WP_TOP_INFO_BAR_URL . 'assets/css/admin.css',
			array(),
			(string) filemtime(WP_TOP_INFO_BAR_PATH . 'assets/css/admin.css')
		);
		wp_enqueue_script(
			'wp-top-info-bar-admin',
			WP_TOP_INFO_BAR_URL . 'assets/js/admin.js',
			array('jquery', 'jquery-ui-sortable', 'wp-util', 'editor'),
			(string) filemtime(WP_TOP_INFO_BAR_PATH . 'assets/js/admin.js'),
			true
		);
		wp_enqueue_style('wp-top-info-bar-preview', WP_TOP_INFO_BAR_URL . 'assets/css/preview.css', array('wp-top-info-bar-admin'), (string) filemtime(WP_TOP_INFO_BAR_PATH . 'assets/css/preview.css'));
		wp_enqueue_script('wp-top-info-bar-button-elements', WP_TOP_INFO_BAR_URL . 'assets/js/button-elements.js', array('wp-top-info-bar-admin'), (string) filemtime(WP_TOP_INFO_BAR_PATH . 'assets/js/button-elements.js'), true);
		wp_enqueue_script('wp-top-info-bar-text-elements', WP_TOP_INFO_BAR_URL . 'assets/js/text-elements.js', array('wp-top-info-bar-admin', 'editor'), (string) filemtime(WP_TOP_INFO_BAR_PATH . 'assets/js/text-elements.js'), true);
		wp_enqueue_script('wp-top-info-bar-preview', WP_TOP_INFO_BAR_URL . 'assets/js/preview.js', array('wp-top-info-bar-admin'), (string) filemtime(WP_TOP_INFO_BAR_PATH . 'assets/js/preview.js'), true);
		wp_enqueue_style('wp-top-info-bar-colors', WP_TOP_INFO_BAR_URL . 'assets/css/colors.css', array('wp-top-info-bar-admin', 'wp-top-info-bar-pickr'), (string) filemtime(WP_TOP_INFO_BAR_PATH . 'assets/css/colors.css'));
		wp_enqueue_script('wp-top-info-bar-colors', WP_TOP_INFO_BAR_URL . 'assets/js/colors.js', array('wp-top-info-bar-admin', 'wp-top-info-bar-pickr'), (string) filemtime(WP_TOP_INFO_BAR_PATH . 'assets/js/colors.js'), true);

		wp_localize_script(
			'wp-top-info-bar-admin',
			'wpTopInfoBarAdmin',
			array(
				'optionName' => WP_Top_Info_Bar_Settings::OPTION_NAME,
				'textColors' => WP_Top_Info_Bar_Palette::tinymce_map(),
				'colorPicker' => array(
					'ui:dialog' => __('Color picker', 'wp-top-info-bar'),
					'btn:toggle' => __('Choose a color', 'wp-top-info-bar'),
					'btn:swatch' => __('Color', 'wp-top-info-bar'),
					'btn:cancel' => __('Cancel', 'wp-top-info-bar'),
					'aria:btn:cancel' => __('Back to the color it had when opened', 'wp-top-info-bar'),
					'aria:input' => __('Color value', 'wp-top-info-bar'),
					'aria:palette' => __('Color and brightness', 'wp-top-info-bar'),
					'aria:hue' => __('Hue', 'wp-top-info-bar'),
					'aria:opacity' => __('Opacity', 'wp-top-info-bar'),
					'default' => __('Default', 'wp-top-info-bar'),
					'defaultLabel' => __('Back to the default color', 'wp-top-info-bar'),
				),
				'previewError' => __('Could not load the preview. Try Update again.', 'wp-top-info-bar'),
				'labels'     => array(
					'changeImage'  => __('Change image', 'wp-top-info-bar'),
					'replaceImage' => __('Replace image', 'wp-top-info-bar'),
					'imageNumber' => __('Image %d', 'wp-top-info-bar'),
					'editImage' => __('Edit Image %d', 'wp-top-info-bar'),
					'chooseImage'  => __('Choose image', 'wp-top-info-bar'),
					'useImage'     => __('Use this image', 'wp-top-info-bar'),
				),
			)
		);
	}

	private function render_bar_decoration_fields(): void {
		$values = $this->settings->get_settings(); $prefix = WP_Top_Info_Bar_Settings::OPTION_NAME;
		foreach (array('border' => __('Bottom border', 'wp-top-info-bar'), 'shadow' => __('Shadow', 'wp-top-info-bar')) as $kind => $label) : ?>
		<div class="wp-top-info-bar-decoration">
			<div class="wp-top-info-bar-admin__switch"><input type="hidden" name="<?php echo esc_attr($prefix . '[' . $kind . '_enabled]'); ?>" value="0"><label><input type="checkbox" data-bar-decoration="<?php echo esc_attr($kind); ?>" name="<?php echo esc_attr($prefix . '[' . $kind . '_enabled]'); ?>" value="1"<?php checked(! empty($values[$kind . '_enabled'])); ?>> <?php echo esc_html($label); ?></label></div>
			<div class="wp-top-info-bar-decoration__options" data-bar-decoration-options="<?php echo esc_attr($kind); ?>"<?php echo empty($values[$kind . '_enabled']) ? ' hidden' : ''; ?>>
			<?php if ('border' === $kind) : ?>
			<div class="wp-top-info-bar-decoration__border-width"><?php $this->image_device_heading(__('Width (px)', 'wp-top-info-bar'), __('Bottom border thickness in pixels. Set 0 to hide it. Empty tablet and mobile fields inherit the value from the larger screen.', 'wp-top-info-bar')); ?>
			<?php foreach (array('desktop' => '', 'tablet' => '_tablet', 'mobile' => '_mobile') as $device => $suffix) : ?>
			<div class="wp-top-info-bar-admin__responsive-device" data-device="<?php echo esc_attr($device); ?>"<?php echo '' === $suffix ? '' : ' hidden'; ?>><input type="number" min="0" max="50" step="any" class="regular-text" name="<?php echo esc_attr($prefix . '[border_width' . $suffix . ']'); ?>" value="<?php echo esc_attr((string) $values['border_width' . $suffix]); ?>" placeholder="<?php echo '' === $suffix ? '2' : esc_attr__('Inherit', 'wp-top-info-bar'); ?>"></div>
			<?php endforeach; ?></div>
			<?php else : ?>
			<div class="wp-top-info-bar-decoration__shadow-values">
			<?php foreach (array('shadow_x' => __('Horizontal (px)', 'wp-top-info-bar'), 'shadow_y' => __('Vertical (px)', 'wp-top-info-bar'), 'shadow_blur' => __('Blur radius (px)', 'wp-top-info-bar'), 'shadow_spread' => __('Spread radius (px)', 'wp-top-info-bar')) as $key => $name) : ?>
			<label><?php echo esc_html($name); ?><input type="number" class="regular-text" name="<?php echo esc_attr($prefix . '[' . $key . ']'); ?>" value="<?php echo esc_attr((string) $values[$key]); ?>" min="<?php echo 'shadow_blur' === $key ? '0' : ('shadow_spread' === $key ? '-50' : '-100'); ?>" max="<?php echo 'shadow_spread' === $key ? '50' : '100'; ?>" step="any"></label>
			<?php endforeach; ?></div>
			<?php endif; ?>
			<div class="wp-top-info-bar-decoration__color"><label><?php esc_html_e('Color', 'wp-top-info-bar'); ?></label><?php $this->color($kind . '_color', $values[$kind . '_color'], 'border' === $kind ? '#ffffff' : 'rgba(0,0,0,0.3)'); ?></div>
			</div>
		</div>
		<?php endforeach;
	}

	private function render_zone_settings_modal(): void {
		$settings = $this->settings->get_settings();
		$zones = (new WP_Top_Info_Bar_Sanitizer())->sanitize_zone_settings($settings['zone_settings']);
		?>
		<div class="wp-top-info-bar-image-modal wp-top-info-bar-zone-modal wp-top-info-bar-admin" data-zone-modal role="dialog" aria-modal="true" aria-labelledby="wp-top-info-bar-zone-modal-title" hidden>
			<div class="wp-top-info-bar-image-modal__backdrop" data-close-zone-modal></div>
			<div class="wp-top-info-bar-image-modal__panel">
				<div class="wp-top-info-bar-image-modal__header"><h2 id="wp-top-info-bar-zone-modal-title"><?php esc_html_e('Zone settings', 'wp-top-info-bar'); ?></h2><button type="button" class="wp-top-info-bar-image-modal__close" data-close-zone-modal aria-label="<?php esc_attr_e('Close', 'wp-top-info-bar'); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></div>
				<div class="wp-top-info-bar-image-modal__body">
				<?php foreach (array('left', 'center', 'right') as $zone) : $values = $zones[$zone] ?? array(); $prefix = WP_Top_Info_Bar_Settings::OPTION_NAME . '[zone_settings][' . $zone . ']'; ?>
					<div data-zone-settings="<?php echo esc_attr($zone); ?>" hidden>
						<div class="wp-top-info-bar-zone-modal__appearance">

						<div class="wp-top-info-bar-zone-modal__row">
						<div class="wp-top-info-bar-zone-modal__field">
						<?php $this->image_device_heading(__('Width', 'wp-top-info-bar'), __('Use px, %, or auto. A number without a unit is pixels. Empty tablet/mobile fields inherit from the larger screen.', 'wp-top-info-bar')); ?>
						<?php foreach (array('desktop' => '', 'tablet' => '_tablet', 'mobile' => '_mobile') as $device => $suffix) : ?>
						<div class="wp-top-info-bar-admin__responsive-device" data-device="<?php echo esc_attr($device); ?>"<?php echo 'desktop' === $device ? '' : ' hidden'; ?>><input type="text" class="regular-text" name="<?php echo esc_attr($prefix . '[width' . $suffix . ']'); ?>" value="<?php echo esc_attr($values['width' . $suffix] ?? ''); ?>" placeholder="<?php echo 'desktop' === $device ? ('center' === $zone ? esc_attr__('Fill available space', 'wp-top-info-bar') : 'auto') : esc_attr__('Inherit', 'wp-top-info-bar'); ?>" aria-label="<?php echo esc_attr(sprintf(__('Zone width: %s', 'wp-top-info-bar'), $device)); ?>"></div>
						<?php endforeach; ?>
						</div>
						<div class="wp-top-info-bar-zone-modal__field"><label><?php esc_html_e('Background', 'wp-top-info-bar'); ?></label><?php $this->color('zone_settings][' . $zone . '][background_color', $values['background_color'] ?? 'rgba(0,0,0,0)', 'rgba(0,0,0,0)'); ?></div>
						</div>
						<?php foreach (array('padding' => __('Padding', 'wp-top-info-bar'), 'margin' => __('Margin', 'wp-top-info-bar')) as $key => $label) : ?>
						<div class="wp-top-info-bar-zone-modal__field wp-top-info-bar-field-<?php echo esc_attr($key); ?>">
						<?php $enabled = ! empty($values[$key . '_enabled']); ?>
						<?php $toggle = '<div class="wp-top-info-bar-admin__switch"><input type="hidden" name="' . esc_attr($prefix . '[' . $key . '_enabled]') . '" value="0"><label><input type="checkbox" data-zone-spacing-toggle name="' . esc_attr($prefix . '[' . $key . '_enabled]') . '" value="1"' . checked($enabled, true, false) . '> ' . esc_html($label) . '</label></div>'; $this->image_device_heading($label, '', $toggle); ?>
						<div data-zone-spacing-options<?php echo $enabled ? '' : ' hidden'; ?>><?php $this->spacing_fields($key, $values, $prefix); ?></div>
						</div>
						<?php endforeach; ?>
						</div>
						<div class="wp-top-info-bar-zone-modal__field" data-zone-animation-block>
						
						<?php $effects = array('none' => __('None', 'wp-top-info-bar'), 'fade-in' => __('Fade in', 'wp-top-info-bar'), 'slide-up' => __('Slide up', 'wp-top-info-bar'), 'from-left' => __('Fade from left', 'wp-top-info-bar'), 'from-right' => __('Fade from right', 'wp-top-info-bar'), 'page-left' => __('Slide from page left', 'wp-top-info-bar'), 'page-right' => __('Slide from page right', 'wp-top-info-bar'), 'blink' => __('Blink', 'wp-top-info-bar'), 'scale-pulse' => __('Scale up', 'wp-top-info-bar'), 'marquee' => __('Marquee', 'wp-top-info-bar'));
						foreach (array('animation_mode' => __('Animation', 'wp-top-info-bar'), 'animation' => __('Animation', 'wp-top-info-bar'), 'animation_speed' => __('Speed (px/s)', 'wp-top-info-bar'), 'animation_delay' => __('Delay', 'wp-top-info-bar')) as $key => $label) : ?>
						<div class="wp-top-info-bar-zone-animation-field" data-zone-animation-field="<?php echo esc_attr($key); ?>">
						<?php $this->image_device_heading($label, ''); ?>
						<?php foreach (array('desktop' => '', 'tablet' => '_tablet', 'mobile' => '_mobile') as $device => $suffix) : $value = $values[$key . $suffix] ?? ('' === $suffix ? ('animation_mode' === $key ? 'inherit' : ('animation' === $key ? 'fade-in' : ('animation_speed' === $key ? '60' : ''))) : ''); ?>
						<div class="wp-top-info-bar-admin__responsive-device" data-device="<?php echo esc_attr($device); ?>"<?php echo 'desktop' === $device ? '' : ' hidden'; ?>>
						<?php if ('animation_mode' === $key) :
						$resolved_mode = $values['animation_mode'] ?? 'inherit';
						foreach (array('_tablet', '_mobile') as $mode_suffix) { if ('' === $suffix) { break; } if (! empty($values['animation_mode' . $mode_suffix])) { $resolved_mode = $values['animation_mode' . $mode_suffix]; } if ($suffix === $mode_suffix) { break; } }
						?>
						<input type="hidden" name="<?php echo esc_attr($prefix . '[' . $key . $suffix . ']'); ?>" data-zone-animation-input="animation_mode" value="<?php echo esc_attr($resolved_mode); ?>">
						<div class="wp-top-info-bar-admin__switch"><label><input type="checkbox" data-zone-animation-toggle<?php checked('inherit' !== $resolved_mode); ?>> <?php esc_html_e('Enable zone animation', 'wp-top-info-bar'); ?></label></div>
						<p class="description"><?php esc_html_e('When disabled, the zone inherits the main bar animation (Inherit).', 'wp-top-info-bar'); ?></p>
						<?php elseif ('animation' === $key) : ?>
						<select name="<?php echo esc_attr($prefix . '[' . $key . $suffix . ']'); ?>" data-zone-animation-input="<?php echo esc_attr($key); ?>">
						<?php if ('' !== $suffix) : ?><option value=""<?php selected($value, ''); ?>><?php esc_html_e('Same as larger screen', 'wp-top-info-bar'); ?></option><?php endif; ?>
						<?php $effect_mode = $values['animation_mode'] ?? 'inherit'; foreach (array('_tablet', '_mobile') as $mode_suffix) { if ('' === $suffix) { break; } if (! empty($values['animation_mode' . $mode_suffix])) { $effect_mode = $values['animation_mode' . $mode_suffix]; } if ($suffix === $mode_suffix) { break; } } if ('none' === $effect_mode) { $value = 'none'; } foreach ($effects as $option => $text) : ?><option value="<?php echo esc_attr($option); ?>"<?php selected($value, $option); ?>><?php echo esc_html($text); ?></option><?php endforeach; ?>
						</select>
						<?php else : ?><input class="regular-text" type="<?php echo 'animation_speed' === $key ? 'number' : 'text'; ?>" name="<?php echo esc_attr($prefix . '[' . $key . $suffix . ']'); ?>" value="<?php echo esc_attr((string) $value); ?>" placeholder="<?php echo '' === $suffix ? ('animation_speed' === $key ? '60' : '0s') : esc_attr__('Inherit', 'wp-top-info-bar'); ?>"<?php echo 'animation_speed' === $key ? ' min="1" max="1000" step="any"' : ''; ?>><?php endif; ?>
						</div>
						<?php endforeach; ?>
						</div>
						<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>
				</div>
				<div class="wp-top-info-bar-image-modal__footer"><button type="button" class="button" data-close-zone-modal><?php esc_html_e('Done', 'wp-top-info-bar'); ?></button></div>
			</div>
		</div>
		<?php
	}

	private function render_text_elements(): void {
		$texts = $this->settings->get_settings()['texts'];
		?>
		<input type="hidden" name="<?php echo esc_attr(WP_Top_Info_Bar_Settings::OPTION_NAME); ?>[texts_present]" value="1">
		<div data-text-elements hidden>
		<?php foreach ($texts as $id => $element) : ?>
			<div data-text-element="<?php echo esc_attr((string) $id); ?>">
			<?php foreach ($element as $key => $value) : ?>
				<input type="hidden" data-text-field="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr(WP_Top_Info_Bar_Settings::OPTION_NAME . '[texts][' . $id . '][' . $key . ']'); ?>" value="<?php echo esc_attr($value); ?>">
			<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
		</div>
		<div class="wp-top-info-bar-image-modal wp-top-info-bar-text-modal wp-top-info-bar-admin" data-text-modal role="dialog" aria-modal="true" aria-labelledby="wp-top-info-bar-text-modal-title" hidden>
			<div class="wp-top-info-bar-image-modal__backdrop" data-close-text-modal></div>
			<div class="wp-top-info-bar-image-modal__panel">
				<div class="wp-top-info-bar-image-modal__header"><h2 id="wp-top-info-bar-text-modal-title"><?php esc_html_e('Text', 'wp-top-info-bar'); ?></h2><button type="button" class="wp-top-info-bar-image-modal__close" data-close-text-modal aria-label="<?php esc_attr_e('Close', 'wp-top-info-bar'); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></div>
				<div class="wp-top-info-bar-image-modal__body"><div class="wp-top-info-bar-text-modal__settings">
					<div class="wp-top-info-bar-text-modal__field"><label for="wp-top-info-bar-text-element-editor"><?php esc_html_e('Text', 'wp-top-info-bar'); ?></label><textarea id="wp-top-info-bar-text-element-editor" rows="6" class="large-text"></textarea></div>
					<div class="wp-top-info-bar-text-modal__field">
					<?php $this->image_device_heading(__('Size (px)', 'wp-top-info-bar'), __('Text size for each device. Empty tablet/mobile fields inherit from the larger screen.', 'wp-top-info-bar')); ?>
					<?php foreach (array('desktop' => '', 'tablet' => '_tablet', 'mobile' => '_mobile') as $device => $suffix) : ?>
					<div class="wp-top-info-bar-admin__responsive-device" data-device="<?php echo esc_attr($device); ?>"<?php echo 'desktop' === $device ? '' : ' hidden'; ?>><input type="number" min="1" step="any" class="regular-text" data-text-edit="<?php echo esc_attr('font_size' . $suffix); ?>" placeholder="<?php echo 'desktop' === $device ? '14' : esc_attr__('Inherit', 'wp-top-info-bar'); ?>"></div>
					<?php endforeach; ?>
					</div>
					<div class="wp-top-info-bar-text-modal__field"><label><?php esc_html_e('Text color', 'wp-top-info-bar'); ?></label><?php $this->color('text_element_editor_color', '#ffffff', '#ffffff'); ?></div>
				</div></div>
				<div class="wp-top-info-bar-image-modal__footer"><button type="button" class="button-link wp-top-info-bar-clone" data-clone-text-element><?php esc_html_e('Clone', 'wp-top-info-bar'); ?></button><button type="button" class="button-link-delete" data-remove-text-element><?php esc_html_e('Remove', 'wp-top-info-bar'); ?></button><button type="button" class="button" data-close-text-modal><?php esc_html_e('Done', 'wp-top-info-bar'); ?></button></div>
			</div>
		</div>
		<?php
	}

	private function render_button_elements(): void {
		$buttons = $this->settings->get_settings()['buttons'];
		?>
		<input type="hidden" name="<?php echo esc_attr(WP_Top_Info_Bar_Settings::OPTION_NAME); ?>[buttons_present]" value="1">
		<div data-button-elements hidden>
		<?php foreach ($buttons as $id => $element) : ?>
			<div data-button-element="<?php echo esc_attr((string) $id); ?>">
			<?php foreach ($element as $key => $value) : ?>
				<input type="hidden" data-button-field="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr(WP_Top_Info_Bar_Settings::OPTION_NAME . '[buttons][' . $id . '][' . $key . ']'); ?>" value="<?php echo esc_attr((string) $value); ?>">
			<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
		</div>
		<div class="wp-top-info-bar-image-modal wp-top-info-bar-button-modal wp-top-info-bar-admin" data-button-modal role="dialog" aria-modal="true" aria-labelledby="wp-top-info-bar-button-modal-title" hidden>
			<div class="wp-top-info-bar-image-modal__backdrop" data-close-button-modal></div>
			<div class="wp-top-info-bar-image-modal__panel">
				<div class="wp-top-info-bar-image-modal__header"><h2 id="wp-top-info-bar-button-modal-title"><?php esc_html_e('Button', 'wp-top-info-bar'); ?></h2><button type="button" class="wp-top-info-bar-image-modal__close" data-close-button-modal aria-label="<?php esc_attr_e('Close', 'wp-top-info-bar'); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></div>
				<div class="wp-top-info-bar-image-modal__body"><?php $this->render_button_fields(); ?></div>
				<div class="wp-top-info-bar-image-modal__footer"><button type="button" class="button-link wp-top-info-bar-clone" data-clone-button-element><?php esc_html_e('Clone', 'wp-top-info-bar'); ?></button><button type="button" class="button-link-delete" data-remove-button-element><?php esc_html_e('Remove', 'wp-top-info-bar'); ?></button><button type="button" class="button" data-close-button-modal><?php esc_html_e('Done', 'wp-top-info-bar'); ?></button></div>
			</div>
		</div>
		<?php
	}

	private function render_button_fields(): void {
		$settings = $this->settings->get_settings();
		$fields = array(
			'button_text' => array(__('Text / value', 'wp-top-info-bar'), 'text'),
			'button_url' => array(__('Link', 'wp-top-info-bar'), 'url'),
			'button_size' => array(__('Size', 'wp-top-info-bar'), array('extra-small' => __('Extra Small', 'wp-top-info-bar'), 'small' => __('Small', 'wp-top-info-bar'), 'default' => __('Default', 'wp-top-info-bar'), 'large' => __('Large', 'wp-top-info-bar'), 'extra-large' => __('Extra Large', 'wp-top-info-bar'))),
			'button_shape' => array(__('Corners', 'wp-top-info-bar'), array('square' => __('Square', 'wp-top-info-bar'), 'rounded' => __('Rounded', 'wp-top-info-bar'), 'round' => __('Round', 'wp-top-info-bar'))),
			'button_style' => array(__('Background', 'wp-top-info-bar'), array('filled' => __('Filled', 'wp-top-info-bar'), 'bordered' => __('Border', 'wp-top-info-bar'), 'underline' => __('Underline', 'wp-top-info-bar'))),
			'button_color' => array(__('Fill / border color', 'wp-top-info-bar'), 'color'),
			'button_text_color' => array(__('Text color', 'wp-top-info-bar'), 'color'),
		);
		$fields = array_replace(array_flip(array('button_text', 'button_url', 'button_style', 'button_shape', 'button_size', 'button_color', 'button_text_color')), $fields);
		echo '<table class="form-table wp-top-info-bar-button-settings" role="presentation"><tbody>';
		foreach ($fields as $key => $field) {
			list($label, $type) = $field;
			$id = 'wp-top-info-bar-' . str_replace('_', '-', $key);
			$name = WP_Top_Info_Bar_Settings::OPTION_NAME . '[' . $key . ']';
			if ('button_size' === $key) {
				echo '<tr data-button-setting="button_size"><th>';
				$this->image_device_heading($label, '');
				echo '</th><td>';
				foreach (array('desktop' => '', 'tablet' => '_tablet', 'mobile' => '_mobile') as $device => $suffix) {
					echo '<div class="wp-top-info-bar-admin__responsive-device" data-device="' . esc_attr($device) . '"' . ('' === $suffix ? '' : ' hidden') . '><select id="' . esc_attr($id . $suffix) . '" name="' . esc_attr(WP_Top_Info_Bar_Settings::OPTION_NAME . '[button_size' . $suffix . ']') . '">';
					$value = $settings['button_size' . $suffix] ?? '';
					if ('' !== $suffix) { echo '<option value=""' . selected($value, '', false) . '>' . esc_html__('Inherit', 'wp-top-info-bar') . '</option>'; }
					foreach ($type as $option => $text) { echo '<option value="' . esc_attr($option) . '"' . selected($value, $option, false) . '>' . esc_html($text) . '</option>'; }
					echo '</select></div>';
				}
				echo '</td></tr>'; continue;
			}
			echo '<tr data-button-setting="' . esc_attr($key) . '"><th><label for="' . esc_attr($id) . '">' . esc_html($label) . '</label></th><td>';
			if (is_array($type)) {
				echo '<select' . (in_array($key, array('button_shape', 'button_style'), true) ? ' hidden aria-hidden="true" tabindex="-1"' : '') . ' id="' . esc_attr($id) . '" name="' . esc_attr($name) . '">';
				foreach ($type as $value => $text) { echo '<option value="' . esc_attr($value) . '"' . selected($settings[$key], $value, false) . '>' . esc_html($text) . '</option>'; }
				echo '</select>';
				if (in_array($key, array('button_shape', 'button_style'), true)) {
					echo '<div class="wp-top-info-bar-button-choices" role="group" aria-label="' . esc_attr($label) . '">';
					foreach ($type as $value => $text) {
						echo '<button type="button" class="wp-top-info-bar-button-choice" data-button-choice="' . esc_attr($key) . '" data-value="' . esc_attr($value) . '" aria-pressed="' . ($settings[$key] === $value ? 'true' : 'false') . '"><span class="wp-top-info-bar-button-choice__sample wp-top-info-bar-button-choice__sample--' . esc_attr($value) . '">' . esc_html($text) . '</span></button>';
					}
					echo '</div>';
				}
			} elseif ('color' === $type) {
				$this->color($key, $settings[$key], 'button_color' === $key ? '#2271b1' : '#ffffff');
			} elseif ('checkbox' === $type) {
				echo '<div class="wp-top-info-bar-button-new-tab"><input type="hidden" name="' . esc_attr($name) . '" value="0"><label><input type="checkbox" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="1"' . checked(! empty($settings[$key]), true, false) . '> ' . esc_html($label) . '</label></div>';
			} else {
				echo '<input class="regular-text" type="' . esc_attr($type) . '" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($settings[$key]) . '">';
			}
			if ('button_url' === $key) {
				$tab_name = WP_Top_Info_Bar_Settings::OPTION_NAME . '[button_new_tab]';
				echo '<div class="wp-top-info-bar-button-new-tab"><input type="hidden" name="' . esc_attr($tab_name) . '" value="0"><label><input type="checkbox" id="wp-top-info-bar-button-new-tab" name="' . esc_attr($tab_name) . '" value="1"' . checked(! empty($settings['button_new_tab']), true, false) . '> ' . esc_html__('Open in a new window', 'wp-top-info-bar') . '</label></div>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	public function render_enabled_field(): void {
		$settings = $this->settings->get_settings();
		?>
		<label for="wp-top-info-bar-enabled">
			<input
				type="checkbox"
				id="wp-top-info-bar-enabled"
				name="<?php echo esc_attr(WP_Top_Info_Bar_Settings::OPTION_NAME); ?>[enabled]"
				value="1"
				<?php checked(1, (int) $settings['enabled']); ?>
			/>
			<?php echo esc_html__('Enable bar', 'wp-top-info-bar'); ?>
		</label>
		<?php
	}

	public function render_message_field(): void {
		$settings = $this->settings->get_settings();
		?>
		<textarea
			id="wp-top-info-bar-message"
			class="large-text wp-top-info-bar-text-editor"
			name="<?php echo esc_attr(WP_Top_Info_Bar_Settings::OPTION_NAME); ?>[message]"
			rows="5"
			cols="60"
		><?php echo esc_textarea($settings['message']); ?></textarea>
		<?php
	}

	public function render_background_color_field(): void {
		$settings = $this->settings->get_settings();
		$this->color('background_color', $settings['background_color'], '#1f2937');
	}

	public function render_text_color_field(): void {
		$settings = $this->settings->get_settings();
		$this->color('text_color', $settings['text_color'], '#ffffff');
	}

	public function render_animation_field(): void {
		$settings = $this->settings->get_settings();
		$options = array(
			'none'        => __('None', 'wp-top-info-bar'),
			'marquee'     => __('Marquee', 'wp-top-info-bar'),
			'fade-in'     => __('Fade in', 'wp-top-info-bar'),
			'slide-up'    => __('Slide up', 'wp-top-info-bar'),
			'from-left'   => __('Fade from left', 'wp-top-info-bar'),
			'from-right'  => __('Fade from right', 'wp-top-info-bar'),
			'page-left'   => __('Slide from page left', 'wp-top-info-bar'),
			'page-right'  => __('Slide from page right', 'wp-top-info-bar'),
			'blink'       => __('Blink', 'wp-top-info-bar'),
			'scale-pulse' => __('Scale up', 'wp-top-info-bar'),
		);
		foreach (array('desktop' => '', 'tablet' => '_tablet', 'mobile' => '_mobile') as $device => $suffix) {
			echo '<div class="wp-top-info-bar-admin__responsive-device" data-device="' . esc_attr($device) . '"' . ('desktop' === $device ? '' : ' hidden') . '>';
			echo '<select id="wp-top-info-bar-animation' . esc_attr($suffix) . '" name="' . esc_attr(WP_Top_Info_Bar_Settings::OPTION_NAME . '[animation' . $suffix . ']') . '">';
			
			foreach ($options as $value => $label) {
				echo '<option value="' . esc_attr($value) . '"' . selected($settings['animation' . $suffix], $value, false) . '>' . esc_html($label) . '</option>';
			}
			if ('' !== $suffix) { echo '<option value=""' . selected($settings['animation' . $suffix], '', false) . '>' . esc_html__('Inherit', 'wp-top-info-bar') . '</option>'; }
			echo '</select></div>';
		}
	}

	public function render_marquee_speed_field(): void { $this->render_animation_value('marquee_speed', 'number', '60'); }
	public function render_text_delay_field(): void { $this->render_animation_value('text_delay', 'text', '2000'); }

	private function render_animation_value(string $key, string $type, string $placeholder): void {
		$settings = $this->settings->get_settings();
		$id = 'marquee_speed' === $key ? 'marquee-speed' : 'text-delay';
		foreach (array('desktop' => '', 'tablet' => '_tablet', 'mobile' => '_mobile') as $device => $suffix) {
			echo '<div class="wp-top-info-bar-admin__responsive-device" data-device="' . esc_attr($device) . '"' . ('desktop' === $device ? '' : ' hidden') . '>';
			echo '<input type="' . esc_attr($type) . '" id="wp-top-info-bar-' . esc_attr($id . $suffix) . '" name="' . esc_attr(WP_Top_Info_Bar_Settings::OPTION_NAME . '[' . $key . $suffix . ']') . '" value="' . esc_attr((string) $settings[$key . $suffix]) . '" class="regular-text" placeholder="' . ('' === $suffix ? esc_attr($placeholder) : esc_attr__('Inherit', 'wp-top-info-bar')) . '"' . ('number' === $type ? ' min="1" max="1000" step="any"' : '') . '></div>';
		}
	}

	public function render_font_size_field(): void {
		$settings = $this->settings->get_settings();
		foreach (array('desktop' => '', 'tablet' => '_tablet', 'mobile' => '_mobile') as $device => $suffix) {
			printf('<input type="text" class="regular-text wp-top-info-bar-admin__device-input" data-device="%1$s" id="wp-top-info-bar-font-size%2$s" name="%3$s[font_size%2$s]" value="%4$s" aria-label="%5$s"%6$s>', esc_attr($device), esc_attr($suffix), esc_attr(WP_Top_Info_Bar_Settings::OPTION_NAME), esc_attr($settings['font_size' . $suffix]), esc_attr(sprintf(__('Text size: %s', 'wp-top-info-bar'), $device)), 'desktop' === $device ? '' : ' hidden');
		}
	}

	public function render_padding_field(): void { $this->render_spacing('padding'); }

	public function render_margin_field(): void { $this->render_spacing('margin'); }

	private function render_spacing(string $key): void {
		$settings = $this->settings->get_settings();
		echo '<div data-spacing-options' . (empty($settings[$key . '_enabled']) ? ' hidden' : '') . '>';
		$this->spacing_fields($key, $settings, WP_Top_Info_Bar_Settings::OPTION_NAME);
		echo '</div>';
	}

	private function spacing_fields(string $key, array $settings, string $name_prefix): void {
		$labels = array('top' => __('Top', 'wp-top-info-bar'), 'right' => __('Right', 'wp-top-info-bar'), 'bottom' => __('Bottom', 'wp-top-info-bar'), 'left' => __('Left', 'wp-top-info-bar'));
		foreach (array('desktop' => '', 'tablet' => '_tablet', 'mobile' => '_mobile') as $device => $suffix) {
			$values = isset($settings[$key . '_sides'][$device]) && is_array($settings[$key . '_sides'][$device]) ? $settings[$key . '_sides'][$device] : WP_Top_Info_Bar_Sanitizer::spacing_sides((string) ($settings[$key . $suffix] ?? ''));
			echo '<div class="wp-top-info-bar-admin__spacing-inputs" data-device="' . esc_attr($device) . '"' . ('desktop' === $device ? '' : ' hidden') . '>';
			foreach ($labels as $side => $label) {
				$value = (string) ($values[$side] ?? '');
				$number = preg_match('/^(-?(?:\d+(?:\.\d+)?|\.\d+))(?:px)?$/i', trim($value), $match) ? (string) (float) $match[1] : '';
				printf('<label><span>%1$s (px)</span><input type="number" step="any"%8$s class="regular-text" data-side="%2$s" value="%6$s" aria-label="%7$s"><input type="hidden" class="wp-top-info-bar-admin__spacing-value" name="%3$s[%4$s_sides][%5$s][%2$s]" value="%9$s"></label>', esc_html($label), esc_attr($side), esc_attr($name_prefix), esc_attr($key), esc_attr($device), esc_attr($number), esc_attr(ucfirst($key) . ': ' . $label . ' (px, ' . $device . ')'), 'padding' === $key ? ' min="0"' : '', esc_attr($value));
			}
			echo '</div>';
		}
	}

	public function render_custom_class_field(): void {
		$settings = $this->settings->get_settings();
		?>
		<input
			type="text"
			id="wp-top-info-bar-custom-class"
			name="<?php echo esc_attr(WP_Top_Info_Bar_Settings::OPTION_NAME); ?>[custom_class]"
			value="<?php echo esc_attr($settings['custom_class']); ?>"
			class="regular-text"
		/>
		<?php
	}

	public function render_custom_id_field(): void {
		$settings = $this->settings->get_settings();
		?>
		<input
			type="text"
			id="wp-top-info-bar-custom-id"
			name="<?php echo esc_attr(WP_Top_Info_Bar_Settings::OPTION_NAME); ?>[custom_id]"
			value="<?php echo esc_attr($settings['custom_id']); ?>"
			class="regular-text"
		/>
		<?php
	}

	public function render_images_field(): void {
		$settings = $this->settings->get_settings();
		$images = is_array($settings['images']) ? $settings['images'] : array();
		?>
		<div class="wp-top-info-bar-images">
			<input type="hidden" name="<?php echo esc_attr(WP_Top_Info_Bar_Settings::OPTION_NAME); ?>[image_position_independent]" value="1">
			<input type="hidden" name="<?php echo esc_attr(WP_Top_Info_Bar_Settings::OPTION_NAME); ?>[images_independent]" value="1">
			<button type="button" data-add-image-row hidden></button>
			<div class="wp-top-info-bar-image-modal" data-image-modal role="dialog" aria-modal="true" aria-labelledby="wp-top-info-bar-image-modal-title" hidden>
				<div class="wp-top-info-bar-image-modal__backdrop" data-close-image-modal></div>
				<div class="wp-top-info-bar-image-modal__panel">
					<div class="wp-top-info-bar-image-modal__header"><h2 id="wp-top-info-bar-image-modal-title"></h2><button type="button" class="wp-top-info-bar-image-modal__close" data-close-image-modal aria-label="<?php esc_attr_e('Close', 'wp-top-info-bar'); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></div>
					<div class="wp-top-info-bar-image-modal__body" data-images-list>
						<?php foreach ($images as $index => $image) { $this->render_single_image_row((int) $index, $image); } ?>
					</div>
					<div class="wp-top-info-bar-image-modal__footer"><button type="button" class="button-link wp-top-info-bar-clone" data-clone-active-image><?php esc_html_e('Clone', 'wp-top-info-bar'); ?></button><button type="button" class="button-link-delete" data-remove-active-image><?php esc_html_e('Remove', 'wp-top-info-bar'); ?></button><button type="button" class="button" data-close-image-modal><?php esc_html_e('Done', 'wp-top-info-bar'); ?></button></div>
				</div>
			</div>
		</div>
		<script type="text/html" id="tmpl-wp-top-info-bar-image-row">
			<?php $this->render_single_image_row('{{{data.index}}}', array('image_id' => 0, 'size' => '40px', 'margin_enabled' => 0, 'animation_enabled' => 0, 'custom_class_enabled' => 1, 'override_enabled' => 1, 'override_initialized' => 1, 'override_fields' => array_fill_keys(array_keys(WP_Top_Info_Bar_Sanitizer::IMAGE_OVERRIDE_GROUPS), 1))); ?>
		</script>
		<?php
	}

	private function render_single_image_row($index, array $image, bool $shared = false): void {
		$image_id = isset($image['image_id']) ? absint($image['image_id']) : 0;
		$animation = isset($image['animation']) ? (string) $image['animation'] : 'none';
		$delay = isset($image['delay']) ? (string) $image['delay'] : '';
		$custom_class = isset($image['custom_class']) ? (string) $image['custom_class'] : '';
		$image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';
		$file = $image_id ? get_attached_file($image_id) : '';
		$filename = $file ? wp_basename($file) : ($image_url ? wp_basename((string) wp_parse_url($image_url, PHP_URL_PATH)) : '');
		$field_name = WP_Top_Info_Bar_Settings::OPTION_NAME . ($shared ? '[image_defaults]' : '[images][' . $index . ']');
		$override = ! array_key_exists('override_enabled', $image) || ! empty($image['override_enabled']);
		$animation_options = array(
			'none'                => __('No animation', 'wp-top-info-bar'),
			'rotate-swing-top'    => __('Swing from top', 'wp-top-info-bar'),
			'rotate-swing-bottom' => __('Swing from bottom', 'wp-top-info-bar'),
			'slide-sideways'      => __('Move left and right', 'wp-top-info-bar'),
			'blink'               => __('Blink', 'wp-top-info-bar'),
			'scale-pulse'         => __('Scale up', 'wp-top-info-bar'),
		);
		?>
		<div class="wp-top-info-bar-image-row" <?php echo $shared ? 'data-image-shared' : 'data-image-row hidden'; ?>>
			<?php if (! $shared) : ?>
			<input type="hidden" name="<?php echo esc_attr($field_name); ?>[image_id]" value="<?php echo esc_attr((string) $image_id); ?>" data-image-id />

			<?php endif; ?>
			<div class="wp-top-info-bar-image-general-settings">
			<div class="wp-top-info-bar-image-row__grid">
				<?php if (! $shared) : ?>
				<div class="wp-top-info-bar-image-row__field wp-top-info-bar-image-row__field--preview">
					<div class="wp-top-info-bar-image-row__title" data-image-heading><?php echo esc_html(sprintf(__('Image %d', 'wp-top-info-bar'), is_numeric($index) ? (int) $index + 1 : 1)); ?></div>
				<div class="wp-top-info-bar-image-row__summary">
				<div class="wp-top-info-bar-image-row__preview<?php echo $image_url ? ' has-image' : ''; ?>" data-image-preview role="img" aria-label="<?php esc_attr_e('Image preview', 'wp-top-info-bar'); ?>">
					<?php if ($image_url) : ?><img src="<?php echo esc_url($image_url); ?>" alt="" /><?php endif; ?>
				</div>
				<span class="wp-top-info-bar-image-row__filename" data-image-filename><?php echo esc_html(rawurldecode($filename)); ?></span>
				</div>
				<div class="wp-top-info-bar-image-row__actions">
					<button type="button" class="button wp-top-info-bar-image-row__replace" data-select-image data-replace-image><?php echo esc_html($image_url ? __('Replace image', 'wp-top-info-bar') : __('Choose image', 'wp-top-info-bar')); ?></button>
					
				</div>
				</div>
				<input type="hidden" data-image-override name="<?php echo esc_attr($field_name); ?>[override_enabled]" value="<?php echo $override ? '1' : '0'; ?>">
				<input type="hidden" data-override-initialized name="<?php echo esc_attr($field_name); ?>[override_initialized]" value="1">
				<?php foreach (WP_Top_Info_Bar_Sanitizer::IMAGE_OVERRIDE_GROUPS as $group => $fields) : ?>
				<input type="hidden" data-image-override-field="<?php echo esc_attr($group); ?>" name="<?php echo esc_attr($field_name . '[override_fields][' . $group . ']'); ?>" value="<?php echo !empty($image['override_fields'][$group]) ? '1' : '0'; ?>">
				<?php endforeach; ?>
				<?php endif; ?>
				<div class="wp-top-info-bar-image-settings-grid" data-image-individual-options>
				<div class="wp-top-info-bar-image-row__field wp-top-info-bar-image-size-field">
					<?php $this->image_device_heading(__('Size (px)', 'wp-top-info-bar'), __('Image width in pixels. Enter a number without px. The height follows the image proportions. Empty tablet/mobile fields inherit from the larger screen.', 'wp-top-info-bar')); ?>
					<?php foreach (array('desktop' => '', 'tablet' => '_tablet', 'mobile' => '_mobile') as $device => $suffix) :
						$value = (string) ($image['size' . $suffix] ?? '');
						$number = preg_match('/^(-?(?:\d+(?:\.\d+)?|\.\d+))(?:px)?$/i', trim($value), $match) ? (string) (float) $match[1] : '';
					?>
					<div class="wp-top-info-bar-admin__responsive-device" data-device="<?php echo esc_attr($device); ?>"<?php echo 'desktop' === $device ? '' : ' hidden'; ?>>
						<input type="number" min="0" step="any" class="regular-text" data-image-size value="<?php echo esc_attr($number); ?>" aria-label="<?php echo esc_attr(sprintf(__('Image size (px): %s', 'wp-top-info-bar'), $device)); ?>">
						<input type="hidden" class="wp-top-info-bar-image-size-value" name="<?php echo esc_attr($field_name . '[size' . $suffix . ']'); ?>" value="<?php echo esc_attr($value); ?>">
					</div>
					<?php endforeach; ?>
				</div>
				<?php if (! $shared) : ?>
				<?php foreach (array('', '_tablet', '_mobile') as $suffix) : ?>
				<input type="hidden" data-image-legacy-position="<?php echo esc_attr('' === $suffix ? 'desktop' : substr($suffix, 1)); ?>" name="<?php echo esc_attr($field_name . '[position' . $suffix . ']'); ?>" value="<?php echo esc_attr($image['position' . $suffix] ?? ('' === $suffix ? 'left' : '')); ?>">
				<?php endforeach; ?>
				<?php endif; ?>

				<div class="wp-top-info-bar-image-row__field wp-top-info-bar-image-margin-field">
					<?php $this->image_device_heading(__('Margin', 'wp-top-info-bar'), '', $this->image_option_toggle($field_name, $image, 'margin', __('Margin', 'wp-top-info-bar'), __('Outer spacing in pixels. Enter numbers without px; negative values are allowed. Empty tablet/mobile sides inherit from the larger screen.', 'wp-top-info-bar'), $shared)); ?>
					<div data-image-option-value<?php echo (! $shared || WP_Top_Info_Bar_Sanitizer::image_option_enabled($image, 'margin')) ? '' : ' hidden'; ?>><?php $this->spacing_fields('margin', $image, $field_name); ?></div>
				</div>


				

				</div>
			</div>
			</div>
			<div class="wp-top-info-bar-image-animation-settings" data-image-individual-options>
				<div class="wp-top-info-bar-image-row__field">
					<?php echo $this->image_option_toggle($field_name, $image, 'animation', __('Animation', 'wp-top-info-bar'), __('Choose an animation for this image. Swing from top moves the lower part, Swing from bottom moves the upper part, Move left and right shifts the image horizontally, Blink fades the image in and out, and Scale up gently enlarges the image.', 'wp-top-info-bar'), $shared); ?>
					<div data-image-option-value<?php echo (! $shared || WP_Top_Info_Bar_Sanitizer::image_option_enabled($image, 'animation')) ? '' : ' hidden'; ?>>
					<select name="<?php echo esc_attr($field_name); ?>[animation]">
						<?php foreach ($animation_options as $value => $label) : ?>
							<option value="<?php echo esc_attr($value); ?>" <?php selected($animation, $value); ?>>
								<?php echo esc_html($label); ?>
							</option>
						<?php endforeach; ?>
					</select>
					</div>
				</div>

				<div class="wp-top-info-bar-image-row__field" data-image-delay-field<?php echo (! $shared || WP_Top_Info_Bar_Sanitizer::image_option_enabled($image, 'animation')) ? '' : ' hidden'; ?>>
					<label class="wp-top-info-bar-image-row__label-with-tooltip"><?php echo $this->get_settings_field_title_markup(__('Delay', 'wp-top-info-bar'), __('Set when the image animation should start. Enter 2000, 2000ms or 2s. A plain number is treated as milliseconds. Applies only while Animation is enabled.', 'wp-top-info-bar')); ?></label>
					<input type="text" class="regular-text" name="<?php echo esc_attr($field_name); ?>[delay]" value="<?php echo esc_attr($delay); ?>" placeholder="2000" />
				</div>


			</div>
			<div class="wp-top-info-bar-image-custom-settings" data-image-individual-options>
				<div class="wp-top-info-bar-image-row__field">
					<?php echo $this->image_option_toggle($field_name, $image, 'custom_class', __('Custom class', 'wp-top-info-bar'), __('Add one or more CSS classes to target this image, for example: promo-icon highlighted.', 'wp-top-info-bar'), $shared); ?>
					<div data-image-option-value<?php echo (! $shared || WP_Top_Info_Bar_Sanitizer::image_option_enabled($image, 'custom_class')) ? '' : ' hidden'; ?>>
					<input type="text" class="regular-text" name="<?php echo esc_attr($field_name); ?>[custom_class]" value="<?php echo esc_attr($custom_class); ?>" />
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	private function get_settings_field_title_markup(string $label, string $tooltip): string {
		return sprintf(
			'<span class="wp-top-info-bar-label-with-tooltip"><span>%1$s</span>%2$s</span>',
			esc_html($label),
			'' !== trim($tooltip) ? $this->get_tooltip_markup($tooltip, $label) : ''
		);
	}

	private function image_option_toggle(string $prefix, array $image, string $key, string $label, string $help, bool $shared = true): string {
		if (! $shared && ! in_array($key, array('margin', 'animation'), true)) {
			return '<input type="hidden" name="' . esc_attr($prefix . '[' . $key . '_enabled]') . '" value="' . (WP_Top_Info_Bar_Sanitizer::image_option_enabled($image, $key) ? '1' : '0') . '">' . $this->get_settings_field_title_markup($label, $help);
		}
		return '<div class="wp-top-info-bar-admin__switch"><input type="hidden" name="' . esc_attr($prefix . '[' . $key . '_enabled]') . '" value="0"><label><input type="checkbox" data-image-option="' . esc_attr($key) . '" name="' . esc_attr($prefix . '[' . $key . '_enabled]') . '" value="1"' . checked(WP_Top_Info_Bar_Sanitizer::image_option_enabled($image, $key), true, false) . '> ' . esc_html($label) . '</label>' . '</div>';
	}

	private function image_device_heading(string $label, string $help, string $toggle = ''): void {
		echo '<div class="wp-top-info-bar-image-row__responsive-heading">' . ('' !== $toggle ? $toggle : $this->get_settings_field_title_markup($label, $help)) . '<span class="wp-top-info-bar-admin__devices" role="group" aria-label="' . esc_attr($label . ' - ' . __('Device', 'wp-top-info-bar')) . '">';
		foreach (array('desktop' => array(__('Desktop', 'wp-top-info-bar'), 'dashicons-desktop'), 'tablet' => array(__('Tablet', 'wp-top-info-bar'), 'dashicons-tablet'), 'mobile' => array(__('Mobile', 'wp-top-info-bar'), 'dashicons-smartphone')) as $device => $meta) {
			echo '<button type="button" class="wp-top-info-bar-admin__device" data-device="' . esc_attr($device) . '" aria-label="' . esc_attr($meta[0]) . '" title="' . esc_attr($meta[0]) . '" aria-pressed="' . ('desktop' === $device ? 'true' : 'false') . '"><span class="dashicons ' . esc_attr($meta[1]) . '" aria-hidden="true"></span></button>';
		}
		echo '</span></div>';
	}

	private function get_tooltip_markup(string $tooltip, string $alt_text): string {
		return sprintf(
			'<span class="wp-top-info-bar-tooltip" tabindex="0" aria-label="%1$s"><img src="%2$s" alt="%3$s" class="wp-top-info-bar-tooltip__icon" /><span class="wp-top-info-bar-tooltip__content" role="tooltip">%4$s</span></span>',
			esc_attr($tooltip),
			esc_url(WP_TOP_INFO_BAR_URL . 'assets/img/info.svg'),
			/* translators: %s: setting name */
			esc_attr(sprintf(__('%s help', 'wp-top-info-bar'), $alt_text)),
			esc_html($tooltip)
		);
	}

	private function color(string $key, string $value, string $default): void {
		printf('<span class="wp-top-info-bar-color-pick" data-alpha="1" data-default="%1$s"><input type="hidden" class="wp-top-info-bar-color-pick__value" name="%2$s[%3$s]" value="%4$s"><button type="button" class="wp-top-info-bar-color-pick__swatch" aria-label="%5$s" aria-expanded="false"><span class="wp-top-info-bar-color-pick__chip" aria-hidden="true"></span></button></span>', esc_attr($default), esc_attr(WP_Top_Info_Bar_Settings::OPTION_NAME), esc_attr($key), esc_attr($value), esc_attr__('Choose a color', 'wp-top-info-bar'));
	}

	private function get_responsive_inputs_markup(string $name_prefix, string $base_key, array $values, array $placeholders = array(), string $id_prefix = ''): string {
		$devices = array(
			'desktop' => array(
				'icon'   => 'pc.svg',
				'suffix' => '',
				'tooltip' => __('Desktop: default value for screens wider than 1024px.', 'wp-top-info-bar'),
			),
			'tablet' => array(
				'icon'   => 'tablet.svg',
				'suffix' => '_tablet',
				'tooltip' => __('Tablet: applied on screens up to 1024px wide.', 'wp-top-info-bar'),
			),
			'mobile' => array(
				'icon'   => 'mobil.svg',
				'suffix' => '_mobile',
				'tooltip' => __('Mobile: applied on screens up to 767px wide.', 'wp-top-info-bar'),
			),
		);

		$markup = '<div class="wp-top-info-bar-responsive-inputs">';

		foreach ($devices as $device => $config) {
			$value = isset($values[$device]) ? (string) $values[$device] : '';
			$placeholder = isset($placeholders[$device]) ? (string) $placeholders[$device] : '';
			$field_name = $name_prefix . '[' . $base_key . $config['suffix'] . ']';
			$field_id = '' !== $id_prefix ? $id_prefix . $config['suffix'] : '';

			$markup .= '<span class="wp-top-info-bar-responsive-inputs__item">';
			$markup .= sprintf(
				'<span class="wp-top-info-bar-tooltip wp-top-info-bar-tooltip--device" tabindex="0" aria-label="%1$s"><img src="%2$s" alt="" class="wp-top-info-bar-device-icons__icon" aria-hidden="true" /><span class="wp-top-info-bar-tooltip__content" role="tooltip">%3$s</span></span>',
				esc_attr($config['tooltip']),
				esc_url(WP_TOP_INFO_BAR_URL . 'assets/img/' . $config['icon']),
				esc_html($config['tooltip'])
			);
			$markup .= '<input type="text" class="regular-text"'
				. ('' !== $field_id ? ' id="' . esc_attr($field_id) . '"' : '')
				. ' name="' . esc_attr($field_name) . '"'
				. ' value="' . esc_attr($value) . '"'
				. ('' !== $placeholder ? ' placeholder="' . esc_attr($placeholder) . '"' : '')
				. ' />';
			$markup .= '</span>';
		}

		$markup .= '</div>';

		return $markup;
	}
}

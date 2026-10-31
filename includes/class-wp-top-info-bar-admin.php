<?php

if (! defined('ABSPATH')) {
	exit;
}

final class WP_Top_Info_Bar_Admin {
	private WP_Top_Info_Bar_Settings $settings;
	private WP_Top_Info_Bar_Sanitizer $sanitizer;
	private const SAVE_NONCE = 'wp_top_info_bar_save';

	public function __construct(WP_Top_Info_Bar_Settings $settings, WP_Top_Info_Bar_Sanitizer $sanitizer) {
		$this->settings = $settings;
		$this->sanitizer = $sanitizer;

		add_action('admin_menu', array($this, 'register_admin_menu'));
		add_action('init', array($this, 'register_post_type'));
		add_action('admin_init', array($this, 'migrate_settings'));
		add_action('admin_init', array($this, 'register_settings'));
		add_action('add_meta_boxes_' . WP_Top_Info_Bar_Settings::POST_TYPE, array($this, 'add_meta_boxes'));
		add_action('save_post_' . WP_Top_Info_Bar_Settings::POST_TYPE, array($this, 'save'), 10, 2);
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
		add_filter('parent_file', array($this, 'parent_menu'));
	}

	public function parent_menu($parent) {
		$screen = get_current_screen();
		return $screen && WP_Top_Info_Bar_Settings::POST_TYPE === $screen->post_type ? WP_Top_Info_Bar_Settings::MENU_SLUG : $parent;
	}

	public function register_admin_menu(): void {
		$hook = add_menu_page(
			__('Top Info Bar', 'wp-top-info-bar'),
			__('Top Info Bar', 'wp-top-info-bar'),
			'manage_options',
			WP_Top_Info_Bar_Settings::MENU_SLUG,
			array($this, 'render_settings_page'),
			'dashicons-megaphone',
			81
		);
		add_action('load-' . $hook, array($this, 'open_editor'));
	}

	public function register_post_type(): void {
		register_post_type(WP_Top_Info_Bar_Settings::POST_TYPE, array(
			'labels' => array(
				'name' => __('Top Info Bar', 'wp-top-info-bar'),
				'singular_name' => __('Top Info Bar', 'wp-top-info-bar'),
				'edit_item' => __('Edit Top Info Bar', 'wp-top-info-bar'),
				'add_new_item' => __('Add Top Info Bar', 'wp-top-info-bar'),
			),
			'public' => false, 'show_ui' => true, 'show_in_menu' => false,
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
		$id = wp_insert_post(array('post_type' => WP_Top_Info_Bar_Settings::POST_TYPE, 'post_title' => __('Top Info Bar', 'wp-top-info-bar'), 'post_status' => 'publish'), true);
		if (is_wp_error($id) || ! $id) {
			return;
		}
		update_post_meta($id, WP_Top_Info_Bar_Settings::META_KEY, wp_slash($this->sanitizer->sanitize_settings($this->settings->get_settings())));
		update_option(WP_Top_Info_Bar_Settings::GLOBAL_POST_OPTION, $id, false);
	}

	public function open_editor(): void {
		$id = absint(get_option(WP_Top_Info_Bar_Settings::GLOBAL_POST_OPTION, 0));
		$post = $id ? get_post($id) : null;
		$url = $post && WP_Top_Info_Bar_Settings::POST_TYPE === $post->post_type && 'trash' !== $post->post_status
			? admin_url('post.php?post=' . $id . '&action=edit')
			: admin_url('edit.php?post_type=' . WP_Top_Info_Bar_Settings::POST_TYPE);
		wp_safe_redirect($url);
		exit;
	}

	public function add_meta_boxes(): void {
		add_meta_box('wp-top-info-bar-settings', __('Bar settings', 'wp-top-info-bar'), array($this, 'render_settings_box'), null, 'normal', 'high');
		add_meta_box('wp-top-info-bar-shortcode', __('Shortcode', 'wp-top-info-bar'), array($this, 'render_shortcode_box'), null, 'side', 'high');
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
			'text_delay'       => array(
				'label'    => __('Text delay', 'wp-top-info-bar'),
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
			$page = in_array($id, array('padding', 'margin'), true) ? 'spacing' : (in_array($id, array('message', 'font_size', 'text_color'), true) ? 'text' : (in_array($id, array('animation', 'text_delay'), true) ? 'animation' : (in_array($id, array('custom_class', 'custom_id'), true) ? 'custom' : 'generell')));
			if ('background_color' === $id) { $page = 'background'; }
			$title = $this->get_settings_field_title_markup($field['label'], $field['tooltip']);
			if (in_array($id, array('font_size', 'padding', 'margin'), true)) {
				$title .= '<span class="wp-top-info-bar-admin__devices" role="group" aria-label="' . esc_attr($field['label'] . ' ? ' . __('Device', 'wp-top-info-bar')) . '">';
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
			'animation' => array(__('Animation', 'wp-top-info-bar'), 'dashicons-controls-play'),
			'custom' => array(__('Custom', 'wp-top-info-bar'), 'dashicons-editor-code'),
		);
	}

	public function render_settings_page(): void {
		echo '<div class="wrap"><p>' . esc_html__('Open the Top Info Bar editor to change its settings.', 'wp-top-info-bar') . '</p></div>';
	}

	public function render_settings_box($post): void {
		$this->settings = new WP_Top_Info_Bar_Settings((int) $post->ID);
		wp_nonce_field(self::SAVE_NONCE, self::SAVE_NONCE . '_nonce');
		?>
		<div class="wp-top-info-bar-settings">
				<div class="wp-top-info-bar-admin">
				<nav class="wp-top-info-bar-admin__menu" aria-label="<?php esc_attr_e('Bar settings', 'wp-top-info-bar'); ?>">
				<div class="wp-top-info-bar-admin__switch wp-top-info-bar-admin__enable"><?php $this->render_enabled_field(); ?></div>
				<?php foreach (self::pages() as $page => $meta) : ?>
					<button type="button" class="wp-top-info-bar-admin__menu-item" data-page="<?php echo esc_attr($page); ?>" aria-controls="wp-top-info-bar-page-<?php echo esc_attr($page); ?>" aria-current="<?php echo 'generell' === $page ? 'true' : 'false'; ?>"><span class="dashicons <?php echo esc_attr($meta[1]); ?>" aria-hidden="true"></span><span><?php echo esc_html($meta[0]); ?></span></button>
				<?php endforeach; ?>
				</nav>
				<div class="wp-top-info-bar-admin__settings">
				<?php foreach (self::pages() as $page => $meta) : ?>
					<fieldset class="wp-top-info-bar-admin__page" id="wp-top-info-bar-page-<?php echo esc_attr($page); ?>" data-page="<?php echo esc_attr($page); ?>"<?php echo 'generell' === $page ? '' : ' hidden'; ?>>
						<legend><?php echo esc_html($meta[0]); ?></legend>
						<?php if ('generell' === $page) : ?>
						<div class="wp-top-info-bar-admin__text-block"><table class="form-table" role="presentation"><tbody><?php do_settings_fields(WP_Top_Info_Bar_Settings::MENU_SLUG, WP_Top_Info_Bar_Settings::SETTINGS_SECTION . '_text'); ?></tbody></table></div>
						<div class="wp-top-info-bar-admin__background-block"><table class="form-table" role="presentation"><tbody><?php do_settings_fields(WP_Top_Info_Bar_Settings::MENU_SLUG, WP_Top_Info_Bar_Settings::SETTINGS_SECTION . '_background'); ?></tbody></table></div>
						<div class="wp-top-info-bar-admin__spacing-block"><table class="form-table" role="presentation"><tbody><?php do_settings_fields(WP_Top_Info_Bar_Settings::MENU_SLUG, WP_Top_Info_Bar_Settings::SETTINGS_SECTION . '_spacing'); ?></tbody></table></div>
						<?php endif; ?>
						<table class="form-table" role="presentation"><tbody><?php do_settings_fields(WP_Top_Info_Bar_Settings::MENU_SLUG, WP_Top_Info_Bar_Settings::SETTINGS_SECTION . '_' . $page); ?></tbody></table>
					</fieldset>
				<?php endforeach; ?>
				</div>
				<?php WP_Top_Info_Bar_Preview::render_panel(); ?>
				</div>
		</div>
		<?php
	}

	public function enqueue_admin_assets(string $hook_suffix): void {
		$screen = get_current_screen();
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
			array('jquery', 'wp-util', 'editor'),
			(string) filemtime(WP_TOP_INFO_BAR_PATH . 'assets/js/admin.js'),
			true
		);
		wp_enqueue_style('wp-top-info-bar-preview', WP_TOP_INFO_BAR_URL . 'assets/css/preview.css', array('wp-top-info-bar-admin'), (string) filemtime(WP_TOP_INFO_BAR_PATH . 'assets/css/preview.css'));
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
					'chooseImage'  => __('Choose image', 'wp-top-info-bar'),
					'useImage'     => __('Use this image', 'wp-top-info-bar'),
				),
			)
		);
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
			'none'        => __('No animation', 'wp-top-info-bar'),
			'fade-in'     => __('Fade in', 'wp-top-info-bar'),
			'slide-up'    => __('Slide up', 'wp-top-info-bar'),
			'from-left'   => __('Fade from left', 'wp-top-info-bar'),
			'from-right'  => __('Fade from right', 'wp-top-info-bar'),
			'page-left'   => __('Slide from page left', 'wp-top-info-bar'),
			'page-right'  => __('Slide from page right', 'wp-top-info-bar'),
			'blink'       => __('Blink', 'wp-top-info-bar'),
			'scale-pulse' => __('Scale up', 'wp-top-info-bar'),
		);
		?>
		<select
			id="wp-top-info-bar-animation"
			name="<?php echo esc_attr(WP_Top_Info_Bar_Settings::OPTION_NAME); ?>[animation]"
		>
			<?php foreach ($options as $value => $label) : ?>
				<option value="<?php echo esc_attr($value); ?>" <?php selected($settings['animation'], $value); ?>>
					<?php echo esc_html($label); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public function render_text_delay_field(): void {
		$settings = $this->settings->get_settings();
		?>
		<input
			type="text"
			id="wp-top-info-bar-text-delay"
			name="<?php echo esc_attr(WP_Top_Info_Bar_Settings::OPTION_NAME); ?>[text_delay]"
			value="<?php echo esc_attr($settings['text_delay']); ?>"
			class="regular-text"
			placeholder="2000"
		/>
		<?php
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
		$labels = array('top' => __('Top', 'wp-top-info-bar'), 'right' => __('Right', 'wp-top-info-bar'), 'bottom' => __('Bottom', 'wp-top-info-bar'), 'left' => __('Left', 'wp-top-info-bar'));
		foreach (array('desktop' => '', 'tablet' => '_tablet', 'mobile' => '_mobile') as $device => $suffix) {
			$values = isset($settings[$key . '_sides'][$device]) && is_array($settings[$key . '_sides'][$device]) ? $settings[$key . '_sides'][$device] : WP_Top_Info_Bar_Sanitizer::spacing_sides($settings[$key . $suffix]);
			echo '<div class="wp-top-info-bar-admin__spacing-inputs" data-device="' . esc_attr($device) . '"' . ('desktop' === $device ? '' : ' hidden') . '>';
			foreach ($labels as $side => $label) {
				$value = (string) ($values[$side] ?? '');
				$number = preg_match('/^(-?(?:\d+(?:\.\d+)?|\.\d+))(?:px)?$/i', trim($value), $match) ? (string) (float) $match[1] : '';
				printf('<label><span>%1$s (px)</span><input type="number" step="any"%8$s class="regular-text" data-side="%2$s" value="%6$s" aria-label="%7$s"><input type="hidden" class="wp-top-info-bar-admin__spacing-value" name="%3$s[%4$s_sides][%5$s][%2$s]" value="%9$s"></label>', esc_html($label), esc_attr($side), esc_attr(WP_Top_Info_Bar_Settings::OPTION_NAME), esc_attr($key), esc_attr($device), esc_attr($number), esc_attr(ucfirst($key) . ': ' . $label . ' (px, ' . $device . ')'), 'padding' === $key ? ' min="0"' : '', esc_attr($value));
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
			<div class="wp-top-info-bar-images__list" data-images-list>
				<?php foreach ($images as $index => $image) : ?>
					<?php $this->render_single_image_row((int) $index, $image); ?>
				<?php endforeach; ?>
			</div>

			<button type="button" class="button button-secondary" data-add-image-row>
				<?php echo esc_html__('Add image', 'wp-top-info-bar'); ?>
			</button>
		</div>

		<script type="text/html" id="tmpl-wp-top-info-bar-image-row">
			<?php
			$this->render_single_image_row(
				'{{{data.index}}}',
				array(
					'image_id'     => 0,
					'size'         => '',
					'size_tablet'  => '',
					'size_mobile'  => '',
					'margin'       => '',
					'margin_tablet'=> '',
					'margin_mobile'=> '',
					'animation'    => 'none',
					'delay'        => '',
					'custom_class' => '',
					'position'     => 'left',
				)
			);
			?>
		</script>
		<?php
	}

	private function render_single_image_row($index, array $image): void {
		$image_id = isset($image['image_id']) ? absint($image['image_id']) : 0;
		$size = isset($image['size']) ? (string) $image['size'] : '';
		$size_tablet = isset($image['size_tablet']) ? (string) $image['size_tablet'] : '';
		$size_mobile = isset($image['size_mobile']) ? (string) $image['size_mobile'] : '';
		$margin = isset($image['margin']) ? (string) $image['margin'] : '';
		$margin_tablet = isset($image['margin_tablet']) ? (string) $image['margin_tablet'] : '';
		$margin_mobile = isset($image['margin_mobile']) ? (string) $image['margin_mobile'] : '';
		$animation = isset($image['animation']) ? (string) $image['animation'] : 'none';
		$delay = isset($image['delay']) ? (string) $image['delay'] : '';
		$custom_class = isset($image['custom_class']) ? (string) $image['custom_class'] : '';
		$position = isset($image['position']) ? (string) $image['position'] : 'left';
		$image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';
		$field_name = WP_Top_Info_Bar_Settings::OPTION_NAME . '[images][' . $index . ']';
		$animation_options = array(
			'none'                => __('No animation', 'wp-top-info-bar'),
			'rotate-swing-top'    => __('Swing from top', 'wp-top-info-bar'),
			'rotate-swing-bottom' => __('Swing from bottom', 'wp-top-info-bar'),
			'slide-sideways'      => __('Move left and right', 'wp-top-info-bar'),
			'blink'               => __('Blink', 'wp-top-info-bar'),
			'scale-pulse'         => __('Scale up', 'wp-top-info-bar'),
		);
		?>
		<div class="wp-top-info-bar-image-row" data-image-row>
			<input type="hidden" name="<?php echo esc_attr($field_name); ?>[image_id]" value="<?php echo esc_attr((string) $image_id); ?>" data-image-id />

			<div class="wp-top-info-bar-image-row__grid">
				<div class="wp-top-info-bar-image-row__field wp-top-info-bar-image-row__field--preview">
					<label><?php echo esc_html__('Preview', 'wp-top-info-bar'); ?></label>
				<button
					type="button"
					class="wp-top-info-bar-image-row__preview<?php echo $image_url ? ' has-image' : ''; ?>"
					data-image-preview
					data-select-image
					aria-label="<?php echo esc_attr($image_url ? __('Change image', 'wp-top-info-bar') : __('Select image', 'wp-top-info-bar')); ?>"
					title="<?php echo esc_attr($image_url ? __('Change image', 'wp-top-info-bar') : __('Select image', 'wp-top-info-bar')); ?>"
				>
					<?php if ($image_url) : ?>
						<img src="<?php echo esc_url($image_url); ?>" alt="" />
					<?php endif; ?>
					<span class="wp-top-info-bar-image-row__preview-plus" aria-hidden="true">+</span>
					<span class="wp-top-info-bar-image-row__preview-edit" aria-hidden="true"></span>
				</button>
				</div>
				<div class="wp-top-info-bar-image-row__field">
					<label class="wp-top-info-bar-image-row__label-with-tooltip">
						<span><?php echo esc_html__('Size', 'wp-top-info-bar'); ?></span>
						<span
							class="wp-top-info-bar-tooltip"
							tabindex="0"
							aria-label="<?php echo esc_attr__('Use a standard CSS size value, for example: 40px, 2rem or 10%. This value sets the image width.', 'wp-top-info-bar'); ?>"
						>
							<img
								src="<?php echo esc_url(WP_TOP_INFO_BAR_URL . 'assets/img/info.svg'); ?>"
								alt="<?php echo esc_attr__('Size help', 'wp-top-info-bar'); ?>"
								class="wp-top-info-bar-tooltip__icon"
							/>
							<span class="wp-top-info-bar-tooltip__content" role="tooltip">
								<?php echo esc_html__('Use a standard CSS size value, for example: 40px, 2rem or 10%. This value sets the image width.', 'wp-top-info-bar'); ?>
							</span>
						</span>
					</label>
					<?php
					echo $this->get_responsive_inputs_markup(
						$field_name,
						'size',
						array(
							'desktop' => $size,
							'tablet'  => $size_tablet,
							'mobile'  => $size_mobile,
						),
						array(
							'desktop' => '40px',
							'tablet'  => '',
							'mobile'  => '',
						)
					);
					?>
				</div>

				<div class="wp-top-info-bar-image-row__field">
					<label class="wp-top-info-bar-image-row__label-with-tooltip">
						<span><?php echo esc_html__('Margin', 'wp-top-info-bar'); ?></span>
						<span
							class="wp-top-info-bar-tooltip"
							tabindex="0"
							aria-label="<?php echo esc_attr__('Use a standard CSS margin value, for example: 0 12px 0 0. For a left image, you can use: 0 auto 0 0. For a right image, you can use: 0 0 0 auto.', 'wp-top-info-bar'); ?>"
						>
							<img
								src="<?php echo esc_url(WP_TOP_INFO_BAR_URL . 'assets/img/info.svg'); ?>"
								alt="<?php echo esc_attr__('Margin help', 'wp-top-info-bar'); ?>"
								class="wp-top-info-bar-tooltip__icon"
							/>
							<span class="wp-top-info-bar-tooltip__content" role="tooltip">
								<?php echo esc_html__('Use a standard CSS margin value, for example: 0 12px 0 0. For a left image, you can use: 0 auto 0 0. For a right image, you can use: 0 0 0 auto.', 'wp-top-info-bar'); ?>
							</span>
						</span>
					</label>
					<?php
					echo $this->get_responsive_inputs_markup(
						$field_name,
						'margin',
						array(
							'desktop' => $margin,
							'tablet'  => $margin_tablet,
							'mobile'  => $margin_mobile,
						),
						array(
							'desktop' => '0 12px 0 0',
							'tablet'  => '',
							'mobile'  => '',
						)
					);
					?>
				</div>

				<div class="wp-top-info-bar-image-row__field">
					<label class="wp-top-info-bar-image-row__label-with-tooltip">
						<span><?php echo esc_html__('Custom class', 'wp-top-info-bar'); ?></span>
						<span
							class="wp-top-info-bar-tooltip"
							tabindex="0"
							aria-label="<?php echo esc_attr__('Add one or more CSS classes to target this image, for example: promo-icon highlighted.', 'wp-top-info-bar'); ?>"
						>
							<img
								src="<?php echo esc_url(WP_TOP_INFO_BAR_URL . 'assets/img/info.svg'); ?>"
								alt="<?php echo esc_attr__('Custom class help', 'wp-top-info-bar'); ?>"
								class="wp-top-info-bar-tooltip__icon"
							/>
							<span class="wp-top-info-bar-tooltip__content" role="tooltip">
								<?php echo esc_html__('Add one or more CSS classes to target this image, for example: promo-icon highlighted.', 'wp-top-info-bar'); ?>
							</span>
						</span>
					</label>
					<input type="text" class="regular-text" name="<?php echo esc_attr($field_name); ?>[custom_class]" value="<?php echo esc_attr($custom_class); ?>" />
				</div>

				<div class="wp-top-info-bar-image-row__field">
					<label class="wp-top-info-bar-image-row__label-with-tooltip">
						<span><?php echo esc_html__('Animation', 'wp-top-info-bar'); ?></span>
						<span
							class="wp-top-info-bar-tooltip"
							tabindex="0"
							aria-label="<?php echo esc_attr__('Choose an animation for this image. Swing from top moves the lower part, Swing from bottom moves the upper part, Move left and right shifts the image horizontally, Blink fades the image in and out, and Scale up gently enlarges the image.', 'wp-top-info-bar'); ?>"
						>
							<img
								src="<?php echo esc_url(WP_TOP_INFO_BAR_URL . 'assets/img/info.svg'); ?>"
								alt="<?php echo esc_attr__('Animation help', 'wp-top-info-bar'); ?>"
								class="wp-top-info-bar-tooltip__icon"
							/>
							<span class="wp-top-info-bar-tooltip__content" role="tooltip">
								<?php echo esc_html__('Choose an animation for this image. Swing from top moves the lower part, Swing from bottom moves the upper part, Move left and right shifts the image horizontally, Blink fades the image in and out, and Scale up gently enlarges the image.', 'wp-top-info-bar'); ?>
							</span>
						</span>
					</label>
					<select name="<?php echo esc_attr($field_name); ?>[animation]">
						<?php foreach ($animation_options as $value => $label) : ?>
							<option value="<?php echo esc_attr($value); ?>" <?php selected($animation, $value); ?>>
								<?php echo esc_html($label); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="wp-top-info-bar-image-row__field">
					<label class="wp-top-info-bar-image-row__label-with-tooltip">
						<span><?php echo esc_html__('Delay', 'wp-top-info-bar'); ?></span>
						<span
							class="wp-top-info-bar-tooltip"
							tabindex="0"
							aria-label="<?php echo esc_attr__('Set when the animation should start. You can enter 2000, 2000ms or 2s. A plain number is treated as milliseconds.', 'wp-top-info-bar'); ?>"
						>
							<img
								src="<?php echo esc_url(WP_TOP_INFO_BAR_URL . 'assets/img/info.svg'); ?>"
								alt="<?php echo esc_attr__('Delay help', 'wp-top-info-bar'); ?>"
								class="wp-top-info-bar-tooltip__icon"
							/>
							<span class="wp-top-info-bar-tooltip__content" role="tooltip">
								<?php echo esc_html__('Set when the animation should start. You can enter 2000, 2000ms or 2s. A plain number is treated as milliseconds.', 'wp-top-info-bar'); ?>
							</span>
						</span>
					</label>
					<input type="text" class="regular-text" name="<?php echo esc_attr($field_name); ?>[delay]" value="<?php echo esc_attr($delay); ?>" placeholder="2000" />
				</div>

				<div class="wp-top-info-bar-image-row__field">
					<label class="wp-top-info-bar-image-row__label-with-tooltip">
						<span><?php echo esc_html__('Position', 'wp-top-info-bar'); ?></span>
						<span
							class="wp-top-info-bar-tooltip"
							tabindex="0"
							aria-label="<?php echo esc_attr__('Choose where the image should appear in the bar. Left places the image before the text, Right places it after the text, Center above text places it above the message, and Center below text places it below the message.', 'wp-top-info-bar'); ?>"
						>
							<img
								src="<?php echo esc_url(WP_TOP_INFO_BAR_URL . 'assets/img/info.svg'); ?>"
								alt="<?php echo esc_attr__('Position help', 'wp-top-info-bar'); ?>"
								class="wp-top-info-bar-tooltip__icon"
							/>
							<span class="wp-top-info-bar-tooltip__content" role="tooltip">
								<?php echo esc_html__('Choose where the image should appear in the bar. Left places the image before the text, Right places it after the text, Center above text places it above the message, and Center below text places it below the message.', 'wp-top-info-bar'); ?>
							</span>
						</span>
					</label>
					<select name="<?php echo esc_attr($field_name); ?>[position]">
						<option value="left" <?php selected($position, 'left'); ?>><?php echo esc_html__('Left', 'wp-top-info-bar'); ?></option>
						<option value="right" <?php selected($position, 'right'); ?>><?php echo esc_html__('Right', 'wp-top-info-bar'); ?></option>
						<option value="center-top" <?php selected($position, 'center-top'); ?>><?php echo esc_html__('Center above text', 'wp-top-info-bar'); ?></option>
						<option value="center-bottom" <?php selected($position, 'center-bottom'); ?>><?php echo esc_html__('Center below text', 'wp-top-info-bar'); ?></option>
					</select>
				</div>

				<div class="wp-top-info-bar-image-row__field wp-top-info-bar-image-row__field--action">
					<label>&nbsp;</label>
					<button type="button" class="button-link-delete" data-remove-image-row>
						<?php echo esc_html__('Remove', 'wp-top-info-bar'); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	private function get_settings_field_title_markup(string $label, string $tooltip): string {
		return sprintf(
			'<span class="wp-top-info-bar-label-with-tooltip"><span>%1$s</span>%2$s</span>',
			esc_html($label),
			$this->get_tooltip_markup($tooltip, $label)
		);
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

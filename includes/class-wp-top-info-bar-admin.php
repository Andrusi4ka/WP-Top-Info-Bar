<?php

if (! defined('ABSPATH')) {
	exit;
}

final class WP_Top_Info_Bar_Admin {
	private WP_Top_Info_Bar_Settings $settings;
	private WP_Top_Info_Bar_Sanitizer $sanitizer;

	public function __construct(WP_Top_Info_Bar_Settings $settings, WP_Top_Info_Bar_Sanitizer $sanitizer) {
		$this->settings = $settings;
		$this->sanitizer = $sanitizer;

		add_action('admin_menu', array($this, 'register_admin_menu'));
		add_action('admin_init', array($this, 'register_settings'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
	}

	public function register_admin_menu(): void {
		add_menu_page(
			__('Top Info Bar', 'wp-top-info-bar'),
			__('Top Info Bar', 'wp-top-info-bar'),
			'manage_options',
			WP_Top_Info_Bar_Settings::MENU_SLUG,
			array($this, 'render_settings_page'),
			'dashicons-megaphone',
			81
		);
	}

	public function register_settings(): void {
		register_setting(
			WP_Top_Info_Bar_Settings::SETTINGS_GROUP,
			WP_Top_Info_Bar_Settings::OPTION_NAME,
			array($this->sanitizer, 'sanitize_settings')
		);

		add_settings_section(
			WP_Top_Info_Bar_Settings::SETTINGS_SECTION,
			__('Bar Settings', 'wp-top-info-bar'),
			function (): void {
				echo '<p>' . esc_html__('Configure the top information bar shown on the frontend.', 'wp-top-info-bar') . '</p>';
			},
			WP_Top_Info_Bar_Settings::MENU_SLUG
		);

		$fields = array(
			'enabled'          => array(
				'label'    => __('Enable bar', 'wp-top-info-bar'),
				'callback' => 'render_enabled_field',
				'tooltip'  => __('Turn the top bar on or off for the frontend. Enable it when you want the bar to appear on the site.', 'wp-top-info-bar'),
			),
			'message'          => array(
				'label'    => __('Bar text', 'wp-top-info-bar'),
				'callback' => 'render_message_field',
				'tooltip'  => __('Enter the message shown in the bar. Example: Free shipping on orders over $50.', 'wp-top-info-bar'),
			),
			'background_color' => array(
				'label'    => __('Background color', 'wp-top-info-bar'),
				'callback' => 'render_background_color_field',
				'tooltip'  => __('Choose the bar background color. Example: #1f2937 for a dark blue-gray background.', 'wp-top-info-bar'),
			),
			'text_color'       => array(
				'label'    => __('Text color', 'wp-top-info-bar'),
				'callback' => 'render_text_color_field',
				'tooltip'  => __('Choose the text color shown inside the bar. Example: #ffffff for white text.', 'wp-top-info-bar'),
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
			'font_size'        => array(
				'label'    => __('Text size', 'wp-top-info-bar'),
				'callback' => 'render_font_size_field',
				'tooltip'  => __('Use a CSS font-size value. Examples: 14px, 1rem, clamp(14px, 2vw, 18px).', 'wp-top-info-bar'),
			),
			'padding'          => array(
				'label'    => __('Padding', 'wp-top-info-bar'),
				'callback' => 'render_padding_field',
				'tooltip'  => __('Use a CSS padding value for the inside spacing of the bar. Example: 12px 16px.', 'wp-top-info-bar'),
			),
			'margin'           => array(
				'label'    => __('Margin', 'wp-top-info-bar'),
				'callback' => 'render_margin_field',
				'tooltip'  => __('Use a CSS margin value for outer spacing around the bar. Example: 0 0 20px 0.', 'wp-top-info-bar'),
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
			add_settings_field(
				$id,
				$this->get_settings_field_title_markup($field['label'], $field['tooltip']),
				array($this, $field['callback']),
				WP_Top_Info_Bar_Settings::MENU_SLUG,
				WP_Top_Info_Bar_Settings::SETTINGS_SECTION
			);
		}
	}

	public function render_settings_page(): void {
		if (! current_user_can('manage_options')) {
			return;
		}

		?>
		<div class="wrap wp-top-info-bar-settings">
			<h1><?php echo esc_html__('Top Info Bar Settings', 'wp-top-info-bar'); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields(WP_Top_Info_Bar_Settings::SETTINGS_GROUP);
				do_settings_sections(WP_Top_Info_Bar_Settings::MENU_SLUG);
				submit_button(__('Save Settings', 'wp-top-info-bar'));
				?>
			</form>
		</div>
		<?php
	}

	public function enqueue_admin_assets(string $hook_suffix): void {
		if ('toplevel_page_' . WP_Top_Info_Bar_Settings::MENU_SLUG !== $hook_suffix) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style(
			'wp-top-info-bar-admin',
			WP_TOP_INFO_BAR_URL . 'assets/css/admin.css',
			array(),
			WP_TOP_INFO_BAR_VERSION
		);
		wp_enqueue_script(
			'wp-top-info-bar-admin',
			WP_TOP_INFO_BAR_URL . 'assets/js/admin.js',
			array('jquery', 'wp-util'),
			WP_TOP_INFO_BAR_VERSION,
			true
		);

		wp_localize_script(
			'wp-top-info-bar-admin',
			'wpTopInfoBarAdmin',
			array(
				'optionName' => WP_Top_Info_Bar_Settings::OPTION_NAME,
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
			<?php echo esc_html__('Show the bar on the site frontend', 'wp-top-info-bar'); ?>
		</label>
		<?php
	}

	public function render_message_field(): void {
		$settings = $this->settings->get_settings();
		?>
		<textarea
			id="wp-top-info-bar-message"
			name="<?php echo esc_attr(WP_Top_Info_Bar_Settings::OPTION_NAME); ?>[message]"
			rows="5"
			cols="60"
			class="large-text"
		><?php echo esc_textarea($settings['message']); ?></textarea>
		<?php
	}

	public function render_background_color_field(): void {
		$settings = $this->settings->get_settings();
		?>
		<input
			type="color"
			id="wp-top-info-bar-background-color"
			name="<?php echo esc_attr(WP_Top_Info_Bar_Settings::OPTION_NAME); ?>[background_color]"
			value="<?php echo esc_attr($settings['background_color']); ?>"
		/>
		<?php
	}

	public function render_text_color_field(): void {
		$settings = $this->settings->get_settings();
		?>
		<input
			type="color"
			id="wp-top-info-bar-text-color"
			name="<?php echo esc_attr(WP_Top_Info_Bar_Settings::OPTION_NAME); ?>[text_color]"
			value="<?php echo esc_attr($settings['text_color']); ?>"
		/>
		<?php
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
		?>
		<?php
		echo $this->get_responsive_inputs_markup(
			WP_Top_Info_Bar_Settings::OPTION_NAME,
			'font_size',
			array(
				'desktop' => $settings['font_size'],
				'tablet'  => $settings['font_size_tablet'],
				'mobile'  => $settings['font_size_mobile'],
			),
			array(
				'desktop' => '14px',
				'tablet'  => '',
				'mobile'  => '',
			),
			'wp-top-info-bar-font-size'
		);
		?>
		<?php
	}

	public function render_padding_field(): void {
		$settings = $this->settings->get_settings();
		?>
		<?php
		echo $this->get_responsive_inputs_markup(
			WP_Top_Info_Bar_Settings::OPTION_NAME,
			'padding',
			array(
				'desktop' => $settings['padding'],
				'tablet'  => $settings['padding_tablet'],
				'mobile'  => $settings['padding_mobile'],
			),
			array(
				'desktop' => '12px 16px',
				'tablet'  => '',
				'mobile'  => '',
			),
			'wp-top-info-bar-padding'
		);
		?>
		<?php
	}

	public function render_margin_field(): void {
		$settings = $this->settings->get_settings();
		?>
		<?php
		echo $this->get_responsive_inputs_markup(
			WP_Top_Info_Bar_Settings::OPTION_NAME,
			'margin',
			array(
				'desktop' => $settings['margin'],
				'tablet'  => $settings['margin_tablet'],
				'mobile'  => $settings['margin_mobile'],
			),
			array(
				'desktop' => '0 0 20px 0',
				'tablet'  => '',
				'mobile'  => '',
			),
			'wp-top-info-bar-margin'
		);
		?>
		<?php
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

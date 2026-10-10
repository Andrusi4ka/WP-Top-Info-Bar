<?php

if (! defined('ABSPATH')) {
	exit;
}

final class WP_Top_Info_Bar_Sanitizer {
	/**
	 * @param mixed $input Submitted settings (anything: options.php passes on what it got).
	 */
	public function sanitize_settings($input): array {
		$input = is_array($input) ? $input : array();

		return array(
			'enabled'          => empty($input['enabled']) ? 0 : 1,
			'message'          => sanitize_textarea_field($this->text($input, 'message')),
			'background_color' => $this->sanitize_hex_color($this->text($input, 'background_color', '#1f2937')),
			'text_color'       => $this->sanitize_hex_color($this->text($input, 'text_color', '#ffffff'), '#ffffff'),
			'animation'        => $this->sanitize_animation($this->text($input, 'animation', 'none')),
			'text_delay'       => $this->sanitize_animation_delay($this->text($input, 'text_delay', '')),
			'font_size'        => $this->sanitize_css_size($this->text($input, 'font_size', '14px'), '14px'),
			'font_size_tablet' => $this->sanitize_css_size($this->text($input, 'font_size_tablet', ''), ''),
			'font_size_mobile' => $this->sanitize_css_size($this->text($input, 'font_size_mobile', ''), ''),
			'padding'          => $this->sanitize_spacing_value($this->text($input, 'padding', '12px 16px')),
			'padding_tablet'   => $this->sanitize_spacing_value($this->text($input, 'padding_tablet', '')),
			'padding_mobile'   => $this->sanitize_spacing_value($this->text($input, 'padding_mobile', '')),
			'margin'           => $this->sanitize_spacing_value($this->text($input, 'margin', '')),
			'margin_tablet'    => $this->sanitize_spacing_value($this->text($input, 'margin_tablet', '')),
			'margin_mobile'    => $this->sanitize_spacing_value($this->text($input, 'margin_mobile', '')),
			'custom_class'     => $this->sanitize_class_list($this->text($input, 'custom_class', '')),
			'custom_id'        => $this->sanitize_html_identifier($this->text($input, 'custom_id', '')),
			'images'           => $this->sanitize_images($input['images'] ?? array()),
		);
	}

	public function sanitize_hex_color(string $color, string $fallback = '#1f2937'): string {
		$sanitized = sanitize_hex_color($color);

		return $sanitized ? $sanitized : $fallback;
	}

	public function sanitize_spacing_value(string $value): string {
		$value = sanitize_text_field($value);

		return preg_replace('/[^0-9a-zA-Z.%\-\s(),\/]/', '', $value) ?? '';
	}

	public function sanitize_css_size(string $value, string $fallback): string {
		$value = sanitize_text_field($value);
		$sanitized = preg_replace('/[^0-9a-zA-Z.%\-\s(),\/]/', '', $value) ?? '';

		return '' !== trim($sanitized) ? $sanitized : $fallback;
	}

	public function sanitize_class_list(string $classes): string {
		$classes = preg_split('/\s+/', sanitize_text_field($classes)) ?: array();
		$classes = array_filter(array_map('sanitize_html_class', $classes));

		return implode(' ', $classes);
	}

	public function sanitize_html_identifier(string $value): string {
		return sanitize_html_class(sanitize_text_field($value));
	}

	public function sanitize_animation(string $value): string {
		$allowed = array('none', 'fade-in', 'slide-up', 'from-left', 'from-right', 'page-left', 'page-right', 'blink', 'scale-pulse');
		$value = sanitize_key($value);

		return in_array($value, $allowed, true) ? $value : 'none';
	}

	public function sanitize_image_animation(string $value): string {
		$allowed = array('none', 'rotate-swing-top', 'rotate-swing-bottom', 'slide-sideways', 'blink', 'scale-pulse');
		$value = sanitize_key($value);

		return in_array($value, $allowed, true) ? $value : 'none';
	}

	public function sanitize_animation_delay(string $value): string {
		$value = strtolower(trim(sanitize_text_field($value)));

		if ('' === $value) {
			return '';
		}

		if (preg_match('/^\d+(?:\.\d+)?$/', $value)) {
			return $value . 'ms';
		}

		if (preg_match('/^\d+(?:\.\d+)?(?:ms|s)$/', $value)) {
			return $value;
		}

		return '';
	}

	public function sanitize_images($images): array {
		if (! is_array($images)) {
			return array();
		}

		$sanitized_images = array();

		foreach ($images as $image) {
			if (! is_array($image)) {
				continue;
			}

			$image_id = absint($this->text($image, 'image_id'));

			if ($image_id <= 0) {
				continue;
			}

			$sanitized_images[] = array(
				'image_id'     => $image_id,
				'size'         => $this->sanitize_css_size($this->text($image, 'size', ''), ''),
				'size_tablet'  => $this->sanitize_css_size($this->text($image, 'size_tablet', ''), ''),
				'size_mobile'  => $this->sanitize_css_size($this->text($image, 'size_mobile', ''), ''),
				'margin'       => $this->sanitize_spacing_value($this->text($image, 'margin', '')),
				'margin_tablet'=> $this->sanitize_spacing_value($this->text($image, 'margin_tablet', '')),
				'margin_mobile'=> $this->sanitize_spacing_value($this->text($image, 'margin_mobile', '')),
				'animation'    => $this->sanitize_image_animation($this->text($image, 'animation', 'none')),
				'delay'        => $this->sanitize_animation_delay($this->text($image, 'delay', '')),
				'custom_class' => $this->sanitize_class_list($this->text($image, 'custom_class', '')),
				'position'     => $this->sanitize_position($this->text($image, 'position', 'left')),
			);
		}

		return $sanitized_images;
	}

	private function sanitize_position(string $value): string {
		$value = sanitize_key($value);

		return in_array($value, array('left', 'right', 'center-top', 'center-bottom'), true) ? $value : 'left';
	}

	/**
	 * A submitted value as a string: '' for anything that is not a scalar
	 * (a field sent as an array must not break the save).
	 *
	 * @param array  $values  Submitted values.
	 * @param string $key     Field name.
	 * @param string $default Value when the field is missing.
	 */
	private function text(array $values, string $key, string $default = ''): string {
		if (! array_key_exists($key, $values)) {
			return $default;
		}

		return is_scalar($values[$key]) ? (string) $values[$key] : '';
	}
}

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

		$out = array(
			'enabled'          => empty($input['enabled']) ? 0 : 1,
			'message'          => $this->sanitize_message($this->text($input, 'message')),
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
		return $this->apply_spacing_sides($input, $out, array('padding', 'margin'));
	}

	private function apply_spacing_sides(array $input, array $out, array $keys): array {
		foreach ($keys as $key) {
			if (! isset($input[$key . '_sides']) || ! is_array($input[$key . '_sides'])) {
				continue;
			}
			$inherited = array_fill_keys(array('top', 'right', 'bottom', 'left'), '0');
			foreach (array('desktop' => '', 'tablet' => '_tablet', 'mobile' => '_mobile') as $device => $suffix) {
				$raw = $input[$key . '_sides'][$device] ?? array();
				$raw = is_array($raw) ? $raw : array();
				$own = false;
				foreach ($inherited as $side => $value) {
					$value = trim($this->sanitize_spacing_value($this->text($raw, $side)));
					if (count(self::spacing_tokens($value)) > 1) { $value = ''; }
					$css_value = $value;
					if (preg_match('/^(-?(?:\d+(?:\.\d+)?|\.\d+))(?:px)?$/i', $value, $match)) {
						$number = (float) $match[1];
						$value = (string) ('padding' === $key ? max(0, $number) : $number);
						$css_value = $value . 'px';
					}
					$out[$key . '_sides'][$device][$side] = $value;
					if ('' !== $value) { $inherited[$side] = $css_value; $own = true; }
				}
				$out[$key . $suffix] = 'desktop' === $device || $own ? implode(' ', $inherited) : '';
			}
		}
		return $out;
	}

	/** Expand legacy CSS shorthand without splitting spaces inside calc()/clamp(). */
	public static function spacing_sides(string $value): array {
		$tokens = self::spacing_tokens($value);
		$count = count($tokens);
		if (! $count || $count > 4) { return array_fill_keys(array('top', 'right', 'bottom', 'left'), ''); }
		return array('top' => $tokens[0], 'right' => $tokens[1] ?? $tokens[0], 'bottom' => $tokens[2] ?? $tokens[0], 'left' => $tokens[3] ?? ($tokens[1] ?? $tokens[0]));
	}

	private static function spacing_tokens(string $value): array {
		$tokens = array(); $token = ''; $depth = 0;
		foreach (str_split(trim($value)) as $char) {
			if ('(' === $char) { ++$depth; }
			if (')' === $char) { --$depth; }
			if (preg_match('/\s/', $char) && 0 === $depth) {
				if ('' !== $token) { $tokens[] = $token; $token = ''; }
			} else { $token .= $char; }
		}
		if ('' !== $token) { $tokens[] = $token; }
		return $tokens;
	}

	public function sanitize_hex_color(string $color, string $fallback = '#1f2937'): string {
		if (preg_match('/^rgba\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d*\.?\d+)\s*\)$/i', trim($color), $match)) {
			return 'rgba(' . min(255, (int) $match[1]) . ',' . min(255, (int) $match[2]) . ',' . min(255, (int) $match[3]) . ',' . max(0, min(1, (float) $match[4])) . ')';
		}
		$sanitized = sanitize_hex_color($color);

		return $sanitized ? $sanitized : $fallback;
	}

	/** The formatting offered by the WordPress text editor, without embeds or scripts. */
	public function sanitize_message(string $html): string {
		$block = array('style' => true);
		$tags = array(
			'p' => $block, 'h1' => $block, 'h2' => $block, 'h3' => $block,
			'h4' => $block, 'h5' => $block, 'h6' => $block, 'li' => $block,
			'br' => array(), 'strong' => array(), 'b' => array(), 'em' => array(),
			'i' => array(), 'u' => array(), 'span' => $block,
			'ul' => array(), 'ol' => array('start' => true),
			'a' => array('href' => true, 'title' => true, 'target' => true, 'rel' => true),
		);
		$html = preg_replace('#<(script|style)\b[^>]*>.*?</\1\s*>#is', '', $html);
		$html = wp_kses($html, $tags);
		$html = preg_replace_callback("/\\sstyle=([\"'])(.*?)\\1/is", static function ($match) {
			$styles = array();
			foreach (explode(';', html_entity_decode($match[2], ENT_QUOTES, 'UTF-8')) as $declaration) {
				$parts = explode(':', $declaration, 2);
				if (count($parts) === 2 && in_array(strtolower(trim($parts[0])), array('color', 'font-weight', 'font-style', 'text-decoration', 'text-align'), true)) {
					$styles[] = $declaration;
				}
			}
			$style = safecss_filter_attr(implode(';', $styles));
			return '' !== $style ? ' style="' . esc_attr($style) . '"' : '';
		}, $html);
		$html = preg_replace_callback('#<a\s[^>]*\btarget="[^"]*"[^>]*>#i', static function ($match) {
			$tag = $match[0];
			if (preg_match('/\brel="([^"]*)"/i', $tag, $rel)) {
				return preg_match('/(^|\s)noopener(\s|$)/i', $rel[1]) ? $tag : str_replace($rel[0], 'rel="' . trim($rel[1] . ' noopener') . '"', $tag);
			}
			return substr($tag, 0, -1) . ' rel="noopener">';
		}, $html);
		return '' === trim(html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES, 'UTF-8'), " \t\n\r\0\x0B\xc2\xa0") ? '' : $html;
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

			$clean_image = array(
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
			foreach (array('size', 'size_tablet', 'size_mobile') as $key) {
				if (preg_match('/^(-?(?:\d+(?:\.\d+)?|\.\d+))(?:px)?$/i', trim($clean_image[$key]), $match)) {
					$clean_image[$key] = max(0, (float) $match[1]) . 'px';
				}
			}
			$sanitized_images[] = $this->apply_spacing_sides($image, $clean_image, array('margin'));
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

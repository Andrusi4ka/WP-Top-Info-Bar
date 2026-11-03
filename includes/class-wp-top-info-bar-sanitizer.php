<?php

if (! defined('ABSPATH')) {
	exit;
}

final class WP_Top_Info_Bar_Sanitizer {
	public const IMAGE_OVERRIDE_GROUPS = array(
		'size' => array('size', 'size_tablet', 'size_mobile'),
		'margin' => array('margin', 'margin_tablet', 'margin_mobile', 'margin_sides', 'margin_enabled'),
		'animation' => array('animation', 'animation_enabled'),
		'delay' => array('delay'),
		'custom_class' => array('custom_class', 'custom_class_enabled'),
	);
	/**
	 * @param mixed $input Submitted settings (anything: options.php passes on what it got).
	 */
	public function sanitize_settings($input): array {
		$input = is_array($input) ? $input : array();

		$out = array(
			'enabled'          => empty($input['enabled']) ? 0 : 1,
			'zone_settings' => $this->sanitize_zone_settings($input['zone_settings'] ?? array()),
			'texts' => $this->sanitize_text_elements($input),
			'buttons' => $this->sanitize_button_elements($input),
			'message'          => $this->sanitize_message($this->text($input, 'message')),
			'background_color' => $this->sanitize_hex_color($this->text($input, 'background_color', '#1f2937')),
			'text_color'       => $this->sanitize_hex_color($this->text($input, 'text_color', '#ffffff'), '#ffffff'),
			'animation'        => $this->sanitize_animation($this->text($input, 'animation', 'none')),
			'animation_enabled' => array_key_exists('animation_enabled', $input) ? (empty($input['animation_enabled']) ? 0 : 1) : 1,
			'marquee_speed'    => max(1, min(1000, (float) $this->text($input, 'marquee_speed', '60'))),
			'text_delay'       => $this->sanitize_animation_delay($this->text($input, 'text_delay', '')),
			'font_size'        => $this->sanitize_css_size($this->text($input, 'font_size', '14px'), '14px'),
			'font_size_tablet' => $this->sanitize_css_size($this->text($input, 'font_size_tablet', ''), ''),
			'font_size_mobile' => $this->sanitize_css_size($this->text($input, 'font_size_mobile', ''), ''),
			'padding_enabled' => array_key_exists('padding_enabled', $input) ? (empty($input['padding_enabled']) ? 0 : 1) : 1,
			'margin_enabled' => array_key_exists('margin_enabled', $input) ? (empty($input['margin_enabled']) ? 0 : 1) : 1,
			'padding'          => $this->sanitize_spacing_value($this->text($input, 'padding', '12px 16px')),
			'padding_tablet'   => $this->sanitize_spacing_value($this->text($input, 'padding_tablet', '')),
			'padding_mobile'   => $this->sanitize_spacing_value($this->text($input, 'padding_mobile', '')),
			'margin'           => $this->sanitize_spacing_value($this->text($input, 'margin', '')),
			'margin_tablet'    => $this->sanitize_spacing_value($this->text($input, 'margin_tablet', '')),
			'margin_mobile'    => $this->sanitize_spacing_value($this->text($input, 'margin_mobile', '')),
			'custom_class'     => $this->sanitize_class_list($this->text($input, 'custom_class', '')),
			'custom_id'        => $this->sanitize_html_identifier($this->text($input, 'custom_id', '')),
			'images'           => $this->independent_images($input),
			'images_independent' => 1,
			'image_defaults'   => $this->sanitize_image_defaults($input['image_defaults'] ?? array()),
			'image_position_independent' => 1,
		);
		$out['border_enabled'] = ! empty($input['border_enabled']) && is_scalar($input['border_enabled']) ? 1 : 0;
		$out['shadow_enabled'] = ! empty($input['shadow_enabled']) && is_scalar($input['shadow_enabled']) ? 1 : 0;
		$out['border_color'] = $this->sanitize_hex_color($this->text($input, 'border_color', '#ffffff'), '#ffffff');
		$out['shadow_color'] = $this->sanitize_hex_color($this->text($input, 'shadow_color', 'rgba(0,0,0,0.3)'), 'rgba(0,0,0,0.3)');
		foreach (array('', '_tablet', '_mobile') as $suffix) {
			$value = $this->text($input, 'border_width' . $suffix, '' === $suffix ? '2' : '');
			$out['border_width' . $suffix] = '' !== $suffix && '' === $value ? '' : max(0, min(50, (float) $value));
		}
		foreach (array('shadow_x' => array(0, -100, 100), 'shadow_y' => array(8, -100, 100), 'shadow_blur' => array(20, 0, 100), 'shadow_spread' => array(0, -50, 50)) as $key => $limits) {
			$out[$key] = max($limits[1], min($limits[2], (float) $this->text($input, $key, (string) $limits[0])));
		}
		// Image rows can have sparse form indexes; saving compacts them.
		$layout = $input['layout_order'] ?? array();
		if (is_string($layout)) { $layout = json_decode($layout, true); }
		$keys = array();
		foreach ($out['buttons'] as $id => $element) { $keys['button:' . $id] = 'button:' . $id; }
		if (isset($out['buttons']['legacy'])) { $keys['button'] = 'button:legacy'; }
		foreach ($out['texts'] as $id => $element) { $keys['text:' . $id] = 'text:' . $id; }
		if (isset($out['texts']['legacy'])) { $keys['text'] = 'text:legacy'; }
		$index = 0;
		foreach (is_array($input['images'] ?? null) ? $input['images'] : array() as $old => $image) {
			if (is_array($image) && absint($this->text($image, 'image_id')) > 0) { $keys['image:' . $old] = 'image:' . $index++; }
		}
		$out['layout_order'] = array();
		foreach (is_array($layout) ? $layout : array() as $key) {
			if (is_string($key) && isset($keys[$key]) && ! in_array($keys[$key], $out['layout_order'], true)) { $out['layout_order'][] = $keys[$key]; }
		}
		$zones = $input['layout_zones'] ?? array();
		if (is_string($zones)) { $zones = json_decode($zones, true); }
		$out['layout_zones'] = array();
		foreach (is_array($zones) ? $zones : array() as $key => $zone) {
			if (isset($keys[$key]) && in_array($zone, array('left', 'center', 'right'), true)) { $out['layout_zones'][$keys[$key]] = $zone; }
		}
		$out = array_merge($out, $this->sanitize_button_element($input));
		foreach (array('_tablet', '_mobile') as $suffix) {
			$value = $this->text($input, 'animation' . $suffix, '');
			$out['animation' . $suffix] = '' === $value ? '' : $this->sanitize_animation($value);
			$out['text_delay' . $suffix] = $this->sanitize_animation_delay($this->text($input, 'text_delay' . $suffix, ''));
			$value = $this->text($input, 'marquee_speed' . $suffix, '');
			$out['marquee_speed' . $suffix] = '' === $value ? '' : max(1, min(1000, (float) $value));
		}
		if (empty($input['images_independent']) && empty($input['image_position_independent'])) {
			foreach ($out['images'] as &$image) {
				if (empty($image['override_enabled'])) {
					foreach (array('position', 'position_tablet', 'position_mobile') as $key) { $image[$key] = $out['image_defaults'][$key]; }
				}
			}
			unset($image);
		}
		foreach ($out['images'] as &$image) {
			$image['override_fields'] = array_fill_keys(array_keys(self::IMAGE_OVERRIDE_GROUPS), 1);
			$image['override_enabled'] = in_array(1, $image['override_fields'], true) ? 1 : 0;
		}
		unset($image);
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
	/** Materialize legacy shared settings once; every image then owns its values. */
	public function independent_images(array $settings): array {
		$defaults = $this->sanitize_image_defaults($settings['image_defaults'] ?? array());
		$images = array();
		foreach (is_array($settings['images'] ?? null) ? $settings['images'] : array() as $image) {
			if (! is_array($image)) { continue; }
			if (empty($settings['images_independent'])) {
				$mask = $this->image_overrides($image, $defaults);
				$resolved = $defaults;
				foreach (self::IMAGE_OVERRIDE_GROUPS as $group => $fields) {
					if (! empty($mask[$group])) { foreach ($fields as $key) { if (array_key_exists($key, $image)) { $resolved[$key] = $image[$key]; } } }
				}
				foreach (array('image_id', 'position', 'position_tablet', 'position_mobile') as $key) { $resolved[$key] = $image[$key] ?? ('position' === $key ? 'left' : ''); }
				$image = $resolved;
			}
			if (empty($settings['images_independent'])) {
			if (! self::image_option_enabled($image, 'margin')) { $image['margin'] = '0'; $image['margin_tablet'] = ''; $image['margin_mobile'] = ''; unset($image['margin_sides']); }
			if (! self::image_option_enabled($image, 'animation')) { $image['animation'] = 'none'; }
			if (! self::image_option_enabled($image, 'custom_class')) { $image['custom_class'] = ''; }
			foreach (array('margin', 'animation', 'custom_class') as $key) { $image[$key . '_enabled'] = 1; }
			}
			$image['override_enabled'] = 1; $image['override_initialized'] = 1;
			$image['override_fields'] = array_fill_keys(array_keys(self::IMAGE_OVERRIDE_GROUPS), 1);
			$images[] = $image;
		}
		return $this->sanitize_images($images);
	}

	public function sanitize_zone_settings($input): array {
		$out = array();
		foreach (array('left', 'center', 'right') as $zone) {
			if (! is_array($input) || ! isset($input[$zone]) || ! is_array($input[$zone])) { continue; }
			$values = $input[$zone];
			$clean = array('background_color' => $this->sanitize_hex_color($this->text($values, 'background_color', 'rgba(0,0,0,0)'), 'rgba(0,0,0,0)'));
			foreach (array('', '_tablet', '_mobile') as $suffix) {
				$value = strtolower(trim($this->text($values, 'width' . $suffix)));
				if ('' === $suffix && '' === $value && 'center' !== $zone) { $value = 'auto'; }
				if (preg_match('/^(?:[0-9]+(?:\.[0-9]+)?|\.[0-9]+)(?:px|%)?$/', $value)) {
					$number = (float) $value;
					$value = (string) (false !== strpos($value, '%') ? min(100, $number) : $number) . (false !== strpos($value, '%') ? '%' : 'px');
				} elseif ('auto' !== $value) { $value = ''; }
				$clean['width' . $suffix] = $value;
			}
			foreach (array('padding', 'margin') as $key) {
				$clean[$key . '_enabled'] = array_key_exists($key . '_enabled', $values) ? (empty($values[$key . '_enabled']) ? 0 : 1) : 1;
				foreach (array('', '_tablet', '_mobile') as $suffix) { $clean[$key . $suffix] = $this->sanitize_spacing_value($this->text($values, $key . $suffix)); }
			}
			foreach (array('', '_tablet', '_mobile') as $suffix) {
				$mode = $this->text($values, 'animation_mode' . $suffix, '' === $suffix ? 'inherit' : '');
				$clean['animation_mode' . $suffix] = in_array($mode, array('inherit', 'none', 'custom'), true) ? $mode : ('' === $suffix ? 'inherit' : '');
				$value = $this->text($values, 'animation' . $suffix, '' === $suffix ? 'fade-in' : '');
				$clean['animation' . $suffix] = '' !== $suffix && '' === $value ? '' : $this->sanitize_animation($value);
				$clean['animation_delay' . $suffix] = $this->sanitize_animation_delay($this->text($values, 'animation_delay' . $suffix));
				$value = $this->text($values, 'animation_speed' . $suffix, '' === $suffix ? '60' : '');
				$clean['animation_speed' . $suffix] = '' === $value ? '' : max(1, min(1000, (float) $value));
			}
			$out[$zone] = $this->apply_spacing_sides($values, $clean, array('padding', 'margin'));
		}
		return $out;
	}

	public function sanitize_button_elements(array $input): array {
		$source = $input['buttons'] ?? null;
		if (! is_array($source)) {
			$source = ! empty($input['buttons_present']) || '' === trim($this->text($input, 'button_text')) ? array() : array('legacy' => $input);
		}
		$out = array();
		foreach ($source as $id => $element) {
			if (is_array($element) && preg_match('/^[a-zA-Z0-9_-]{1,64}$/', (string) $id)) { $out[$id] = $this->sanitize_button_element($element); }
		}
		return $out;
	}

	private function sanitize_button_element(array $input): array {
		$out = array();
		$out['button_text'] = sanitize_text_field($this->text($input, 'button_text'));
		$out['button_url'] = esc_url_raw($this->text($input, 'button_url'), array('http', 'https', 'mailto', 'tel'));
		$out['button_new_tab'] = ! empty($input['button_new_tab']) && is_scalar($input['button_new_tab']) ? 1 : 0;
		$out['button_color'] = $this->sanitize_hex_color($this->text($input, 'button_color', '#2271b1'), '#2271b1');
		$out['button_text_color'] = $this->sanitize_hex_color($this->text($input, 'button_text_color', '#ffffff'), '#ffffff');
		foreach (array(
			'button_size' => array('default', 'extra-small', 'small', 'large', 'extra-large'),
			'button_shape' => array('square', 'rounded', 'round'),
			'button_style' => array('filled', 'bordered', 'underline'),
			'button_position' => array('text-right', 'screen-left', 'text-left', 'screen-right', 'center-top', 'center-bottom'),
		) as $key => $allowed) {
			$value = $this->text($input, $key, $allowed[0]);
			$out[$key] = in_array($value, $allowed, true) ? $value : $allowed[0];
		}
		foreach (array('_tablet', '_mobile') as $suffix) {
			$value = $this->text($input, 'button_size' . $suffix);
			$out['button_size' . $suffix] = in_array($value, array('extra-small', 'small', 'default', 'large', 'extra-large'), true) ? $value : '';
		}
		return $out;
	}

	/** Stable element IDs keep layout order intact when another text is removed. */
	public function sanitize_text_elements(array $input): array {
		$source = $input['texts'] ?? null;
		if (! is_array($source)) {
			$source = ! empty($input['texts_present']) || '' === trim($this->text($input, 'message')) ? array() : array('legacy' => $input);
		}
		$out = array();
		foreach ($source as $id => $element) {
			if (! is_array($element) || ! preg_match('/^[a-zA-Z0-9_-]{1,64}$/', (string) $id)) { continue; }
			$out[$id] = array(
				'message' => $this->sanitize_message($this->text($element, 'message')),
				'font_size' => $this->sanitize_css_size($this->text($element, 'font_size', '14px'), '14px'),
				'font_size_tablet' => $this->sanitize_css_size($this->text($element, 'font_size_tablet'), ''),
				'font_size_mobile' => $this->sanitize_css_size($this->text($element, 'font_size_mobile'), ''),
				'text_color' => $this->sanitize_hex_color($this->text($element, 'text_color', '#ffffff'), '#ffffff'),
			);
		}
		return $out;
	}

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
		return self::css_value($value);
	}

	public function sanitize_css_size(string $value, string $fallback): string {
		$sanitized = self::css_value($value);

		return '' !== trim($sanitized) ? $sanitized : $fallback;
	}

	/**
	 * A CSS length such as 12px, 1rem or calc(1px + 2vw). Values go into inline
	 * style attributes, so a comment or an unclosed bracket (which would swallow
	 * the declarations after it) makes the whole value empty.
	 */
	private static function css_value(string $value): string {
		$value = preg_replace('/[^0-9a-zA-Z.%\-+*\s(),\/]/', '', sanitize_text_field($value)) ?? '';
		if (false !== strpos($value, '/*') || false !== strpos($value, '*/')) {
			return '';
		}
		$depth = 0;
		foreach (str_split($value) as $char) {
			if ('(' === $char) { ++$depth; }
			if (')' === $char && --$depth < 0) { return ''; }
		}

		return 0 === $depth ? $value : '';
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
		$allowed = array('none', 'fade-in', 'slide-up', 'from-left', 'from-right', 'page-left', 'page-right', 'blink', 'scale-pulse', 'marquee');
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
				'override_enabled' => array_key_exists('override_enabled', $image) ? (is_scalar($image['override_enabled']) && ! empty($image['override_enabled']) ? 1 : 0) : 1,
				'override_initialized' => array_key_exists('override_initialized', $image) ? (is_scalar($image['override_initialized']) && ! empty($image['override_initialized']) ? 1 : 0) : 1,
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
				'position_tablet' => $this->sanitize_position($this->text($image, 'position_tablet'), true),
				'position_mobile' => $this->sanitize_position($this->text($image, 'position_mobile'), true),
			);
			foreach (array('margin', 'animation', 'custom_class') as $key) {
				$clean_image[$key . '_enabled'] = self::image_option_enabled($image, $key) ? 1 : 0;
			}
			foreach (array('size', 'size_tablet', 'size_mobile') as $key) {
				if (preg_match('/^(-?(?:\d+(?:\.\d+)?|\.\d+))(?:px)?$/i', trim($clean_image[$key]), $match)) {
					$clean_image[$key] = max(0, (float) $match[1]) . 'px';
				}
			}
			$clean_image = $this->apply_spacing_sides($image, $clean_image, array('margin'));
			if (isset($image['override_fields']) && is_array($image['override_fields'])) {
				foreach (self::IMAGE_OVERRIDE_GROUPS as $key => $fields) {
					$value = $image['override_fields'][$key] ?? 0;
					$clean_image['override_fields'][$key] = is_scalar($value) && ! empty($value) ? 1 : 0;
				}
			}
			$sanitized_images[] = $clean_image;
		}

		return $sanitized_images;
	}

	public function sanitize_image_defaults($input): array {
		$input = is_array($input) ? $input : array();
		$clean = $this->sanitize_images(array(array_merge($input, array('image_id' => 1))))[0];
		unset($clean['image_id'], $clean['override_enabled'], $clean['override_initialized'], $clean['override_fields']);
		return $clean;
	}

	/** Each style group inherits independently; older image snapshots are narrowed to real overrides. */
	public function image_overrides(array $image, array $defaults): array {
		$mask = array_fill_keys(array_keys(self::IMAGE_OVERRIDE_GROUPS), 0);
		if (isset($image['override_fields']) && is_array($image['override_fields'])) {
			foreach ($mask as $key => $value) {
				$raw = $image['override_fields'][$key] ?? 0;
				$mask[$key] = is_scalar($raw) && ! empty($raw) ? 1 : 0;
			}
			return $mask;
		}
		if (isset($image['override_enabled']) && empty($image['override_enabled'])) { return $mask; }
		$own = self::image_style_groups($image);
		$common = self::image_style_groups($defaults);
		foreach ($mask as $key => $value) {
			$active = 'size' === $key || ('delay' === $key ? 'none' !== $own['animation'] : self::image_option_enabled($image, $key));
			$mask[$key] = $active && $own[$key] !== $common[$key] ? 1 : 0;
		}
		return $mask;
	}

	private static function image_style_groups(array $image): array {
		$px = static function ($value) {
			$value = is_scalar($value) ? trim((string) $value) : '';
			return preg_match('/^-?(?:\d+(?:\.\d+)?|\.\d+)(?:px)?$/i', $value) ? (float) $value . 'px' : $value;
		};
		$sizes = array(); $margins = array(); $size = '40px';
		$margin = array_fill_keys(array('top', 'right', 'bottom', 'left'), '0px');
		foreach (array('', '_tablet', '_mobile') as $suffix) {
			if (! empty($image['size' . $suffix])) { $size = $px($image['size' . $suffix]); }
			$sizes[] = $size;
			$raw = $image['margin' . $suffix] ?? '';
			if (is_string($raw) && '' !== $raw) {
				foreach (self::spacing_sides($raw) as $side => $value) { if ('' !== $value) $margin[$side] = $px($value); }
			}
			$margins[] = self::image_option_enabled($image, 'margin') ? array_values($margin) : array('0px', '0px', '0px', '0px');
		}
		$animation = self::image_option_enabled($image, 'animation') ? ($image['animation'] ?? 'none') : 'none';
		$delay = is_scalar($image['delay'] ?? '') ? trim((string) ($image['delay'] ?? '')) : '';
		$classes = self::image_option_enabled($image, 'custom_class') && is_string($image['custom_class'] ?? '') ? preg_split('/\s+/', trim($image['custom_class'])) : array();
		sort($classes);
		return array('size' => $sizes, 'margin' => $margins, 'animation' => $animation, 'delay' => 'none' !== $animation ? (float) $delay * (preg_match('/(?<!m)s$/', $delay) ? 1000 : 1) : 0, 'custom_class' => implode(' ', $classes));
	}

	/** Keep legacy image settings active until their new switch is explicitly changed. */
	public static function image_option_enabled(array $image, string $key): bool {
		if (array_key_exists($key . '_enabled', $image)) {
			return is_scalar($image[$key . '_enabled']) && ! empty($image[$key . '_enabled']);
		}
		foreach ('margin' === $key ? array('margin', 'margin_tablet', 'margin_mobile') : array($key) as $field) {
			$value = isset($image[$field]) && is_scalar($image[$field]) ? trim((string) $image[$field]) : '';
			if ('' !== $value && ('animation' !== $key || 'none' !== $value)) { return true; }
		}
		if ('margin' === $key && isset($image['margin_sides']) && is_array($image['margin_sides'])) {
			foreach ($image['margin_sides'] as $values) {
				foreach (is_array($values) ? $values : array() as $value) {
					if (is_scalar($value) && '' !== trim((string) $value)) { return true; }
				}
			}
		}
		return false;
	}

	private function sanitize_position(string $value, bool $inherit = false): string {
		$value = sanitize_key($value);

		return in_array($value, array('left', 'right', 'center-top', 'center-bottom'), true) ? $value : ($inherit ? '' : 'left');
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

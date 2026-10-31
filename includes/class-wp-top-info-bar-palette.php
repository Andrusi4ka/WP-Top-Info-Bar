<?php
/**
 * The colors the slide text editors offer (the "Text color" button of the
 * Caption and Description editors, admin.js): the theme's colors, then
 * black, white and greys. Any other color can still be picked there
 * ("Custom..."). Until 2.9 a WP Swiper > Settings page added the user's own;
 * it is gone (uninstall.php still removes what it saved).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Top_Info_Bar_Palette {

	/**
	 * A CSS color as "#rrggbb" (lowercase): from "#rgb", "#rrggbb" or
	 * "rgb()"/"rgba()" (its alpha dropped); '' for anything else.
	 *
	 * @param string $value Color.
	 * @return string
	 */
	public static function hex( $value ) {
		$value = strtolower( trim( $value ) );
		if ( preg_match( '/^#([0-9a-f]{3})$/', $value, $match ) ) {
			$value = '#' . $match[1][0] . $match[1][0] . $match[1][1] . $match[1][1] . $match[1][2] . $match[1][2];
		}
		if ( preg_match( '/^#[0-9a-f]{6}$/', $value ) ) {
			return $value;
		}
		if ( preg_match( '/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*[\d.]+\s*)?\)$/', $value, $match ) ) {
			return sprintf( '#%02x%02x%02x', min( 255, (int) $match[1] ), min( 255, (int) $match[2] ), min( 255, (int) $match[3] ) );
		}
		return '';
	}

	/**
	 * The theme's colors: Woodmart's own (Theme Settings), and any theme's
	 * palette (theme.json or its editor color palette).
	 *
	 * @return array [ [ 'color' => '#rrggbb', 'name' => string ], ... ]
	 */
	public static function theme_colors() {
		$colors = array();

		if ( 'woodmart' === get_template() ) {
			$options = get_option( 'xts-woodmart-options' );
			$names   = array(
				'primary-color'   => __( 'Primary color', 'wp-top-info-bar' ),
				'secondary-color' => __( 'Secondary color', 'wp-top-info-bar' ),
				'link-color'      => __( 'Link color', 'wp-top-info-bar' ),
			);
			foreach ( $names as $key => $name ) {
				$value = is_array( $options ) && isset( $options[ $key ] ) ? $options[ $key ] : '';
				if ( is_array( $value ) ) {
					$value = isset( $value['idle'] ) ? $value['idle'] : '';
				}
				$colors[] = array(
					'color' => self::hex( (string) $value ),
					'name'  => $name,
				);
			}
		}

		if ( function_exists( 'wp_get_global_settings' ) ) {
			$palette = wp_get_global_settings( array( 'color', 'palette', 'theme' ) );
			foreach ( is_array( $palette ) ? $palette : array() as $entry ) {
				if ( is_array( $entry ) && isset( $entry['color'] ) ) {
					$colors[] = array(
						'color' => self::hex( (string) $entry['color'] ),
						'name'  => isset( $entry['name'] ) ? sanitize_text_field( (string) $entry['name'] ) : '',
					);
				}
			}
		}

		return array_values(
			array_filter(
				$colors,
				function ( $entry ) {
					return '' !== $entry['color'];
				}
			)
		);
	}

	/**
	 * Black, white and greys, always there.
	 *
	 * @return array
	 */
	public static function basic_colors() {
		return array(
			array( 'color' => '#000000', 'name' => __( 'Black', 'wp-top-info-bar' ) ),
			array( 'color' => '#444444', 'name' => __( 'Dark gray', 'wp-top-info-bar' ) ),
			array( 'color' => '#888888', 'name' => __( 'Gray', 'wp-top-info-bar' ) ),
			array( 'color' => '#cccccc', 'name' => __( 'Light gray', 'wp-top-info-bar' ) ),
			array( 'color' => '#ffffff', 'name' => __( 'White', 'wp-top-info-bar' ) ),
		);
	}

	/**
	 * The editors' palette: the theme's colors, then the basic ones; each
	 * color once (its first name), a nameless one named by itself.
	 *
	 * @return array [ [ 'color' => '#rrggbb', 'name' => string ], ... ]
	 */
	public static function colors() {
		$all  = array();
		$seen = array();
		foreach ( array_merge( self::theme_colors(), self::basic_colors() ) as $entry ) {
			if ( isset( $seen[ $entry['color'] ] ) ) {
				continue;
			}
			$seen[ $entry['color'] ] = true;
			$all[]                   = array(
				'color' => $entry['color'],
				'name'  => '' !== $entry['name'] ? $entry['name'] : $entry['color'],
			);
		}
		return $all;
	}

	/**
	 * The palette as TinyMCE's textcolor takes it (forecolor_map): the color
	 * without "#", then its name, one after the other.
	 *
	 * @return string[]
	 */
	public static function tinymce_map() {
		$map = array();
		foreach ( self::colors() as $entry ) {
			$map[] = strtoupper( substr( $entry['color'], 1 ) );
			$map[] = $entry['name'];
		}
		return $map;
	}
}

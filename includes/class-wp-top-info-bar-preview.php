<?php
/**
 * Isolated, unsaved bar preview. Device artwork and screen geometry match
 * WP Maps Embed; the iframe uses this plugin's frontend renderer and CSS.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Top_Info_Bar_Preview {

	const ACTION = 'wp_top_info_bar_frame';

	/**
	 * Frame sizes (px) per device, inside the plugin's breakpoints; the frame
	 * is drawn this size and scaled down to fit (admin.js). As WP Swiper's:
	 * each one the proportions of its drawing's screen (DEVICES).
	 */
	const SIZES = array(
		'desktop' => array( 1280, 721 ),
		'tablet'  => array( 820, 1101 ),
		'mobile'  => array( 390, 844 ),
	);

	/**
	 * The devices drawn round the frame (assets/images/preview/, WP Swiper's
	 * drawings): each one's size and its screen's box and corner radius, in
	 * the drawing's units (its viewBox).
	 */
	const DEVICES = array(
		'desktop' => array(
			'src'    => 'assets/images/preview/desktop.svg',
			'width'  => 1000.2,
			'height' => 793.1,
			'screen' => array( 22.89, 23.39, 954.91, 537.91 ),
			'radius' => 0,
		),
		'tablet'  => array(
			'src'    => 'assets/images/preview/tablet.svg',
			'width'  => 1379.63,
			'height' => 1789.88,
			'screen' => array( 78.02, 84.23, 1214.8, 1631.04 ),
			'radius' => 17.15,
		),
		'mobile'  => array(
			'src'    => 'assets/images/preview/phone.svg',
			'width'  => 213.04,
			'height' => 429.61,
			'screen' => array( 12.01, 10.13, 188.99, 409.35 ),
			'radius' => 24.05,
		),
	);

	public static function init() {
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'render_frame' ) );
	}

	/**
	 * The device whose settings a screen this wide takes, as frontend.css's
	 * breakpoints: below 768px Mobile, up to 1024px Tablet, wider Desktop.
	 *
	 * @param int $width Screen width (px).
	 * @return string desktop | tablet | mobile
	 */
	public static function device_at( $width ) {
		if ( $width <= 767 ) {
			return 'mobile';
		}
		return $width <= 1024 ? 'tablet' : 'desktop';
	}

	/**
	 * Tablet and Mobile turned sideways (the turn button): the note saying the
	 * screen then takes another device's settings (a phone 844px wide is a tablet).
	 *
	 * @return array device => note, for the devices that change.
	 */
	public static function turned_notes() {
		$names = self::device_names();
		$notes = array();
		foreach ( array( 'tablet', 'mobile' ) as $device ) {
			$width = self::SIZES[ $device ][1];
			$takes = self::device_at( $width );
			if ( $takes !== $device ) {
				$notes[ $device ] = sprintf(
					/* translators: 1: screen width in px, 2: a device's name (Tablet, Desktop). */
					__( 'Turned sideways the screen is %1$dpx wide: the bar takes the %2$s settings, as it does on the site.', 'wp-top-info-bar' ),
					$width,
					$names[ $takes ]
				);
			}
		}
		return $notes;
	}

	/**
	 * @return array device => short name.
	 */
	public static function device_names() {
		return array(
			'desktop' => __( 'Desktop', 'wp-top-info-bar' ),
			'tablet'  => __( 'Tablet', 'wp-top-info-bar' ),
			'mobile'  => __( 'Mobile', 'wp-top-info-bar' ),
		);
	}

	/**
	 * DEVICES for admin.js: each one's size, screen and radius (no file).
	 *
	 * @return array
	 */
	public static function device_frames() {
		$frames = array();
		foreach ( self::DEVICES as $device => $drawing ) {
			unset( $drawing['src'] );
			$frames[ $device ] = $drawing;
		}
		return $frames;
	}

	/**
	 * The plugin's own SVG icon inline (currentColor), a dashicon without the file.
	 *
	 * @param string $file     File in assets/images/.
	 * @param string $class    Class of the svg.
	 * @param string $fallback Dashicon.
	 * @return string
	 */
	private static function svg_icon( $file, $class, $fallback ) {
		$path = WP_TOP_INFO_BAR_PATH . 'assets/images/' . $file;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
		$svg = is_readable( $path ) ? trim( preg_replace( '/<!--.*?-->/s', '', (string) file_get_contents( $path ) ) ) : '';
		return '' !== $svg
			? preg_replace( '/^<svg\b/', '<svg class="' . esc_attr( $class ) . '" aria-hidden="true" focusable="false"', $svg )
			: '<span class="dashicons ' . esc_attr( $fallback ) . '" aria-hidden="true"></span>';
	}

	/**
	 * The preview column: rotation, device switcher, Update and device frame.
	 */
	public static function render_panel() {
		printf(
			'<div class="wp-top-info-bar-admin__preview wp-top-info-bar-preview" data-url="%1$s" data-action="%2$s" data-post="%3$d" data-nonce="%4$s" data-sizes="%5$s" data-devices="%6$s">',
			esc_url( admin_url( 'admin-ajax.php' ) ),
			esc_attr( self::ACTION ),
			0,
			esc_attr( wp_create_nonce( self::ACTION ) ),
			esc_attr( wp_json_encode( self::SIZES ) ),
			esc_attr( wp_json_encode( self::device_frames() ) )
		);

		echo '<div class="wp-top-info-bar-preview__bar">';
		echo '<span class="wp-top-info-bar-preview__title">' . esc_html__( 'Preview', 'wp-top-info-bar' ) . '</span>';
		// Always here (the bar stays as it is), greyed for Desktop (admin.js).
		printf(
			'<button type="button" class="button wp-top-info-bar-preview__rotate" aria-pressed="false" title="%1$s" aria-label="%1$s" disabled>%2$s</button>',
			esc_attr__( 'Turn the tablet or phone sideways', 'wp-top-info-bar' ),
			self::svg_icon( 'rotate.svg', 'wp-top-info-bar-preview__rotate-icon', 'dashicons-image-rotate' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the plugin's own SVG file.
		);

		// Device controls match the WP Maps Embed preview.
		echo '<button type="button" class="button wp-top-info-bar-preview__dev" aria-pressed="false" disabled><span class="dashicons dashicons-editor-code" aria-hidden="true"></span><span>' . esc_html__('Developer mode', 'wp-top-info-bar') . '</span></button>';
		echo '<div class="wp-top-info-bar-preview__devices" role="group" aria-label="' . esc_attr__( 'Device', 'wp-top-info-bar' ) . '">';
		$names = self::device_names();
		foreach ( array( 'desktop' => array( __( 'Desktop', 'wp-top-info-bar' ), 'dashicons-desktop' ), 'tablet' => array( __( 'Tablet', 'wp-top-info-bar' ), 'dashicons-tablet' ), 'mobile' => array( __( 'Mobile', 'wp-top-info-bar' ), 'dashicons-smartphone' ) ) as $device => $meta ) {
			printf(
				'<button type="button" class="wp-top-info-bar-preview__device-btn" data-device="%1$s" title="%2$s" aria-pressed="%3$s"><span class="dashicons %4$s" aria-hidden="true"></span>%5$s</button>',
				esc_attr( $device ),
				esc_attr( $meta[0] ),
				'desktop' === $device ? 'true' : 'false',
				esc_attr( $meta[1] ),
				esc_html( $names[ $device ] )
			);
		}
		echo '</div>';

		echo '<button type="button" class="button wp-top-info-bar-preview__update"><span class="dashicons dashicons-update" aria-hidden="true"></span>' . esc_html__( 'Update', 'wp-top-info-bar' ) . '</button>';
		echo '</div>';

		// A device turned sideways whose screen then takes another device's settings (admin.js shows it).
		foreach ( self::turned_notes() as $device => $note ) {
			printf(
				'<p class="description wp-top-info-bar-preview__hint wp-top-info-bar-preview__turned" data-device="%1$s" hidden><span class="dashicons dashicons-info-outline" aria-hidden="true"></span>%2$s</p>',
				esc_attr( $device ),
				esc_html( $note )
			);
		}

		echo '<div class="wp-top-info-bar-preview__stage">';
		printf(
			'<iframe class="wp-top-info-bar-preview__frame" name="wp-top-info-bar-preview-%1$d" title="%2$s" src="about:blank"></iframe>',
			0,
			esc_attr__( 'Top info bar preview', 'wp-top-info-bar' )
		);
		// The frame in the device's screen (admin.js fitPreview()); over it, it takes no clicks.
		foreach ( self::DEVICES as $device => $drawing ) {
			printf(
				'<img class="wp-top-info-bar-preview__device" data-device="%1$s" src="%2$s" alt="" hidden>',
				esc_attr( $device ),
				esc_url( WP_TOP_INFO_BAR_URL . $drawing['src'] . '?ver=' . WP_TOP_INFO_BAR_VERSION )
			);
		}
		echo '</div>';

		echo '</div>';
	}

	/** Render the current form without saving any settings. */
	public static function render_frame() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot preview this bar.', 'wp-top-info-bar' ), '', array( 'response' => 403 ) );
		}
		check_ajax_referer( self::ACTION );
		$input = isset( $_POST[ WP_Top_Info_Bar_Settings::OPTION_NAME ] ) ? wp_unslash( $_POST[ WP_Top_Info_Bar_Settings::OPTION_NAME ] ) : array();
		$settings = ( new WP_Top_Info_Bar_Sanitizer() )->sanitize_settings( $input );
		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		header( 'X-Robots-Tag: noindex, nofollow', true );
		?><!doctype html>
<html data-top-info-bar-preview <?php language_attributes(); ?>>
<head><meta charset="<?php echo esc_attr( get_option( 'blog_charset' ) ); ?>"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php esc_html_e( 'Top info bar preview', 'wp-top-info-bar' ); ?></title>
<link rel="stylesheet" href="<?php echo esc_url( WP_TOP_INFO_BAR_URL . 'assets/css/frontend.css?ver=' . filemtime( WP_TOP_INFO_BAR_PATH . 'assets/css/frontend.css' ) ); ?>">
<link rel="stylesheet" href="<?php echo esc_url( WP_TOP_INFO_BAR_URL . 'assets/css/preview-dev.css?ver=' . filemtime( WP_TOP_INFO_BAR_PATH . 'assets/css/preview-dev.css' ) ); ?>">
<style>html,body{margin:0;min-height:100%;background:#fff}body{font-family:Arial,sans-serif}</style></head>
<body><?php
		$frontend = new WP_Top_Info_Bar_Frontend( new WP_Top_Info_Bar_Settings() );
		$frontend->render_settings( $settings );
		if ( empty( $settings['enabled'] ) ) {
			echo '<p style="margin:24px;color:#646970;font-size:14px;text-align:center">' . esc_html__( 'The bar is disabled. Enable it to see the preview.', 'wp-top-info-bar' ) . '</p>';
		} elseif ( '' === trim( $settings['message'] ) && empty( $settings['images'] ) ) {
			echo '<p style="margin:24px;color:#646970;font-size:14px;text-align:center">' . esc_html__( 'Add text or an image to preview the bar.', 'wp-top-info-bar' ) . '</p>';
		}
		?><script src="<?php echo esc_url( WP_TOP_INFO_BAR_URL . 'assets/js/preview-dev.js?ver=' . filemtime( WP_TOP_INFO_BAR_PATH . 'assets/js/preview-dev.js' ) ); ?>"></script></body></html><?php
		exit;
	}
}

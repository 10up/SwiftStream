<?php
namespace TenUp\SwiftStream\v1_1_0\Utils;

/**
 * Set up any required hooks in the namespace
 */
function setup() {
	$n = function( $function ) {
		return __NAMESPACE__ . "\\$function";
	};

	add_action( 'init',                               $n( 'register_scripts' ),           10, 1 );
	add_filter( 'wp_get_attachment_image_attributes', $n( 'get_attachment_placeholder' ), 10, 2 );
}

/**
 * Register our scripts with WordPress
 */
function register_scripts() {
	if ( defined( 'SWIFTSTREAM_PATH' ) ) {
		wp_register_script( 'lazy-loader', SWIFTSTREAM_PATH . 'js/imageLoader.js', array( 'jquery' ), '1.1.0', true );
	}
}

/**
 * Retrieve an image placeholder to represent an attachment.
 *
 * @param int    $attachment_id
 * @param string $size
 * @param bool   $icon
 *
 * @return array|bool
 */
function get_placeholder_image_src( $attachment_id, $size, $icon = false ) {
	if ( false === strpos( $size, '-ph' ) ) {
		$size .= '-ph';
	}

	return wp_get_attachment_image_src( $attachment_id, $size, $icon );
}

/**
 * Get an image reference from photon, down-sampled by a factor of 10.
 *
 * @global array $_wp_additional_image_sizes
 *
 * @param      $attachment_id
 * @param      $size
 * @param bool $icon
 *
 * @return array|bool
 */
function get_photon_placeholder_image_src( $attachment_id, $size, $icon = false ) {
	global $_wp_additional_image_sizes;

	$height = $_wp_additional_image_sizes[ $size ]['height'];
	$width = $_wp_additional_image_sizes[ $size ]['width'];
	$crop = isset( $_wp_additional_image_sizes[ $size ]['crop'] ) ? $_wp_additional_image_sizes[ $size ]['crop'] : false;
	$src = wpcom_vip_get_resized_attachment_url( $attachment_id, $width, $height, $crop );

	// Update some query parameters
	$src = add_query_arg( array(
		'quality' => 100,
		'w'       => $height / 10,
		'h'       => $width / 10
	), $src );

	// Return the image array
	return array(
		$src,
		$height,
		$width
	);
}

/**
 * Filter the array of image attributes to replace the src with its placeholder and relegate the real image to
 * a data attribute for lazy loading.
 *
 * @global array $_wp_additional_image_sizes
 *
 * @param array    $attr
 * @param \WP_Post $attachment
 *
 * @return array
 */
function get_attachment_placeholder( $attr, $attachment ) {
	global $_wp_additional_image_sizes;

	if ( is_admin() ) {
		return $attr;
	}

	// Get the image size
	$class = $attr['class'];
	$size = str_replace( 'attachment-', '', $class );

	// Get the placeholder image
	if ( ! defined( 'WPCOM_IS_VIP_ENV' ) || ! WPCOM_IS_VIP_ENV ) {
		$override = false;
		$placeholder = get_placeholder_image_src( $attachment->ID, $size );
	} else {
		$override = true;

		// If the image size isn't set, abort
		if ( ! isset( $_wp_additional_image_sizes[ $size ] ) ) {
			return $attr;
		}

		// If we're on WordPress.com, get the original image source with a quality parameter ala Photon
		$placeholder = get_photon_placeholder_image_src( $attachment->ID, $size );
	}

	if ( strpos( $placeholder[0], '-ph.' ) !== false || $override ) {
		$attr['data-lazy'] = $attr['src'];
		$attr['src'] = $placeholder[0];

		// If we're swapping images, we need to set up our scripts
		if ( defined( 'SWIFTSTREAM_PATH' ) ) {
			wp_enqueue_script( 'lazy-loader' );
		}
	}

	return $attr;
}
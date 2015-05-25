<?php
namespace TenUp\SwiftStream\v1_1_0\Pixelate;

/**
 * Set up any required hooks in the namespace.
 */
function setup() {
	$n = function( $function ) {
		return __NAMESPACE__ . "\\$function";
	};

	// Only hook in to create intermediate images if we're _not_ on WordPress.com.
	if ( ! defined( 'WPCOM_IS_VIP_ENV' ) || ! WPCOM_IS_VIP_ENV ) {
		add_action( 'after_setup_theme',               $n( 'add_placeholder_sizes' ), 999, 0 );
		add_filter( 'wp_generate_attachment_metadata', $n( 'filter_images' ),         10,  1 );
	}
}

/**
 * Loop through registered image sizes and add placeholders
 *
 * @global array $_wp_additional_image_sizes
 */
function add_placeholder_sizes() {
	global $_wp_additional_image_sizes;

	if( !isset( $_wp_additional_image_sizes ) ){
		$_wp_additional_image_sizes = array();
	}

	$new_sizes = array();

	foreach( get_intermediate_image_sizes() as $name ) {
		if ( isset( $_wp_additional_image_sizes[ $name ]['width'] ) ) {
			$width = absint( $_wp_additional_image_sizes[ $name ]['width'] );
		} else {
			$width = absint( get_option( "{$name}_size_w" ) );
		}

		if ( isset( $_wp_additional_image_sizes[ $name ]['height'] ) ) {
			$height = absint( $_wp_additional_image_sizes[ $name ]['height'] );
		} else {
			$height = absint( get_option( "{$name}_size_h" ) );
		}

		if ( isset( $_wp_additional_image_sizes[ $name ]['crop'] ) ) {
			$crop = $_wp_additional_image_sizes[ $name ]['crop'];
		} else {
			$crop = get_option( "{$name}_crop" );
		}

		$new_sizes[ $name . '-ph' ] = array(
			'width'  => $width,
			'height' => $height,
			'crop'   => $crop,
		);
	}

	$_wp_additional_image_sizes = array_merge( $_wp_additional_image_sizes, $new_sizes );
}

/**
 * Automatically resample and save the downsized versions of each graphic.
 *
 * @param array $meta
 *
 * @return array
 */
function filter_images( $meta ) {
	$n = function( $function ) {
		return __NAMESPACE__ . "\\$function";
	};

	add_filter( 'image_resize_dimensions', $n( 'upscale_dimensions' ), 1, 6 );

	$uploads = wp_upload_dir();

	// Generate a placeholder for the full image
	$file           = basename( $meta[ 'file' ] );
	$file_base_path = trailingslashit( $uploads[ 'basedir' ] ) . str_replace( $file, '', $meta[ 'file' ] );
	$new_parent     = create_placeholder( $file, $file_base_path );

	// Generate placeholders for each image size
	foreach( $meta['sizes'] as $name => $data ) {
		if ( false !== strpos( $name, '-ph' ) ) {
			$new_file = create_placeholder( $data['file'], $file_base_path );
			$meta['sizes'][ $name ]['file'] = $new_file;
		}
	}

	$file_ext = pathinfo( $file, PATHINFO_EXTENSION );
	$mime_type = 'application/octet-stream';

	$mime_types = wp_get_mime_types();
	foreach( $mime_types as $extension => $type ) {
		if ( preg_match( "/{$file_ext}/i", $extension ) ) {
			$mime_type = $type;
			break;
		}
	}

	$meta['sizes']['full-ph'] = array(
		'file'      => $new_parent,
		'width'     => $meta['width'],
		'height'    => $meta['height'],
		'mime-type' => $mime_type,
	);

	remove_filter( 'image_resize_dimensions', $n( 'upscale_dimensions' ), 1 );

	return $meta;
}

/**
 * Create a placeholder image given a regular image.
 *
 * @param string $image_filename
 * @param string $base_file_path
 * @return string
 */
function create_placeholder( $image_filename, $base_file_path ) {
	$image = wp_get_image_editor( trailingslashit( $base_file_path ) . $image_filename );

	if ( is_wp_error( $image ) ) {
		return $image_filename;
	}

	// Get proportions
	$size = $image->get_size();
	$old_width = $size['width'];
	$old_height = $size['height'];
	$width = $old_width / 10;
	$height = $old_height / 10;

	// Placeholder filename
	$new_name = preg_replace( '/(\.gif|\.jpg|\.jpeg|\.png)/', '-ph$1', $image_filename );

	$image->set_quality( 10 );
	$image->resize( $width, $height, false );
	$image->resize( $old_width, $old_height, false );
	$image->save( trailingslashit( $base_file_path ) . $new_name );

	return $new_name;
}

/**
 * Allow WordPress to upscale images.
 *
 * @see https://wordpress.org/support/topic/wp-351-wp-image-editor-scaling-up-images-does-not-work?replies=6
 *
 * @param null $null
 * @param int  $orig_w
 * @param int  $orig_h
 * @param int  $dest_w
 * @param int  $dest_h
 * @param bool $crop
 *
 * @return array|bool
 */
function upscale_dimensions( $null, $orig_w, $orig_h, $dest_w, $dest_h, $crop = false ) {
	if ( $crop ) {
		// crop the largest possible portion of the original image that we can size to $dest_w x $dest_h
		$aspect_ratio = $orig_w / $orig_h;
		$new_w        = min( $dest_w, $orig_w );
		$new_h        = min( $dest_h, $orig_h );

		if ( ! $new_w ) {
			$new_w = intval( $new_h * $aspect_ratio );
		}

		if ( ! $new_h ) {
			$new_h = intval( $new_w / $aspect_ratio );
		}

		$size_ratio = max( $new_w / $orig_w, $new_h / $orig_h );

		$crop_w = round( $new_w / $size_ratio );
		$crop_h = round( $new_h / $size_ratio );

		$s_x = floor( ( $orig_w - $crop_w ) / 2 );
		$s_y = floor( ( $orig_h - $crop_h ) / 2 );
	} else {
		// don't crop, just resize using $dest_w x $dest_h as a maximum bounding box
		$crop_w = $orig_w;
		$crop_h = $orig_h;

		$s_x = 0;
		$s_y = 0;

		/* wp_constrain_dimensions() doesn't consider higher values for $dest :( */
		/* So just use that function only for scaling down ... */
		if ( $orig_w >= $dest_w && $orig_h >= $dest_h ) {
			list( $new_w, $new_h ) = wp_constrain_dimensions( $orig_w, $orig_h, $dest_w, $dest_h );
		} else {
			$ratio = $dest_w / $orig_w;
			$w     = intval( $orig_w * $ratio );
			$h     = intval( $orig_h * $ratio );
			list( $new_w, $new_h ) = array( $w, $h );
		}
	}

	// Now WE need larger images ...
	if ( $new_w == $orig_w && $new_h == $orig_h ) {
		return false;
	}

	// the return array matches the parameters to imagecopyresampled()
	// int dst_x, int dst_y, int src_x, int src_y, int dst_w, int dst_h, int src_w, int src_h
	return array( 0, 0, (int) $s_x, (int) $s_y, (int) $new_w, (int) $new_h, (int) $crop_w, (int) $crop_h );
}
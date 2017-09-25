<?php

/**
 * Easy image resize function
 *
 * @param  $file - file name to resize
 * @param  $string - The image data, as a string
 * @param  $width - new image width
 * @param  $height - new image height
 * @param  $proportional - keep image proportional, default is no
 * @param  $output - name of the new file (include path if needed)
 * @param  $delete_original - if true the original image will be deleted
 * @param  $use_linux_commands - if set to true will use "rm" to delete the image, if false will use PHP unlink
 * @param  $quality - enter 1-100 (100 is best quality) default is 100
 * @return boolean|resource
 *
 * @package wyz
 */

if ( ! function_exists( 'wyz_getimagesizefromstring' ) ) {
	function wyz_getimagesizefromstring( $data ) {
		$uri = 'data://application/octet-stream;base64,' . base64_encode( $data );
		return getimagesize( $uri );
	}
}

function wyz_smart_resize_image( $file, $string = null, $width = 0, $height = 0, $proportional = false, $output = 'file', $delete_original = true, $use_linux_commands = false, $quality = 100 ) {

	if ( $height <= 0 && $width <= 0 ) {
		return false;
	}

	if ( null === $file && null === $string ) {
		return false;
	}
	// Setting defaults and meta.
	$info = null !== $file ? getimagesize( $file ) : wyz_getimagesizefromstring( $string );
	$image = '';
	$final_width = 0;
	$final_height = 0;
	list($width_old, $height_old) = $info;

	$cropHeight = $cropWidth = 0;
	// Calculating proportionality.
	if ( $proportional ) {
		if ( 0 == $width ) {
			$factor = $height / $height_old;
		} elseif ( 0 == $height ) {
			$factor = $width / $width_old;
		} else {
			$factor = min( $width / $width_old, $height / $height_old );
		}

		$final_width = round( $width_old * $factor );
		$final_height = round( $height_old * $factor );
	} else {
		$final_width = ( $width <= 0 ) ? $width_old : $width;
		$final_height = ( $height <= 0 ) ? $height_old : $height;
		$widthX = $width_old / $width;
		$heightX = $height_old / $height;

		$x = min( $widthX, $heightX );
		$cropWidth = ( $width_old - $width * $x ) / 2;
		$cropHeight = ( $height_old - $height * $x ) / 2;
	}
	// Loading image to memory according to type.
	switch ( $info[2] ) {
		case IMAGETYPE_JPEG:
			null !== $file ? $image = imagecreatefromjpeg( $file ) : $image = imagecreatefromstring( $string );
		break;

		case IMAGETYPE_GIF:
			null !== $file ? $image = imagecreatefromgif( $file ) : $image = imagecreatefromstring( $string );
		break;

		case IMAGETYPE_PNG:
			null !== $file ? $image = imagecreatefrompng( $file ) : $image = imagecreatefromstring( $string );
		break;

		default:
		return false;
	}
	// This is the resizing/resampling/transparency-preserving magic.
	$image_resized = imagecreatetruecolor( $final_width, $final_height );
	if ( ( IMAGETYPE_GIF == $info[2] ) || ( IMAGETYPE_PNG == $info[2] ) ) {
		$transparency = imagecolortransparent( $image );
		$palletsize = imagecolorstotal( $image );

		if ( $transparency >= 0 && $transparency < $palletsize ) {
			$transparent_color = imagecolorsforindex( $image, $transparency );
			$transparency = imagecolorallocate( $image_resized, $transparent_color['red'], $transparent_color['green'], $transparent_color['blue'] );
			imagefill( $image_resized, 0, 0, $transparency );
			imagecolortransparent( $image_resized, $transparency );
		} elseif ( IMAGETYPE_PNG == $info[2] ) {
			imagealphablending( $image_resized, false );
			$color = imagecolorallocatealpha( $image_resized, 0, 0, 0, 127 );
			imagefill( $image_resized, 0, 0, $color );
			imagesavealpha( $image_resized, true );
		}
	}
	imagecopyresampled( $image_resized, $image, 0, 0, $cropWidth, $cropHeight, $final_width, $final_height, $width_old - 2 * $cropWidth, $height_old - 2 * $cropHeight );
	// Taking care of original, if needed.
	if ( $delete_original ) {
		if ( $use_linux_commands ) {
			exec( 'rm ' . $file );
		} else {
			@unlink( $file );
		}
	}
	// Preparing a method of providing result.
	switch ( strtolower( $output ) ) {
		case 'browser':
			$mime = image_type_to_mime_type( $info[2] );
			header( "Content-type: $mime" );
			$output = null;
		break;

		case 'file':
			$output = $file;
		break;

		case 'return':
			return $image_resized;
		break;

		default:
		break;
	}
	// Writing image according to type to the output destination and image quality.
	switch ( $info[2] ) {
		case IMAGETYPE_GIF:
			imagegif( $image_resized, $output );
		break;

		case IMAGETYPE_JPEG:
			imagejpeg( $image_resized, $output, $quality );
		break;

		case IMAGETYPE_PNG:
			$quality = 9 - (int) ( ( 0.9 * $quality ) / 10.0 );
			imagepng( $image_resized, $output, $quality );
		break;

		default:
		return false;
	}

	return true;
}

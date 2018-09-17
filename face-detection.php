<?php
require_once __DIR__ . '/functions.php';

// @todo
//
/*
class WP_Error { function __construct( $c, $m ) { $this->c = $c; $this->m = $m; } }
function __( $t, $d ) { return $t; }
function apply_filters( $f, $v ) { return $v; }
function absint( $i ) { return abs( intval( $i ) ); }

$source = '/media/sf_Downloads/s3-news-tmp-85019-oscar--2x1--940.jpg';

$faces = face_detection_get_faces( $source );

if ( is_a( $faces, 'WP_Error') ) {
	echo $faces->m . PHP_EOL;
} elseif ( empty( $faces ) ) {
	echo "No faces found, doing nothing\n";
} else {
	$image = imagecreatefromjpeg( $source );

	foreach ( $faces as $face ) {
		$color = imagecolorallocate( $image, 255, 255, 0 );

		imagerectangle( $image, $face->x, $face->y, $face->x + $face->width, $face->y + $face->height, $color );
	}

	$output = __DIR__ . '/test.jpg';

	imagejpeg( $image, $output );

	echo "Saved image $output\n";
}
 */

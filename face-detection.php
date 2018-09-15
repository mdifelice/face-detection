<?php
include 'functions.php';
include 'class-face-detection-scanner.php';

function apply_filters( $f, $v ) { return $v; }
function absint( $i ) { return abs( intval( $i ) ); }

$source = '/media/sf_Downloads/20180522_094613.jpg';

$faces = face_detection_get_faces( $source );

if ( empty( $faces ) ) {
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

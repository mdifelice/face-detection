<?php
include 'functions.php';

function apply_filters( $f, $v ) { return $v; }
function absint( $i ) { return abs( intval( $i ) ); }

print_r( face_detection_get_faces( '/Users/pulpo/Downloads/jviolajones/trunk/lena.jpg' ) );

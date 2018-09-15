<?php
/**
 * Returns an array of coordinates of faces found in an image.
 *
 * @param string  $image_file       Image file.
 * @param float   $base_scale       Optional. The initial ratio between the
 *                                  window size and the Haar classifier size.
 *                                  Default is 2.
 * @param float   $scale_increment  Optional. The scale increment of the window
 *                                  size at each step. Default is 1.25.
 * @param float   $increment        Optional. The shift of the window at each
 *                                  sub-step, in terms of percentage of the
 *                                  window size. Default is 0.1.
 * @param int     $min_neighbours   Optional. The minimum number of rectangles
 *                                  needed for the corresponding detection to be
 *                                  kept. Default is 2.
 * @param boolean $do_canny_pruning Optional. Whether to perform canny pruning.
 *                                  Default is TRUE.
 *
 * @return array|WP_Error Coordinates of faces found in the image or a 
 *                        WP_Error object in case of an error.
 */
function face_detection_get_faces( $image_file, $base_scale = 2, $scale_increment = 1.25, $increment = 0.1, $min_neighbours = 2, $do_canny_pruning = true ) {
	$faces = array();

	try {
		$image_size = getimagesize( $image_file );

		if ( ! $image_size ) {
			throw new Exception( __( 'Invalid image', 'face-detection' ) );
		}

		$image_callback = null;
		$image          = null;

		switch ( $image_size[2] ) {
			case IMAGETYPE_JPEG:
				$image_callback = 'imagecreatefromjpeg';
				break;
			case IMAGETYPE_GIF:
				$image_callback = 'imagecreatefromgif';
				break;
			case IMAGETYPE_PNG:
				$image_callback = 'imagecreatefrompng';
				break;
		}

		if ( $image_callback ) {
			$image = call_user_func( $image_callback, $image_file );
		}

		if ( ! $image ) {
			throw new Exception( __( 'Unknown image format', 'face-detection' ) );
		}

		$image_width  = $image_size[0];
		$image_height = $image_size[1];

		$haarcascade_file = apply_filters( 'face_detection_haarcascade_file', __DIR__ . '/assets/haarcascade-frontalface.xml' );

		$xml = simplexml_load_file( $haarcascade_file );

		if ( ! $xml ) {
			throw new Exception( __( 'Cannot load Haar file', 'face-detection' ) ); 
		}

		$xml_root = $xml->children()[0];

		/**
		 * Stages specify if the considered zone represents the object with a
		 * probability greater than 0.5. If a zone passes all stages, it is
		 * considered that represents the object.
		 */
		$stages = array();

		list( $classifier_width, $classifier_height ) = array_map( 'absint', explode( ' ', $xml_root->size ) );

		if ( ! $classifier_width || ! $classifier_height ) {
			throw new Exception( __( 'Invalid classifier size', 'face-detection' ) );
		}

		$xml_stages = $xml_root->stages->_;

		foreach ( $xml_stages as $xml_stage ) {
			$stage = new StdClass();

			$stage->threshold = floatval( $xml_stage->stage_threshold );
			$stage->trees     = array();

			foreach ( $xml_stage->trees->_ as $xml_tree ) {
				$tree = new StdClass();

				$tree->features = array();

				foreach ( $xml_tree->_->feature as $xml_feature ) {
					$feature = new StdClass();

					$feature->rectangles = array();
					$feature->threshold  = floatval( $xml_tree->_->threshold );
					$feature->left_val   = floatval( $xml_tree->_->left_val );
					$feature->right_val  = floatval( $xml_tree->_->right_val );
					$feature->left_node  = absint( $xml_tree->_->left_node );
					$feature->right_node = absint( $xml_tree->_->right_node );

					foreach ( $xml_feature->rects->_ as $xml_rect ) {
						$rectangle = new StdClass();

						$values = explode( ' ', strval( $xml_rect ) );

						list( $rectangle->x1, $rectangle->x2, $rectangle->y1, $rectangle->y2 ) = array_map( 'absint', $values );

						$rectangle->weight = floatval( $values[4] );

						$feature->rectangles[] = $rectangle;
					}

					$tree->features[] = $feature;
				}

				$stage->trees[] = $tree;
			}

			$stages[] = $stage;
		}

		$image_gray     = array();
		$image_integral = array();
		$image_squared  = array();

		for ( $x = 0; $x < $image_width; $x++ ) {
			$image_gray[ $x ]     = array();
			$image_integral[ $x ] = array();
			$image_squared[ $x ]  = array();

			$column         = 0;
			$column_squared = 0;

			for ( $y = 0; $y < $image_height; $y++ ) {
				$color = imagecolorat( $image, $x, $y );
				$rgb   = imagecolorsforindex( $image, $color );

				$value         = ( ( 30 * $rgb['red'] ) + ( 59 * $rgb['green'] ) + ( 11 * $rgb['blue'] ) ) / 100;
				$value_squared = $value * $value;

				$image_integral[ $x ][ $y ] = $value;
				$image_gray[ $x ][ $y ]     = ( $x > 0 ? $image_gray[ $x - 1 ][ $y ] : 0 ) + $column + $value;
				$image_squared[ $x ][ $y ]  = ( $x > 0 ? $image_squared[ $x - 1 ][ $y ] : 0 ) + $column_squared + $value_squared;

				$column         += $value;
				$column_squared += $value_squared;
			}
		}

		imagedestroy( $image );

		$image_canny = null;

		if ( $do_canny_pruning ) {
			$image_canny    = array();
			$image_gradient = array();

			for ( $x = 0; $x < $image_width; $x++ ) {
				$image_canny[ $x ] = array();

				for ( $y = 0; $y < $image_height; $y++ ) {
					$value = 0;

					if ( $x >= 2 && $x < $image_width - 2 && $y >= 2 && $y < $image_height - 2 ) {
						$value += 2 * $image_integral[ $x - 2 ][ $y - 2 ];
						$value += 4 * $image_integral[ $x - 2 ][ $y - 1 ];
						$value += 5 * $image_integral[ $x - 2 ][ $y + 0 ];
						$value += 4 * $image_integral[ $x - 2 ][ $y + 1 ];
						$value += 2 * $image_integral[ $x - 2 ][ $y + 2 ];
						$value += 4 * $image_integral[ $x - 1 ][ $y - 2 ];
						$value += 9 * $image_integral[ $x - 1 ][ $y - 1 ];
						$value += 12 * $image_integral[ $x - 1 ][ $y + 0 ];
						$value += 9 * $image_integral[ $x - 1 ][ $y + 1 ];
						$value += 4 * $image_integral[ $x - 1 ][ $y + 2 ];
						$value += 5 * $image_integral[ $x + 0 ][ $y - 2 ];
						$value += 12 * $image_integral[ $x + 0 ][ $y - 1 ];
						$value += 15 * $image_integral[ $x + 0 ][ $y + 0 ];
						$value += 12 * $image_integral[ $x + 0 ][ $y + 1 ];
						$value += 5 * $image_integral[ $x + 0 ][ $y + 2 ];
						$value += 4 * $image_integral[ $x + 1 ][ $y - 2 ];
						$value += 9 * $image_integral[ $x + 1 ][ $y - 1 ];
						$value += 12 * $image_integral[ $x + 1 ][ $y + 0 ];
						$value += 9 * $image_integral[ $x + 1 ][ $y + 1 ];
						$value += 4 * $image_integral[ $x + 1 ][ $y + 2 ];
						$value += 2 * $image_integral[ $x + 2 ][ $y - 2 ];
						$value += 4 * $image_integral[ $x + 2 ][ $y - 1 ];
						$value += 5 * $image_integral[ $x + 2 ][ $y + 0 ];
						$value += 4 * $image_integral[ $x + 2 ][ $y + 1 ];
						$value += 2 * $image_integral[ $x + 2 ][ $y + 2 ];

						$value /= 159;
					}

					$image_canny[ $x ][ $y ] = $value;
				}
			}

			for ( $x = 0; $x < $image_width; $x++ ) {
				$image_gradient[ $x ] = array();

				for ( $y = 0; $y < $image_height; $y++ ) {
					$value = 0;

					if ( $x >= 1 && $x < $image_width - 1 && $y >= 1 && $y < $image_height - 1 ) {
						$value =
							abs( 
								-$image_canny[ $x - 1 ][ $y - 1 ] +
								$image_canny[ $x + 1 ][ $y - 1 ] -
								( 2 * $image_canny[ $x - 1 ][ $y ] ) +
								( 2 * $image_canny[ $x + 1 ][ $y ] ) -
								$image_canny[ $x - 1 ][ $y + 1 ] +
								$image_canny[ $x + 1 ][ $y + 1 ]
							) + abs(
								$image_canny[ $x - 1 ][ $y - 1 ] +
								( 2 * $image_canny[ $x ][ $y - 1 ] ) +
								$image_canny[ $x + 1 ][ $y - 1 ] -
								$image_canny[ $x - 1 ][ $y + 1 ] -
								( 2 * $image_canny[ $x ][ $y + 1 ] ) -
								$image_canny[ $x + 1 ][ $y + 1 ]
							);
					}

					$image_gradient[ $x ][ $y ] = $value;
				}
			}

			for ( $x = 0; $x < $image_width; $x++ ) {
				$column = 0;

				for ( $y = 0; $y < $image_height; $y++ ) {
					$value = $image_gradient[ $x ][ $y ];

					$image_canny[ $x ][ $y ] = ( $x ? $image_canny[ $x - 1 ][ $y ] : 0 ) + $column + $value;

					$column += $value;
				}
			}
		}

		$rectangles  = array();
		$max_scale   = min( $image_width / $classifier_width, $image_height / $classifier_height );

		for ( $scale = $base_scale; $scale < $max_scale; $scale *= $scale_increment ) {
			$size = intval( $scale * $classifier_width );
			$step = intval( $size * $increment );

			for ( $x = 0; $x < $image_width - $size; $x += $step ) {
				for ( $y = 0; $y < $image_height - $size; $y += $step ) {
					$scanner = new Face_Detection_Scanner( $x, $y, $size, $stages, $image_canny, $image_gray, $image_squared );

					while ( $scanner->isRunning() ) {
						usleep( 100 );
					}

					$rectangle = $scanner->getRectangle();

					if ( $rectangle ) {
						$rectangles[] = $rectangle;
					}
				}
			}
		}

		/**
		 * Merge the raw detections resulting from the detection step in order
		 * to avoid multiple detections of the same object.
		 */
		$rectangle_equals = array();
		$neighbour_types  = 0;
		$neighbours       = array();
		$rectangle_count  = count( $rectangles );

		for ( $i = 0; $i < $rectangle_count; $i++ ) {
			$found = false;

			$rectangle_i = $rectangles[ $i ];

			for ( $j = 0; $j < $i; $j++ ) {
				$rectangle_j = $rectangles[ $j ];
				$distance    = intval( $rectangle_j->width * 0.2 );

				if (
					(
						$rectangle_i->x <= $rectangle_j->x + $distance &&
						$rectangle_i->x >= $rectangle_j->x - $distance &&
						$rectangle_i->y <= $rectangle_j->y + $distance &&
						$rectangle_i->y >= $rectangle_j->y - $distance &&
						$rectangle_i->width <= intval( $rectangle_j->width * 1.2 ) &&
						intval( $rectangle_i->width * 1.2 ) >= $rectangle_j->width
					) || (
						$rectangle_j->x >= $rectangle_i->x &&
						$rectangle_j->x + $rectangle_j->width <= $rectangle_i->x + $rectangle_i->width &&
						$rectangle_j->y >= $rectangle_i->y &&
						$rectangle_j->y + $rectangle_j->height <= $rectangle_i->y + $rectangle_i->height
					)
				) {
					$found                  = true;
					$rectangle_equals[ $i ] = $rectangle_equals[ $j ];
				}
			}

			if ( ! $found ) {
				$rectangle_equals[ $i ] = $neighbour_types;

				$neighbour_types++;
			}
		}

		$aux_rectangles = array();

		for ( $i = 0; $i < $neighbour_types; $i++ ) {
			$rectangle = new StdClass();

			$rectangle->x      = 0;
			$rectangle->y      = 0;
			$rectangle->width  = 0;
			$rectangle->height = 0;

			$neighbours[]     = 0;
			$aux_rectangles[] = $rectangle;
		}

		$rectangles_count = count( $rectangles );

		for ( $i = 0; $i < $rectangles_count; $i++ ) {
			$j = $rectangle_equals[ $i ];

			$neighbours[ $j ]++;
			$aux_rectangles[ $j ]->x      += $rectangles[ $i ]->x;
			$aux_rectangles[ $j ]->y      += $rectangles[ $i ]->y;
			$aux_rectangles[ $j ]->width  += $rectangles[ $i ]->width;
			$aux_rectangles[ $j ]->height += $rectangles[ $i ]->height;
		}

		for ( $i = 0; $i < $neighbour_types; $i++ ) {
			$j = $neighbours[ $i ];

			if ( $j >= $min_neighbours ) {
				$face = new StdClass();

				$face->x      = ( $aux_rectangles[ $i ]->x * 2 + $j ) / ( 2 * $j );
				$face->y      = ( $aux_rectangles[ $i ]->y * 2 + $j ) / ( 2 * $j );
				$face->width  = ( $aux_rectangles[ $i ]->width * 2 + $j ) / ( 2 * $j );
				$face->height = ( $aux_rectangles[ $i ]->height * 2 + $j ) / ( 2 * $j );

				$faces[] = $face;
			}
		}
	} catch ( Exception $e ) {
		$faces = new WP_Error( 'face-detection-error', $e->getMessage() );
	}

	return $faces;
}

<?php
class Face_Detection_Scanner extends Thread {
	private $x,
			$y,
			$size,
			$stages,
			$image_canny,
			$image_gray,
			$image_squared,
			$rectangle;

	public function __construct( $x, $y, $size, $stages, $image_canny, $image_gray, $image_squared ) {
		$this->x = $x;
		$this->y = $y;
		$this->size = $size;
		$this->stages = $stages;
		$this->image_canny = $image_canny;
		$this->image_gray = $image_gray;
		$this->rectangle = null;
	}

	public function run() {
		$x = $this->x;
		$y = $this->y;
		$size = $this->size;
		$stages = $this->stages;
		$image_canny = $this->image_canny;
		$image_gray = $this->image_gray;
		$image_squared = $this->image_squared;
		$pass = true;
		$rectangle = null;

		if ( $image_canny ) {
			$edges_density = $image_canny[ $x + $size ][ $y + $size ] + $image_canny[ $x ][ $y ] - $image_canny[ $x ][ $y + $size ] - $image_canny[ $x + $size ][ $y ];

			$d = $edges_density / $size / $size;

			if ( $d < 20 || $d > 100 ) {
				$pass = false;
			}
		}

		if ( $pass ) {
			foreach ( $stages as $stage ) {
				$value = 0;

				foreach ( $stage->trees as $tree ) {
					$feature    = $tree->features[0];
					$tree_value = null;

					while ( null === $tree_value ) {
						$feature_width  = intval( $scale * $classifier_width );
						$feature_height = intval( $scale * $classifier_height );
						$inverse_area   = 1 / ( $feature_width * $feature_height );

						$total_x         =
							$image_gray[ $x + $feature_width ][ $y + $feature_height ] +
							$image_gray[ $x ][ $y ] -
							$image_gray[ $x ][ $y + $feature_height ] -
							$image_gray[ $x + $feature_width ][ $y ];
						$total_x_squared =
							$image_squared[ $x + $feature_width ][ $y + $feature_height ] +
							$image_squared[ $x ][ $y ] -
							$image_squared[ $x ][ $y + $feature_height ] -
							$image_squared[ $x + $feature_width ][ $y ];

						$moy   = $total_x * $inverse_area;
						$vnorm = ( $total_x_squared * $inverse_area ) - ( $moy * $moy );
						$vnorm = $vnorm > 1 ? sqrt( $vnorm ) : 1;

						$rectangle_sum   = 0;
						$rectangle_count = count( $feature->rectangles );

						for ( $i = 0; $i < $rectangle_count; $i++ ) {
							$rectangle = $feature->rectangles[ $i ];

							$x1 = $x + intval( $scale * $rectangle->x1 );
							$x2 = $x + intval( $scale * ( $rectangle->x1 + $rectangle->y1 ) );
							$y1 = $y + intval( $scale * $rectangle->x2 );
							$y2 = $y + intval( $scale * ( $rectangle->x2 + $rectangle->y2 ) );

							$rectangle_sum += intval( (
								$image_gray[ $x2 ][ $y2 ] -
								$image_gray[ $x1 ][ $y2 ] -
								$image_gray[ $x2 ][ $y1 ] +
								$image_gray[ $x1 ][ $y1 ]
							) * $rectangle->weight );
						}

						$left = $rectangle_sum * $inverse_area < $feature->threshold * $vnorm;

						if ( $left ) {
							if ( $feature->left_val ) {
								$tree_value = $feature->left_val;
							} elseif ( isset( $tree->features[ $feature->left_node ] ) ) {
								$feature = $tree->features[ $feature->left_node ];
							} else {
								throw new Exception( __( 'Missing tree node', 'face-detection' ) );
							}
						} else {
							if ( $feature->right_val ) {
								$tree_value = $feature->right_val;
							} elseif ( isset( $tree->features[ $feature->right_node ] ) ) {
								$feature = $tree->features[ $feature->right_node ];
							} else {
								throw new Exception( __( 'Missing tree node', 'face-detection' ) );
							}
						}
					}

					$value += $tree_value;
				}

				if ( $value <= $stage->threshold ) {
					$pass = false;

					break;
				}
			}
		}

		if ( $pass ) {
			$rectangle = new StdClass();

			$rectangle->x      = $x;
			$rectangle->y      = $y;
			$rectangle->width  = $size;
			$rectangle->height = $size;
		}

		$this->rectangle = $rectangle;
	}

	public function getRectangle() {
		return $this->rectangle;
	}
}

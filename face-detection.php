<?php
/**
 * Plugin Name: Face Detection
 * Description: Detects faces on uploaded images thus cropped generated
 *              thumbnails will not have cut heads.
 * Plugin URI:  https://github.com/mdifelice/face-detection
 * Author:      Martin Di Felice
 * Author URI:  https://github.com/mdifelice
 * Text Domain: face-detection
 * Domain Path: /languages
 * Version:     1.0
 * License:     GPL2
 *
 * Face Detection is free software: you can redistribute it and/or modify it * under the terms of the GNU General Public License as published by the Free
 * Software Foundation, either version 2 of the License, or any later version.
 *
 * Face Detection is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with
 * Face Detection. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
 *
 * @package Face_Detection
 */

/**
 * Requiring files.
 */
require_once __DIR__ . '/functions.php';

add_action(
	'init',
	function() {
		load_theme_textdomain( 'face-detection', __DIR__ . '/languages' );
	}
);

add_action(
	'admin_init',
	function() {
		add_settings_section(
			'face-detection',
			__( 'Face Detection', 'face-detection' ),
			null,
			'media'
		);

		add_settings_field(
			'face_detection_upload_enabled',
			__( 'Enable Face Detection in media upload?', 'face-detection' ),
			function() {
				printf(
					'<input type="checkbox" name="face_detection_upload_enabled" id="face_detection_upload_enabled" value="yes"%s /><p class="description">%s</p>',
					checked( 'yes', get_option( 'face_detection_upload_enabled' ), false ),
					esc_html__( 'If you enable this option, whenever a new image is uploaded the plugin will attempt to detect faces on it. If you leave it disabled, you still have the possibility to detect faces in images by going to the Media Library.', 'face-detection' )
				);
			},
			'media',
			'face-detection'
		);

		register_setting(
			'media',
			'face_detection_upload_enabled',
			function( $value ) {
				return 'yes' === $value ? 'yes' : 'no';
			}
		);
	}
);

add_action(
	'add_meta_boxes',
	function() {
		add_meta_box(
			'face-detection',
			__( 'Face Detection', 'face-detection' ),
			function( $post ) {
			?>
<div id="face-detection-thumbnails"></div>
			<?php

			if ( current_user_can( 'upload_files' ) ) {
			?>
<a class="button-primary face-detection-button" id="face-detection-regenerate" href="#"><?php esc_html_e( 'Regenerate thumbnails', 'face-detection' ); ?></a>
<a class="button-secondary face-detection-button" id="face-detection-reset" href="#"><?php esc_html_e( 'Reset thumbnails', 'face-detection' ); ?></a>
			<?php
			}
			},
			'attachment'
		);
	}
);

add_action(
	'admin_enqueue_scripts',
	function( $hook ) {
		if ( 'post.php' === $hook && 'attachment' === get_post_type() ) {
			$attachment_id = get_the_ID();
			$metadata      = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );

			wp_enqueue_style(
				'face-detection',
				plugins_url( 'assets/face-detection.css', __FILE__ )
			);

			wp_enqueue_script(
				'face-detection',
				plugins_url( 'assets/face-detection.js', __FILE__ ),
				array( 'jquery' )
			);

			wp_localize_script(
				'face-detection',
				'face_detection',
				array(
					'attachment_id'   => get_the_ID(),
					'faces'           => get_post_meta( $attachment_id, 'face_detection_faces', true ),
					'i18n'            => array(
						'error'                   => __( 'There has been an error. Please, try again.', 'face-detection' ),
						'loading_thumbnails'      => __( 'Loading thumbnails...', 'face-detection' ),
						'no_thumbnails'           => __( 'There are not any cropped thumbnails.', 'face-detection' ),
						'regenerating_thumbnails' => __( 'Regenerating thumbnails...', 'face-detection' ),
					),
					'nonces'          => array(
						'load_thumbnails'       => wp_create_nonce( 'face_detection_load_thumbnails' ),
						'regenerate_thumbnails' => wp_create_nonce( 'face_detection_regenerate_thumbnails' ),
					),
					'original_height' => $metadata['height'],
					'original_width'  => $metadata['width'],
				)
			);
		}
	}
);

add_action(
	'wp_ajax_face_detection_load_thumbnails',
	function() {
		check_ajax_referer( 'face_detection_load_thumbnails' );

		$thumbnails = null;

		if ( isset( $_POST['attachment_id'] ) ) {
			$attachment_id = intval( $_POST['attachment_id'] );
			$thumbnails    = face_detection_get_cropped_thumbnails( $attachment_id );
		}

		wp_send_json( $thumbnails );
	}
);

add_action(
	'wp_ajax_face_detection_regenerate_thumbnails',
	function() {
		check_ajax_referer( 'face_detection_regenerate_thumbnails' );

		$thumbnails = null;

		if ( isset( $_POST['attachment_id'] ) ) {
			$attachment_id = intval( $_POST['attachment_id'] );
			$ignore_faces  = false;

			if ( isset( $_POST['ignore_faces'] ) ) {
				$ignore_faces = 'yes' === sanitize_text_field( wp_unslash( $_POST['ignore_faces'] ) );
			}

			if ( current_user_can( 'upload_files' ) ) {
				if ( $ignore_faces ) {
					$filter_callback = '__return_false';
				} else {
					$filter_callback = '__return_true';
				}

				add_filter( 'face_detection_upload_enabled', $filter_callback );

				$file     = get_attached_file( $attachment_id );
				$metadata = wp_generate_attachment_metadata( $attachment_id, $file );

				wp_update_attachment_metadata( $attachment_id, $metadata );

				remove_filter( 'face_detection_upload_enabled', $filter_callback );
			}

			$thumbnails = face_detection_get_cropped_thumbnails( $attachment_id );
		}

		wp_send_json( $thumbnails );
	}
);

add_filter(
	'intermediate_image_sizes_advanced',
	function( $sizes, $metadata ) {
		global $face_detection_faces;

		$upload_enabled = 'yes' === get_option( 'face_detection_upload_enabled' );

		$upload_enabled = apply_filters( 'face_detection_upload_enabled', $upload_enabled );

		if ( $upload_enabled ) {
			$upload_dir = _wp_upload_dir();
			$image_file = $upload_dir['basedir'] . '/' . $metadata['file'];
			$cache_key  = 'face_detection_' . md5( $image_file );
			$faces      = wp_cache_get( $cache_key );

			if ( false === $faces ) {
				$faces = face_detection_get_faces( $image_file );

				if ( ! empty( $faces ) && ! is_wp_error( $faces ) ) {
					$face_detection_faces = $faces;

					wp_cache_set( $cache_key, $faces );
				}
			} else {
				$face_detection_faces = $faces;
			}
		}

		return $sizes;
	},
	10,
	2
);

add_filter(
	'image_resize_dimensions',
	function( $payload, $orig_w, $orig_h, $dest_w, $dest_h, $crop ) {
		global $face_detection_faces;

		if ( isset( $face_detection_faces ) && $crop ) {
			$max_size = 0;

			foreach ( $face_detection_faces as $face ) {
				$size = $face->width * $face->height;

				if ( $size > $max_size ) {
					$max_size = $size;
				}
			}

			$centers = array();

			foreach ( $face_detection_faces as $face ) {
				$size = $face->width * $face->height;

				if ( $size === $max_size ) {

					$centers[] = array(
						$face->x + ( $face->width / 2 ),
						$face->y + ( $face->height / 2 ),
					);
				}
			}

			$sum_center_x = 0;
			$sum_center_y = 0;

			foreach ( $centers as $center ) {
				$sum_center_x += $center[0];
				$sum_center_y += $center[1];
			}

			$centers_count = count( $centers );
			$center_x      = round( $sum_center_x / $centers_count );
			$center_y      = round( $sum_center_y / $centers_count );
			$aspect_ratio  = $orig_w / $orig_h;
			$new_w         = min( $dest_w, $orig_w );
			$new_h         = min( $dest_h, $orig_h );

			if ( ! $new_w ) {
				$new_w = intval( $new_h * $aspect_ratio );
			}

			if ( ! $new_h ) {
				$new_h = intval( $new_w / $aspect_ratio );
			}

			$size_ratio = max( $new_w / $orig_w, $new_h / $orig_h );

			$crop_w = round( $new_w / $size_ratio );
			$crop_h = round( $new_h / $size_ratio );
			$crop_x = min( $orig_w - $crop_w, max( 0, round( $center_x - $crop_w / 2 ) ) );
			$crop_y = min( $orig_h - $crop_h, max( 0, round( $center_y - $crop_h / 2 ) ) );

			$payload = array(
				0,
				0,
				intval( $crop_x ),
				intval( $crop_y ),
				intval( $new_w ),
				intval( $new_h ),
				intval( $crop_w ),
				intval( $crop_h ),
			);
		}

		return $payload;
	},
	10,
	6
);

add_filter(
	'wp_generate_attachment_metadata',
	function( $metadata, $attachment_id ) {
		global $face_detection_faces;

		if ( isset( $face_detection_faces ) ) {
			update_post_meta( $attachment_id, 'face_detection_faces', $face_detection_faces );
		}

		return $metadata;
	},
	10,
	2
);

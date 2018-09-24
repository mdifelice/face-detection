jQuery( document ).ready( function() {
	var wrapFaces = function() {
		var faces = face_detection.faces;

		if ( faces ) {
			var image     = jQuery( '.thumbnail' );
			var container = image.parent();
			var maxSize   = 0;

			container.find( '.face-detection-face' ).remove();

			for ( var i in faces ) {
				var face = faces[ i ];
				var size = face.width * face.height;

				if ( size > maxSize ) {
					maxSize = size;
				}
			}

			for ( var i in faces ) {
				var face    = faces[ i ];
				var size    = face.width * face.height;
				var ratio   = 100 * image.width() / container.width();
				var wrapper = jQuery( '<div/>' )
					.addClass( 'face-detection-face' )
					.css( {
						left   : Math.round( ratio * face.x / face_detection.original_width ) + '%',
						top    : Math.round( ratio * face.y / face_detection.original_height ) + '%',
						width  : Math.round( ratio * face.width / face_detection.original_width ) + '%',
						height : Math.round( ratio * face.height / face_detection.original_height ) + '%',
					} );

				if ( size === maxSize ) {
					wrapper.addClass( 'face-detection-face-biggest' );
				}

				container.append( wrapper );
			}
		}
	};

	var loadThumbnails = function( regenerate, ignoreFaces ) {
		var regenerate  = regenerate || false;
		var ignoreFaces = ignoreFaces || false;
		var action      = 'face_detection_' + ( regenerate ? 'regenerate' : 'load' ) + '_thumbnails';
		var nonce       = ( regenerate ? 'regenerate' : 'load' ) + '_thumbnails';

		jQuery( '#face-detection-thumbnails' )
			.empty()
			.append( jQuery( '<p/>' )
				.addClass( 'description' )
				.text( regenerate ? face_detection.i18n.regenerating_thumbnails : face_detection.i18n.loading_thumbnails )
			);

		jQuery( '.face-detection-button' ).attr( 'disabled', 'disabled' );

		jQuery.ajax( {
			complete : function() {
				jQuery( '.face-detection-button' ).removeAttr( 'disabled' );
			},
			error    : function() {
				jQuery( '#face-detection-thumbnails' )
					.empty()
					.append( jQuery( '<p/>' )
						.addClass( 'description' )
						.text( face_detection.i18n.error )
					);
			},
			data     : {
				action        : action,
				_wpnonce      : face_detection.nonces[ nonce ],
				attachment_id : face_detection.attachment_id,
				ignore_faces  : ignoreFaces ? 'yes' : 'no',
			},
			method   : 'POST',
			success  : function( response ) {
				var container = jQuery( '#face-detection-thumbnails' );

				container.empty();

				if ( ! Object.keys( response ).length ) {
					container
						.append( jQuery( '<p/>' )
							.addClass( 'description' )
							.text( face_detection.i18n.no_thumbnails )
						);
				} else {
					for ( var size in response ) {
						var image = response[ size ];

						image += ( image.indexOf( '?' ) === -1 ? '?' : '&' ) + 'r=' + ( new Date() ).getTime();

						container
							.append( jQuery( '<div/>' )
								.addClass( 'face-detection-thumbnail' )
								.append( jQuery( '<img/>' )
									.attr( 'src', image )
								)
								.append( jQuery( '<span/>' )
									.text( size )
								)
							);
					}
				}
			},
			url      : ajaxurl,
		} );
	};

	if ( 'undefined' !== typeof window.face_detection ) {
		wrapFaces();

		loadThumbnails();

		window.addEventListener( 'resize', wrapFaces );

		jQuery( '#face-detection-regenerate' ).click( function( e ) {
			e.preventDefault();

			loadThumbnails( true );
		} );

		jQuery( '#face-detection-reset' ).click( function( e ) {
			e.preventDefault();

			loadThumbnails( true, true );
		} );
	}
} );

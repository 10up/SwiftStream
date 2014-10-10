( function( window, $, undefined ) {
	var $window = $( window ),
		$document = $( window.document ),
		images = $( 'img[data-lazy]'),
		throttle_id;

	/**
	 * Determine whether or not a given image is in the current viewport.
	 *
	 * @param {DOMElement} image
	 *
	 * @return {Boolean}
	 */
	function inView( image ) {
		var $image = $( image ),
			view_top = $window.scrollTop() - 300,
			view_bottom = view_top + $window.height() + 600,
			height = $image.height(),
			_top = $image.offset().top,
			_bottom = _top + height;

		// Only fire on elements with non-zero heights.If the top edge is above the bottom threshold, or the bottom
		// edge is below the top threshold, then the image is visible and should be loaded. Otherwise, return false.
		return ( height > 0 ) && ( _top <= view_bottom ) && ( _bottom >= view_top );
	}

	/**
	 * Iterate through a jQuery array of images and, if they're visible, load them into the page.
	 *
	 * @param {Array} images
	 */
	function maybeLoad( images ) {
		var did_lazy_load = false;

		// Iterate through every image on the page that has a `data-lazy` attribute.
		images.each( function( i, image ) {
			if ( image.hasAttribute( 'data-lazy') && inView( image ) ) {
				image.src = image.getAttribute( 'data-lazy' );
				image.removeAttribute( 'data-lazy' );

				// Fire a jQuery event on the image in case anyone else is watching.
				$( image ).trigger( 'lazy-load' );

				// Keep track that we need to repopulate the `images` array
				did_lazy_load = true;
			}
		} );

		// Repopulate our `images` array
		if ( did_lazy_load ) {
			images = $( 'image[data-lazy]' );
		}
	}

	$window.on( 'scroll', function() {
		if ( undefined !== throttle_id ) {
			// We're already living in a throttled environment, so exit
			return;
		}

		// If we've gotten this far, it means it's the first call to 'scroll' the application has received.
		// Set up a timeout to fire our callback in 250 milliseconds
		throttle_id = window.setTimeout(
			function() {
				maybeLoad( images );
				throttle_id = undefined;
			},
			250
		);
	} );

	// Fire our image parser immediately to force visible images into the display
	window.setTimeout( function() {
		maybeLoad( images );
	}, 1 );
} )( window, jQuery );
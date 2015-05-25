(function (window, document, attribute, selector, type, undefined) {
	var images = document.querySelectorAll(selector),
		throttle_id;

	/**
	 * Determine whether or not a given image is in the current viewport.
	 *
	 * @param {DOMElement} image
	 *
	 * @return {Boolean}
	 */
	function inView(image) {
		var innerHeight = 'innerHeight' in window ? window.innerHeight : document.documentElement.clientHeight,
			height = image.clientHeight,
			top = image.getBoundingClientRect().top;

		// Only fire on elements with non-zero heights.If the top edge is above the bottom threshold, or the bottom
		// edge is below the top threshold, then the image is visible and should be loaded. Otherwise, return false.
		return height > 0 && top < innerHeight && top + height > 0;
	}

	/**
	 * Iterate through a jQuery array of images and, if they're visible, load them into the page.
	 *
	 * @param {Array} images
	 */
	function maybeLoad(images) {
		var did_lazy_load = false, index = -1, image, event;

		// Iterate through every image on the page that has a `data-lazy` attribute.
		while (image = images[++index]) {
			if (image.hasAttribute(attribute) && inView(image)) {
				image.src = image.getAttribute(attribute);

				image.removeAttribute(attribute);

				// Fire a jQuery event on the image in case anyone else is watching.
				if (document.createEvent) {
					event = document.createEvent('Event');

					event.initEvent(type, true, true);

					image.dispatchEvent(event);
				} else {
					image.fireEvent('on' + type, document.createEventObject());
				}

				// Keep track that we need to repopulate the `images` array
				did_lazy_load = true;
			}
		}

		// Repopulate our `images` array
		if (did_lazy_load) {
			images = document.querySelectorAll(selector);
		}
	}

	function onscroll() {
		if (undefined !== throttle_id) {
			// We're already living in a throttled environment, so exit
			return;
		}

		// If we've gotten this far, it means it's the first call to 'scroll' the application has received.
		// Set up a timeout to fire our callback in 250 milliseconds
		throttle_id = window.setTimeout(function () {
			maybeLoad(images);

			throttle_id = undefined;
		}, 50);
	}

	if (window.addEventListener) {
		window.addEventListener('scroll', onscroll);
	} else {
		window.addEvent('onscroll', onscroll);
	}

	// Fire our image parser immediately to force visible images into the display
	window.setTimeout(function () {
		// Reset bound variables
		images = document.querySelectorAll(selector);

		if (undefined !== throttle_id) {
			window.clearTimeout(throttle_id);

			throttle_id = undefined;
		}

		// Load visible images
		maybeLoad(images);
	}, 1);
})(this, this.document, 'data-lazy', 'img[data-lazy]', 'lazy-load');

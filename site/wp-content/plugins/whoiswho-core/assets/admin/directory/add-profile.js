/**
 * Add profile screen (issue 798, REQ-209): choose the photo or logo from the media library.
 *
 * Opens the WordPress media modal limited to images, writes the chosen attachment id into the image field and shows the file
 * name. Remove clears it. Without JavaScript the id can still be typed; the server checks the file type and licence either way.
 */
( function () {
	'use strict';

	function parts( button ) {
		var form = button.closest( 'form' );
		if ( ! form ) {
			return null;
		}
		return {
			input: form.querySelector( 'input[name="image_id"]' ),
			label: form.querySelector( '.wiw-add-image-name' ),
		};
	}

	function pick( button ) {
		var found = parts( button );
		if ( ! found || ! found.input || ! window.wp || ! window.wp.media ) {
			return;
		}
		var frame = window.wp.media( {
			title: button.textContent,
			library: { type: [ 'image/jpeg', 'image/png', 'image/webp' ] },
			button: { text: button.textContent },
			multiple: false,
		} );
		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			found.input.value = attachment.id;
			if ( found.label ) {
				found.label.textContent = attachment.filename || attachment.title;
			}
		} );
		frame.open();
	}

	function clear( button ) {
		var found = parts( button );
		if ( ! found || ! found.input ) {
			return;
		}
		found.input.value = '';
		if ( found.label ) {
			found.label.textContent = 'Nothing chosen';
		}
	}

	document.addEventListener( 'click', function ( event ) {
		var target = event.target;
		if ( ! ( target instanceof Element ) ) {
			return;
		}
		var choose = target.closest( '.wiw-add-image-pick' );
		if ( choose ) {
			event.preventDefault();
			pick( choose );
			return;
		}
		var drop = target.closest( '.wiw-add-image-clear' );
		if ( drop ) {
			event.preventDefault();
			clear( drop );
		}
	} );
}() );

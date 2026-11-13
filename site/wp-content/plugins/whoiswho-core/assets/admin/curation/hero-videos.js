/**
 * Choose the home hero clips and posters from the media library (issue 799).
 *
 * Opens the WordPress media modal for a slot's field, writes the chosen attachment id into the slot's hidden input and shows the
 * file name and a preview. Nothing is saved until the person presses Save clip; the server checks every file again.
 */
( function () {
	'use strict';

	function field( form, name ) {
		return form.querySelector( 'input[name="' + name + '"]' );
	}

	function label( form, name ) {
		var input = field( form, name );
		return input && input.parentNode ? input.parentNode.querySelector( '.wiw-hero-name' ) : null;
	}

	function preview( form, name, attachment ) {
		var poster = form.querySelector( '.wiw-hero-poster' );
		var clip = form.querySelector( '.wiw-hero-clip' );
		if ( 'poster_id' === name && poster && attachment ) {
			var sizes = attachment.sizes || {};
			poster.src = ( sizes.thumbnail || sizes.full || attachment ).url;
		}
		if ( 'video_id' === name && clip && attachment ) {
			clip.src = attachment.url;
		}
	}

	function pick( button ) {
		var form = button.closest( 'form' );
		var name = button.getAttribute( 'data-target' );
		var kind = button.getAttribute( 'data-kind' );
		if ( ! form || ! window.wp || ! window.wp.media ) {
			return;
		}
		var frame = window.wp.media( {
			title: button.textContent,
			library: { type: kind },
			button: { text: button.textContent },
			multiple: false,
		} );
		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			field( form, name ).value = attachment.id;
			var shown = label( form, name );
			if ( shown ) {
				shown.textContent = attachment.filename || attachment.title;
			}
			preview( form, name, attachment );
		} );
		frame.open();
	}

	function clear( button ) {
		var form = button.closest( 'form' );
		var name = button.getAttribute( 'data-target' );
		if ( ! form ) {
			return;
		}
		field( form, name ).value = '0';
		var shown = label( form, name );
		if ( shown ) {
			shown.textContent = 'Nothing chosen';
		}
	}

	document.addEventListener( 'click', function ( event ) {
		var target = event.target;
		if ( ! ( target instanceof Element ) ) {
			return;
		}
		var choose = target.closest( '.wiw-hero-pick' );
		if ( choose ) {
			event.preventDefault();
			pick( choose );
			return;
		}
		var drop = target.closest( '.wiw-hero-clear' );
		if ( drop ) {
			event.preventDefault();
			clear( drop );
		}
	} );
}() );

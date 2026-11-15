/**
 * Staff UI kit (REQ-275): progressive enhancement for the staff list tables and forms. Everything works without it.
 *
 *  - Bulk bar: shows "N selected" and turns sticky while rows are checked.
 *  - Destructive bulk action: asks with confirm() and sets confirm=1, so the server skips its confirmation step. An action with
 *    extra fields (data-wiw-step) always goes to the server's confirmation step.
 *  - Row action links with data-wiw-confirm ask first.
 *  - Searchable select: a filter box above select[data-wiw-search].
 *  - Unsaved changes: a form with data-wiw-unsaved warns before leaving with unsaved edits; submitting it clears the warning.
 */
( function () {
	'use strict';

	function checkedBoxes( form ) {
		return form.querySelectorAll( 'tbody .check-column input[type="checkbox"]:checked' );
	}

	function updateCount( form ) {
		var out = form.querySelector( '.wiw-selected-count' );
		var count = checkedBoxes( form ).length;
		form.classList.toggle( 'has-selection', count > 0 );
		if ( ! out ) {
			return;
		}
		if ( 0 === count ) {
			out.textContent = '';
		} else if ( 1 === count ) {
			out.textContent = out.getAttribute( 'data-wiw-selected-one' ) || '1';
		} else {
			out.textContent = ( out.getAttribute( 'data-wiw-selected-many' ) || '%d' ).replace( '%d', String( count ) );
		}
	}

	function chosenSelect( form, submitter ) {
		var scope = submitter && submitter.closest ? submitter.closest( '.bulkactions' ) : null;
		var select = scope ? scope.querySelector( '.wiw-bulk-select' ) : null;
		if ( select && '-1' !== select.value ) {
			return select;
		}
		var all = form.querySelectorAll( '.wiw-bulk-select' );
		for ( var i = 0; i < all.length; i++ ) {
			if ( '-1' !== all[ i ].value ) {
				return all[ i ];
			}
		}
		return null;
	}

	document.querySelectorAll( '.wiw-list-form' ).forEach( function ( form ) {
		form.addEventListener( 'change', function () {
			updateCount( form );
		} );
		// WordPress's select-all sets the boxes without a change event; count after its click handler has run.
		form.addEventListener( 'click', function ( event ) {
			if ( event.target instanceof HTMLInputElement && 'checkbox' === event.target.type ) {
				window.setTimeout( function () {
					updateCount( form );
				}, 0 );
			}
		} );
		form.addEventListener( 'submit', function ( event ) {
			var select = chosenSelect( form, event.submitter );
			var confirmField = form.querySelector( '.wiw-list-confirm' );
			if ( ! select || ! confirmField ) {
				return;
			}
			var option = select.options[ select.selectedIndex ];
			if ( ! option || ! option.hasAttribute( 'data-wiw-destructive' ) || option.hasAttribute( 'data-wiw-step' ) ) {
				return;
			}
			var count = checkedBoxes( form ).length;
			if ( 0 === count ) {
				return;
			}
			var pattern = select.getAttribute( 'data-wiw-confirm-pattern' ) || '%1$s: %2$d';
			var question = pattern.replace( '%1$s', option.textContent.trim() ).replace( '%2$d', String( count ) );
			if ( window.confirm( question ) ) {
				confirmField.value = '1';
			} else {
				event.preventDefault();
			}
		} );
		updateCount( form );
	} );

	document.addEventListener( 'click', function ( event ) {
		var link = event.target instanceof Element ? event.target.closest( 'a[data-wiw-confirm]' ) : null;
		if ( link && ! window.confirm( link.getAttribute( 'data-wiw-confirm' ) ) ) {
			event.preventDefault();
		}
	} );

	document.querySelectorAll( 'select[data-wiw-search]' ).forEach( function ( select ) {
		var box = document.createElement( 'input' );
		box.type = 'search';
		box.className = 'wiw-select-filter';
		box.setAttribute( 'aria-label', select.getAttribute( 'data-wiw-search-label' ) || '' );
		box.setAttribute( 'aria-controls', select.id );
		select.parentNode.insertBefore( box, select );
		box.addEventListener( 'input', function () {
			var term = box.value.trim().toLowerCase();
			Array.prototype.forEach.call( select.options, function ( option ) {
				option.hidden = '' !== term && '' !== option.value && ! option.selected && -1 === option.textContent.toLowerCase().indexOf( term );
			} );
		} );
	} );

	var dirty = new Set();
	document.querySelectorAll( 'form[data-wiw-unsaved]' ).forEach( function ( form ) {
		function mark( event ) {
			if ( event.target && event.target.type !== 'hidden' ) {
				dirty.add( form );
			}
		}
		form.addEventListener( 'input', mark );
		form.addEventListener( 'change', mark );
		form.addEventListener( 'submit', function () {
			dirty.delete( form );
		} );
	} );
	window.addEventListener( 'beforeunload', function ( event ) {
		if ( dirty.size > 0 ) {
			event.preventDefault();
			event.returnValue = '';
		}
	} );
}() );

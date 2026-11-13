/**
 * Drag to order the pinned items on the Home page settings screen (REQ-257).
 *
 * Progressive enhancement: without this script the Move up and Move down buttons do the same job. Dragging a row rewrites the
 * comma-separated "pinned" box of the block's form; the editor then presses Save, which is the same save as typing the ids.
 */
( function () {
	'use strict';

	function syncField( list ) {
		var block = list.getAttribute( 'data-block' );
		var form = document.querySelector( 'form input[name="block"][value="' + block + '"]' );
		if ( ! form ) {
			return;
		}
		var field = form.form.querySelector( 'input[name="pinned"]' );
		if ( ! field ) {
			return;
		}
		var ids = [];
		list.querySelectorAll( ':scope > li' ).forEach( function ( li ) {
			ids.push( li.getAttribute( 'data-id' ) );
		} );
		field.value = ids.join( ', ' );
	}

	function enhance( list ) {
		var dragged = null;
		list.querySelectorAll( ':scope > li' ).forEach( function ( li ) {
			li.setAttribute( 'draggable', 'true' );
			li.addEventListener( 'dragstart', function ( event ) {
				dragged = li;
				event.dataTransfer.effectAllowed = 'move';
				event.dataTransfer.setData( 'text/plain', li.getAttribute( 'data-id' ) );
			} );
			li.addEventListener( 'dragover', function ( event ) {
				event.preventDefault();
			} );
			li.addEventListener( 'drop', function ( event ) {
				event.preventDefault();
				if ( ! dragged || dragged === li ) {
					return;
				}
				var after = dragged.compareDocumentPosition( li ) & Node.DOCUMENT_POSITION_FOLLOWING;
				list.insertBefore( dragged, after ? li.nextSibling : li );
				syncField( list );
			} );
			li.addEventListener( 'dragend', function () {
				dragged = null;
			} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( 'ol.wiw-pins' ).forEach( enhance );
	} );
}() );

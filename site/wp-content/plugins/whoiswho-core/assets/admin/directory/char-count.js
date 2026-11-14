/**
 * Edit profile screen (issue 806, REQ-125): a live character counter under each long text box.
 *
 * A textarea with data-wiw-count="<id>" and a maxlength updates the element with that id to "N / MAX characters" as the person
 * types. Line breaks count as one character, as the server counts them. Without JavaScript the server-rendered count still shows
 * and the browser's maxlength still applies.
 */
( function () {
	'use strict';

	function update( box ) {
		var out = document.getElementById( box.getAttribute( 'data-wiw-count' ) );
		if ( ! out ) {
			return;
		}
		var max = parseInt( box.getAttribute( 'maxlength' ), 10 ) || 0;
		var used = Array.from( box.value.replace( /\r\n/g, '\n' ) ).length;
		out.textContent = used + ' / ' + max + ' characters';
		out.classList.toggle( 'wiw-count-near', max > 0 && used > max * 0.9 );
	}

	document.addEventListener( 'input', function ( event ) {
		var target = event.target;
		if ( target instanceof HTMLTextAreaElement && target.hasAttribute( 'data-wiw-count' ) ) {
			update( target );
		}
	} );

	document.querySelectorAll( 'textarea[data-wiw-count]' ).forEach( update );
}() );

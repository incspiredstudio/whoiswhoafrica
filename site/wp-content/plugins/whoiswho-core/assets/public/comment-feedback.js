/**
 * Clear feedback for a visitor who posts a comment through wpDiscuz (REQ-258). While the comment is on its way the button is
 * off and a line says so; when it arrives the line says it is waiting for moderation (wpDiscuz clears the form); when it is
 * refused or the network fails the line says what to do. The same text cannot be sent twice in a row: a second click while it
 * is on its way, or soon after it arrived, is stopped with a message. Listens to wpDiscuz's own events; no wpDiscuz file is
 * changed. The words come from the plugin (window.wiwCommentFeedback).
 */
( function () {
	'use strict';

	var config = window.wiwCommentFeedback;
	var jq = window.jQuery;
	if ( ! config || ! jq ) {
		return;
	}
	var strings = config.strings || {};
	var repeatWindow = ( parseInt( config.repeatSeconds, 10 ) || 120 ) * 1000;
	var pending = new window.WeakMap();
	var sent = {};

	function normalise( text ) {
		return String( text || '' ).replace( /\s+/g, ' ' ).trim();
	}

	function commentText( form ) {
		var editor = form.querySelector( '.ql-editor' );
		var area = form.querySelector( 'textarea[name="wc_comment"]' );
		var fromEditor = editor ? normalise( editor.innerText ) : '';
		return fromEditor || normalise( area ? area.value : '' );
	}

	var counter = 0;

	function formKey( form ) {
		if ( ! form.getAttribute( 'data-wiw-key' ) ) {
			counter += 1;
			form.setAttribute( 'data-wiw-key', 'wiw-comment-form-' + counter );
		}
		return form.getAttribute( 'data-wiw-key' );
	}

	function statusLine( form ) {
		var key = formKey( form );
		var line = form.parentNode ? form.parentNode.querySelector( '.wiw-comment-status[data-for="' + key + '"]' ) : null;
		if ( ! line ) {
			line = document.createElement( 'p' );
			line.className = 'notice notice--info wiw-comment-status';
			line.setAttribute( 'role', 'status' );
			line.setAttribute( 'aria-live', 'polite' );
			line.setAttribute( 'tabindex', '-1' );
			line.setAttribute( 'data-for', key );
			line.hidden = true;
			form.parentNode.insertBefore( line, form.nextSibling );
		}
		return line;
	}

	function say( form, text, kind, focus ) {
		var line = statusLine( form );
		line.textContent = text;
		line.className = 'notice wiw-comment-status' + ( 'error' === kind ? ' wiw-comment-status--error' : ' notice--info' );
		line.setAttribute( 'role', 'error' === kind ? 'alert' : 'status' );
		line.hidden = false;
		if ( focus ) {
			line.focus( { preventScroll: false } );
		}
	}

	function setBusy( form, busy ) {
		Array.prototype.forEach.call( form.querySelectorAll( '.wc_comm_submit' ), function ( button ) {
			button.disabled = busy;
			button.setAttribute( 'aria-disabled', busy ? 'true' : 'false' );
		} );
	}

	function recentlySent( text ) {
		return !! text && !! sent[ text ] && Date.now() - sent[ text ] < repeatWindow;
	}

	// Runs before wpDiscuz's own click handler: stop a second send of the same comment.
	document.addEventListener(
		'click',
		function ( event ) {
			var button = event.target && event.target.closest ? event.target.closest( '.wc_comm_submit' ) : null;
			var form = button ? button.closest( 'form' ) : null;
			if ( ! form ) {
				return;
			}
			var text = commentText( form );
			if ( pending.has( form ) ) {
				event.preventDefault();
				event.stopImmediatePropagation();
				say( form, strings.sending, 'info', true );
			} else if ( recentlySent( text ) ) {
				event.preventDefault();
				event.stopImmediatePropagation();
				say( form, strings.duplicate, 'info', true );
			}
		},
		true
	);

	jq( document.body ).on( 'wpdiscuz_before_send_comment', function ( event, wcForm ) {
		var form = wcForm && wcForm[ 0 ];
		if ( ! form ) {
			return;
		}
		pending.set( form, commentText( form ) );
		setBusy( form, true );
		say( form, strings.sending, 'info', false );
	} );

	jq( document.body ).on( 'wpdiscuz_comment_post_success', function ( event, wcForm, data, button, response ) {
		var form = wcForm && wcForm[ 0 ];
		if ( ! form ) {
			return;
		}
		var text = pending.get( form );
		if ( text ) {
			sent[ text ] = Date.now();
		}
		pending.delete( form );
		var held = ! response || response.held_moderate;
		say( form, held ? strings.held : strings.published, 'info', true );
	} );

	jq( document.body ).on( 'wpdiscuz_comment_post_error', function ( event, wcForm, data, button, response ) {
		var form = wcForm && wcForm[ 0 ];
		if ( ! form ) {
			return;
		}
		pending.delete( form );
		var message = response && 'object' === typeof response && response.error ? String( response.error ) : strings.error;
		say( form, message, 'error', true );
	} );

	jq( document.body ).on( 'wpdiscuz_comment_post_failed', function ( event, wcForm ) {
		var form = wcForm && wcForm[ 0 ];
		if ( ! form ) {
			return;
		}
		pending.delete( form );
		setBusy( form, false );
		say( form, strings.failed, 'error', true );
	} );

	jq( document.body ).on( 'wpdiscuz_comment_post_complete', function ( event, wcForm ) {
		var form = wcForm && wcForm[ 0 ];
		if ( form ) {
			pending.delete( form );
			setBusy( form, false );
		}
	} );
}() );

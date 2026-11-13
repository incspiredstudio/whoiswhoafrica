/**
 * Setup screen: import and remove the demo content in small steps, with a progress bar.
 * Each request carries its own nonce; the server checks the capability. A failed request can be repeated.
 */
( function () {
	'use strict';

	var config = window.wiwSetup;
	var root = document.querySelector( '.wiw-demo' );
	if ( ! config || ! root ) {
		return;
	}

	var $ = function ( name ) {
		return root.querySelector( '[data-wiw="' + name + '"]' );
	};
	var els = {
		openImport: $( 'open-import' ),
		openRemove: $( 'open-remove' ),
		confirmImport: $( 'confirm-import' ),
		confirmRemove: $( 'confirm-remove' ),
		doImport: $( 'do-import' ),
		doRemove: $( 'do-remove' ),
		typed: $( 'typed' ),
		wrap: $( 'progress-wrap' ),
		bar: $( 'bar' ),
		fill: $( 'fill' ),
		label: $( 'label' ),
		error: $( 'error' ),
		retry: $( 'retry' ),
		visit: $( 'visit' ),
		reload: $( 'reload' )
	};
	var mode = 'import';

	function show( el, on ) {
		if ( el ) {
			el.hidden = ! on;
		}
	}

	function progress( percent, text ) {
		els.fill.style.width = percent + '%';
		els.bar.setAttribute( 'aria-valuenow', String( percent ) );
		els.label.textContent = text;
	}

	function fail( message ) {
		els.error.textContent = message;
		show( els.error, true );
		show( els.retry, true );
	}

	function post( name, extra ) {
		var body = new window.URLSearchParams();
		body.set( 'action', config.actions[ name ] );
		body.set( '_wpnonce', config.nonces[ name ] );
		Object.keys( extra || {} ).forEach( function ( key ) {
			body.set( key, extra[ key ] );
		} );
		return window.fetch( config.ajax, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		} ).then( function ( response ) {
			return response.json().then(
				function ( json ) {
					return json;
				},
				function () {
					throw new Error( 'not json' );
				}
			);
		} );
	}

	function busy( on ) {
		[ els.openImport, els.openRemove, els.doImport, els.doRemove ].forEach( function ( button ) {
			if ( button ) {
				button.disabled = on;
			}
		} );
	}

	function finished( text ) {
		progress( 100, text );
		show( els.retry, false );
		show( els.reload, true );
		if ( 'import' === mode ) {
			show( els.visit, true );
		}
		busy( false );
	}

	function stepImport() {
		post( 'step' ).then( function ( json ) {
			if ( ! json || ! json.success ) {
				throw new Error( 'refused' );
			}
			var data = json.data;
			if ( data.busy ) {
				window.setTimeout( stepImport, 1500 );
				return;
			}
			progress( data.percent, data.label );
			if ( 'failed' === data.status ) {
				fail( data.error || config.strings.failed );
				busy( false );
				return;
			}
			if ( 'done' === data.status ) {
				var note = config.strings.finished;
				if ( ! data.images ) {
					note += ' ' + config.strings.no_images;
				}
				finished( note );
				return;
			}
			stepImport();
		} ).catch( function () {
			fail( config.strings.network );
			busy( false );
		} );
	}

	function startImport() {
		mode = 'import';
		show( els.error, false );
		show( els.retry, false );
		show( els.visit, false );
		show( els.reload, false );
		show( els.wrap, true );
		busy( true );
		progress( 0, config.strings.working );
		var typed = els.typed ? els.typed.value : '';
		post( 'start', { typed: typed } ).then( function ( json ) {
			if ( ! json || ! json.success ) {
				throw new Error( 'refused' );
			}
			if ( json.data.error && 'running' !== json.data.status ) {
				fail( json.data.error );
				busy( false );
				return;
			}
			stepImport();
		} ).catch( function () {
			fail( config.strings.network );
			busy( false );
		} );
	}

	function stepRemove() {
		post( 'remove' ).then( function ( json ) {
			if ( ! json || ! json.success ) {
				throw new Error( 'refused' );
			}
			var data = json.data;
			if ( data.busy ) {
				window.setTimeout( stepRemove, 1500 );
				return;
			}
			if ( data.error ) {
				fail( data.error );
				busy( false );
				return;
			}
			if ( data.done ) {
				finished( config.strings.removed );
				return;
			}
			progress( 50, config.strings.removing + ' (' + data.left + ')' );
			stepRemove();
		} ).catch( function () {
			fail( config.strings.network );
			busy( false );
		} );
	}

	function startRemove() {
		mode = 'remove';
		show( els.error, false );
		show( els.retry, false );
		show( els.visit, false );
		show( els.reload, false );
		show( els.wrap, true );
		busy( true );
		progress( 10, config.strings.removing );
		stepRemove();
	}

	function closeConfirms() {
		show( els.confirmImport, false );
		show( els.confirmRemove, false );
	}

	if ( els.openImport ) {
		els.openImport.addEventListener( 'click', function () {
			closeConfirms();
			show( els.confirmImport, true );
			var first = els.confirmImport.querySelector( 'input, button' );
			if ( first ) {
				first.focus();
			}
		} );
	}
	if ( els.openRemove ) {
		els.openRemove.addEventListener( 'click', function () {
			closeConfirms();
			show( els.confirmRemove, true );
		} );
	}
	root.querySelectorAll( '[data-wiw="cancel"]' ).forEach( function ( button ) {
		button.addEventListener( 'click', closeConfirms );
	} );
	if ( els.doImport ) {
		els.doImport.addEventListener( 'click', function () {
			if ( els.typed && '' === els.typed.value.trim() ) {
				window.alert( config.strings.type_needed );
				els.typed.focus();
				return;
			}
			closeConfirms();
			startImport();
		} );
	}
	if ( els.doRemove ) {
		els.doRemove.addEventListener( 'click', function () {
			closeConfirms();
			startRemove();
		} );
	}
	if ( els.retry ) {
		els.retry.addEventListener( 'click', function () {
			show( els.error, false );
			show( els.retry, false );
			busy( true );
			if ( 'import' === mode ) {
				startImport();
			} else {
				startRemove();
			}
		} );
	}
	if ( els.reload ) {
		els.reload.addEventListener( 'click', function () {
			window.location.reload();
		} );
	}
	if ( config.resume ) {
		// A run that stopped halfway: offer to continue it.
		mode = 'import';
		show( els.wrap, true );
		progress( 0, config.strings.resume );
		show( els.retry, true );
	}
}() );

/**
 * Import screen: upload a bundle in small pieces (so the host's PHP upload limit does not matter), then step the dry run, the
 * import or the undo on with short requests and draw the progress. Each request carries its own nonce; the server checks the
 * capability. A dropped request can be repeated with the Continue button: the server resumes where it stopped.
 */
( function () {
	'use strict';

	var config = window.wiwImport;
	if ( ! config ) {
		return;
	}

	function format( text, values ) {
		var i = 0;
		return text.replace( /%(\d\$)?[ds]|%%/g, function ( token ) {
			if ( '%%' === token ) {
				return '%';
			}
			var match = /%(\d)\$/.exec( token );
			var value = match ? values[ Number( match[ 1 ] ) - 1 ] : values[ i++ ];
			return String( value );
		} );
	}

	function post( action, body ) {
		body.append( 'action', config.actions[ action ] );
		body.append( '_wpnonce', config.nonces[ action ] );
		return window.fetch( config.ajax, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		} ).then( function ( response ) {
			return response.json().then(
				function ( json ) {
					if ( ! json || ! json.success ) {
						throw new Error( json && json.data && json.data.message ? json.data.message : config.text.failed );
					}
					return json.data;
				},
				function () {
					throw new Error( config.text.failed );
				}
			);
		} );
	}

	// Upload in pieces.
	var form = document.querySelector( 'form[data-wiw-import="upload"]' );
	if ( form && window.FormData && window.Blob && Blob.prototype.slice ) {
		var input = form.querySelector( 'input[type="file"]' );
		var status = form.querySelector( '[data-wiw-import="upload-progress"]' );
		var button = form.querySelector( 'button' );
		form.addEventListener( 'submit', function ( event ) {
			var file = input && input.files && input.files[ 0 ];
			if ( ! file ) {
				return;
			}
			event.preventDefault();
			button.disabled = true;
			var size = Number( config.pieceSize ) || 1048576;
			var uploadId = '';
			var send = function ( offset ) {
				var body = new window.FormData();
				body.append( 'upload_id', uploadId );
				body.append( 'offset', String( offset ) );
				body.append( 'total', String( file.size ) );
				body.append( 'name', file.name );
				body.append( 'piece', file.slice( offset, offset + size ), file.name );
				return post( 'piece', body ).then( function ( data ) {
					uploadId = data.upload_id;
					status.textContent = format( config.text.uploading, [ Math.floor( ( 100 * data.received ) / file.size ) ] );
					if ( data.redirect ) {
						window.location.assign( data.redirect );
						return null;
					}
					return send( data.received );
				} );
			};
			send( 0 ).catch( function ( error ) {
				status.textContent = error.message;
				button.disabled = false;
			} );
		} );
	}

	// Step a batch on.
	var box = document.querySelector( '[data-wiw-import="progress"]' );
	if ( ! box ) {
		return;
	}
	var bar = box.querySelector( '[data-wiw-import="bar"]' );
	var text = box.querySelector( '[data-wiw-import="text"]' );
	var again = box.querySelector( '[data-wiw-import="continue"]' );
	var batch = box.getAttribute( 'data-batch' );
	var running = false;

	function draw( data ) {
		var percent = data.total > 0 ? Math.floor( ( 100 * Math.min( data.cursor, data.total ) ) / data.total ) : 100;
		bar.value = percent;
		bar.textContent = percent + '%';
		if ( data.busy ) {
			text.textContent = config.text.busy;
		} else if ( 'dry_run' === data.phase ) {
			text.textContent = format( config.text.checking, [ data.cursor, data.total ] );
		} else if ( 'apply' === data.phase ) {
			text.textContent = format( config.text.importing, [ data.cursor, data.total ] );
		}
	}

	function step() {
		running = true;
		if ( again ) {
			again.disabled = true;
		}
		var body = new window.FormData();
		body.append( 'batch', batch );
		post( 'step', body ).then(
			function ( data ) {
				draw( data );
				if ( data.done ) {
					var url = new window.URL( config.screen, window.location.href );
					url.searchParams.set( 'batch', batch );
					window.location.assign( url.toString() );
					return;
				}
				window.setTimeout( step, data.busy ? 5000 : 200 );
			},
			function ( error ) {
				running = false;
				text.textContent = error.message;
				if ( again ) {
					again.disabled = false;
				}
			}
		);
	}

	if ( again ) {
		again.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			if ( ! running ) {
				step();
			}
		} );
	}
	step();
}() );

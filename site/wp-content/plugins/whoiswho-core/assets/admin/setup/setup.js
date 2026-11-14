/**
 * Setup screen: choose the parts of the demo content, import them in small steps with a progress bar, and remove one part or all
 * of it. Ticking a part ticks what it needs (with a note saying why); unticking a part unticks what needs it.
 * Each request carries its own nonce; the server checks the capability and works out the needed parts again. A failed request can be repeated.
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
		confirmPart: $( 'confirm-part' ),
		partTitle: $( 'part-title' ),
		partText: $( 'part-text' ),
		chosen: $( 'chosen' ),
		autoNote: $( 'auto-note' ),
		doImport: $( 'do-import' ),
		doRemove: $( 'do-remove' ),
		doRemovePart: $( 'do-remove-part' ),
		typed: $( 'typed' ),
		wrap: $( 'progress-wrap' ),
		bar: $( 'bar' ),
		fill: $( 'fill' ),
		label: $( 'label' ),
		error: $( 'error' ),
		retry: $( 'retry' ),
		visit: $( 'visit' ),
		reload: $( 'reload' ),
		kept: $( 'kept' ),
		keptList: $( 'kept-list' )
	};
	var parts = config.parts || { labels: {}, needs: {}, reasons: {}, dependents: {} };
	var boxes = Array.prototype.slice.call( root.querySelectorAll( '[data-wiw-part]' ) );
	var mode = 'import';
	var removing = '';

	function show( el, on ) {
		if ( el ) {
			el.hidden = ! on;
		}
	}

	function format( text ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var index = 0;
		return text.replace( /%(\d+\$)?s/g, function ( match, position ) {
			var value = position ? args[ parseInt( position, 10 ) - 1 ] : args[ index++ ];
			return undefined === value ? '' : String( value );
		} );
	}

	function box( part ) {
		return boxes.filter( function ( input ) {
			return input.value === part;
		} )[ 0 ];
	}

	function label( part ) {
		return parts.labels[ part ] || part;
	}

	function chosen() {
		return boxes.filter( function ( input ) {
			return input.checked;
		} ).map( function ( input ) {
			return input.value;
		} );
	}

	function note( text ) {
		if ( els.autoNote ) {
			els.autoNote.textContent = text;
		}
	}

	// Tick what a part needs (and what those need), saying why.
	function tickNeeds( part, notes ) {
		( parts.needs[ part ] || [] ).forEach( function ( need ) {
			var input = box( need );
			if ( input && ! input.checked ) {
				input.checked = true;
				notes.push( format( config.strings.auto_ticked, label( need ), parts.reasons[ part ] || '' ) );
				tickNeeds( need, notes );
			}
		} );
	}

	boxes.forEach( function ( input ) {
		input.addEventListener( 'change', function () {
			var notes = [];
			if ( input.checked ) {
				tickNeeds( input.value, notes );
			} else {
				var cleared = ( parts.dependents[ input.value ] || [] ).filter( function ( other ) {
					var dependent = box( other );
					if ( dependent && dependent.checked ) {
						dependent.checked = false;
						return true;
					}
					return false;
				} );
				if ( cleared.length ) {
					notes.push( format( config.strings.auto_cleared, cleared.map( label ).join( ', ' ), label( input.value ) ) );
				}
			}
			note( notes.join( ' ' ) );
		} );
	} );

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
		[ els.openImport, els.openRemove, els.doImport, els.doRemove, els.doRemovePart ].concat(
			Array.prototype.slice.call( root.querySelectorAll( '[data-wiw="remove-part"]' ) ),
			boxes
		).forEach( function ( control ) {
			if ( control ) {
				control.disabled = on;
			}
		} );
	}

	function addKept( items ) {
		( items || [] ).forEach( function ( item ) {
			var li = document.createElement( 'li' );
			if ( item.url ) {
				var link = document.createElement( 'a' );
				link.href = item.url;
				link.textContent = item.title;
				li.appendChild( link );
			} else {
				li.textContent = item.title;
			}
			els.keptList.appendChild( li );
		} );
		show( els.kept, els.keptList.children.length > 0 );
	}

	// Show only the confirmation lines of the ticked parts. A line tagged with several parts shows when any of them is ticked.
	function filterLines( list ) {
		Array.prototype.slice.call( els.confirmImport.querySelectorAll( '[data-wiw-for]' ) ).forEach( function ( line ) {
			var forParts = line.getAttribute( 'data-wiw-for' ).split( ',' );
			show( line, forParts.some( function ( part ) {
				return -1 !== list.indexOf( part );
			} ) );
		} );
	}

	// After a removal, take the "Imported N" line and the Remove button off the removed parts at once (no reload needed).
	function clearPartStatus( list ) {
		list.forEach( function ( part ) {
			var item = root.querySelector( '.wiw-part[data-part="' + part + '"]' );
			if ( ! item ) {
				return;
			}
			Array.prototype.slice.call( item.querySelectorAll( '[data-wiw="part-status"], [data-wiw="remove-part"]' ) ).forEach( function ( el ) {
				el.parentNode.removeChild( el );
			} );
		} );
		if ( ! root.querySelector( '[data-wiw="remove-part"]' ) ) {
			show( els.openRemove, false );
		}
	}

	function finished( text ) {
		if ( 'remove' === mode ) {
			clearPartStatus( removing ? [ removing ].concat( parts.dependents[ removing ] || [] ) : boxes.map( function ( input ) {
				return input.value;
			} ) );
		}
		progress( 100, text );
		show( els.retry, false );
		show( els.reload, true );
		if ( 'import' === mode ) {
			show( els.visit, true );
		}
		busy( false );
	}

	function reset() {
		show( els.error, false );
		show( els.retry, false );
		show( els.visit, false );
		show( els.reload, false );
		show( els.wrap, true );
		busy( true );
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
				var text = config.strings.finished;
				if ( ! data.images ) {
					text += ' ' + config.strings.no_images;
				}
				finished( text );
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
		reset();
		progress( 0, config.strings.working );
		// A run that stopped halfway resumes on the server whatever is ticked now.
		post( 'start', { typed: els.typed ? els.typed.value : '', parts: chosen().join( ',' ) } ).then( function ( json ) {
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
		var name = removing ? 'part' : 'remove';
		post( name, removing ? { part: removing } : {} ).then( function ( json ) {
			if ( ! json || ! json.success ) {
				throw new Error( 'refused' );
			}
			var data = json.data;
			if ( data.busy ) {
				window.setTimeout( stepRemove, 1500 );
				return;
			}
			addKept( data.kept );
			if ( data.error ) {
				fail( data.error );
				busy( false );
				return;
			}
			if ( data.done ) {
				finished( removing ? config.strings.part_removed : config.strings.removed );
				return;
			}
			progress( 50, config.strings.removing + ' (' + data.left + ')' );
			stepRemove();
		} ).catch( function () {
			fail( config.strings.network );
			busy( false );
		} );
	}

	function startRemove( part ) {
		mode = 'remove';
		removing = part || '';
		reset();
		els.keptList.textContent = '';
		show( els.kept, false );
		progress( 10, config.strings.removing );
		stepRemove();
	}

	function closeConfirms() {
		show( els.confirmImport, false );
		show( els.confirmRemove, false );
		show( els.confirmPart, false );
	}

	if ( els.openImport ) {
		els.openImport.addEventListener( 'click', function () {
			var list = chosen();
			closeConfirms();
			if ( ! list.length ) {
				note( config.strings.none_chosen );
				if ( boxes[ 0 ] ) {
					boxes[ 0 ].focus();
				}
				return;
			}
			if ( els.chosen ) {
				els.chosen.textContent = list.map( label ).join( ', ' );
			}
			filterLines( list );
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
	root.querySelectorAll( '[data-wiw="remove-part"]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			var part = button.getAttribute( 'data-part' );
			var also = ( parts.dependents[ part ] || [] ).filter( function ( other ) {
				return !! root.querySelector( '[data-wiw="remove-part"][data-part="' + other + '"]' );
			} );
			closeConfirms();
			removing = part;
			els.partTitle.textContent = format( config.strings.remove_title, label( part ) );
			els.partText.textContent = config.strings.remove_text + ( also.length ? ' ' + format( config.strings.remove_also, also.map( label ).join( ', ' ) ) : '' );
			show( els.confirmPart, true );
			els.doRemovePart.focus();
		} );
	} );
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
			startRemove( '' );
		} );
	}
	if ( els.doRemovePart ) {
		els.doRemovePart.addEventListener( 'click', function () {
			closeConfirms();
			startRemove( removing );
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
				startRemove( removing );
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

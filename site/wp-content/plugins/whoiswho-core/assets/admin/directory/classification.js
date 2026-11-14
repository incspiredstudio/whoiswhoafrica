/**
 * Edit profile and Add profile classification controls (REQ-009, REQ-125, REQ-209).
 *
 * - A search box with data-wiw-filter-for="<select id>" is shown and hides the options of that select that do not contain the
 *   typed text (a group with no match is hidden too).
 * - A select with data-wiw-chip-add="<name>" turns the chosen industry into a removable chip in the list with
 *   data-wiw-chips="<name>", then resets itself. The hint with data-wiw-chips-hint="<name>" follows: data-hint-none while no chip
 *   is ticked, data-hint-some once one is.
 * - A select with data-wiw-city-for="<country select id>" offers only the cities of the chosen country.
 *
 * Without JavaScript the selects still work: the add select is sent with the form and the server keeps every industry chosen.
 */
( function () {
	'use strict';

	function matches( option, text ) {
		return '' === text || '0' === option.value || option.textContent.toLowerCase().indexOf( text ) !== -1;
	}

	function filter( input ) {
		var select = document.getElementById( input.getAttribute( 'data-wiw-filter-for' ) );
		if ( ! select ) {
			return;
		}
		var text = input.value.trim().toLowerCase();
		Array.prototype.forEach.call( select.options, function ( option ) {
			option.hidden = ! matches( option, text );
		} );
		Array.prototype.forEach.call( select.querySelectorAll( 'optgroup' ), function ( group ) {
			group.hidden = ! Array.prototype.some.call( group.querySelectorAll( 'option' ), function ( option ) {
				return ! option.hidden;
			} );
		} );
	}

	function updateHint( name ) {
		var hint = document.querySelector( '[data-wiw-chips-hint="' + name + '"]' );
		var list = document.querySelector( '[data-wiw-chips="' + name + '"]' );
		if ( ! hint || ! list ) {
			return;
		}
		var some = null !== list.querySelector( 'input[type="checkbox"]:checked' );
		hint.textContent = hint.getAttribute( some ? 'data-hint-some' : 'data-hint-none' ) || hint.textContent;
	}

	function addChip( select ) {
		var name = select.getAttribute( 'data-wiw-chip-add' );
		var list = document.querySelector( '[data-wiw-chips="' + name + '"]' );
		var option = select.options[ select.selectedIndex ];
		if ( ! list || ! option || '0' === option.value ) {
			return;
		}
		var existing = list.querySelector( 'input[value="' + option.value + '"]' );
		if ( existing ) {
			existing.checked = true;
		} else {
			var group = option.parentNode && option.parentNode.label ? ' (' + option.parentNode.label + ')' : '';
			var item = document.createElement( 'li' );
			var label = document.createElement( 'label' );
			var box = document.createElement( 'input' );
			label.className = 'wiw-chip';
			box.type = 'checkbox';
			box.name = name + '[]';
			box.value = option.value;
			box.checked = true;
			label.appendChild( box );
			label.appendChild( document.createTextNode( ' ' + option.textContent + group ) );
			item.appendChild( label );
			list.appendChild( item );
		}
		select.value = '0';
		updateHint( name );
	}

	function narrowCities( select ) {
		var country = document.getElementById( select.getAttribute( 'data-wiw-city-for' ) );
		if ( ! country ) {
			return;
		}
		var chosen = country.value;
		Array.prototype.forEach.call( select.querySelectorAll( 'optgroup' ), function ( group ) {
			var mine = group.getAttribute( 'data-wiw-country' ) === chosen;
			group.hidden = ! mine;
			group.disabled = ! mine;
		} );
		var current = select.options[ select.selectedIndex ];
		if ( current && current.parentNode && current.parentNode.disabled ) {
			select.value = '0';
		}
	}

	document.querySelectorAll( '[data-wiw-filter-for]' ).forEach( function ( input ) {
		input.hidden = false;
		input.addEventListener( 'input', function () {
			filter( input );
		} );
	} );

	document.querySelectorAll( '[data-wiw-chip-add]' ).forEach( function ( select ) {
		select.addEventListener( 'change', function () {
			addChip( select );
		} );
	} );

	document.querySelectorAll( '[data-wiw-chips]' ).forEach( function ( list ) {
		var name = list.getAttribute( 'data-wiw-chips' );
		list.addEventListener( 'change', function () {
			updateHint( name );
		} );
	} );

	document.querySelectorAll( '[data-wiw-city-for]' ).forEach( function ( select ) {
		var country = document.getElementById( select.getAttribute( 'data-wiw-city-for' ) );
		if ( country ) {
			country.addEventListener( 'change', function () {
				narrowCities( select );
			} );
		}
		narrowCities( select );
	} );
}() );

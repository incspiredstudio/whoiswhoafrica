// Editor side of the dynamic blocks (REQ-260): each block previews the server render and has nothing to type.
( function ( blocks, element, serverSideRender ) {
	var el = element.createElement;
	[ 'wiw/figures', 'wiw/awards-stage-button', 'wiw/awards-categories', 'wiw/edition' ].forEach( function ( name ) {
		blocks.registerBlockType( name, {
			edit: function () {
				return el( serverSideRender, { block: name } );
			},
			save: function () {
				return null;
			},
		} );
	} );
}( window.wp.blocks, window.wp.element, window.wp.serverSideRender ) );

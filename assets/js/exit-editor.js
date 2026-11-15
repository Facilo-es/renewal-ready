/* Renewal Ready: editor view of the "How to end your subscription" block. */
( function ( wp ) {
	'use strict';
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var be = wp.blockEditor;
	wp.blocks.registerBlockType( 'renewal-ready/exit', {
		edit: function () {
			return el( 'div', be.useBlockProps( { className: 'rr-editor-note' } ),
				el( 'strong', null, __( 'How to end your subscription', 'renewal-ready' ) ),
				el( 'p', null, __( 'Renewal Ready shows the steps to end a subscription online, with your contact email.', 'renewal-ready' ) )
			);
		},
		save: function () {
			return null;
		}
	} );
}( window.wp ) );

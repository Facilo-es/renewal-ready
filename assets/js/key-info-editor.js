/* Renewal Ready: editor view of the key-information inner block. */
( function ( wp ) {
	'use strict';
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var be = wp.blockEditor;
	wp.blocks.registerBlockType( 'renewal-ready/key-info', {
		edit: function () {
			return el( 'div', be.useBlockProps( { className: 'rr-editor-note' } ),
				el( 'strong', null, __( 'Subscription: key information', 'renewal-ready' ) ),
				el( 'p', null, __( 'Renewal Ready shows the price, renewal dates, reminders and how to end the subscription here, with the payment tick, when the cart holds a subscription.', 'renewal-ready' ) )
			);
		},
		save: function () {
			return el( 'div', be.useBlockProps.save() );
		}
	} );
}( window.wp ) );

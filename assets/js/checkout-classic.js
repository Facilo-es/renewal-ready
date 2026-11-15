/* Renewal Ready: show the tick error on the classic checkout. */
( function ( $ ) {
	'use strict';
	function mark( bad ) {
		var $tick = $( '.rr-tick' );
		$tick.toggleClass( 'bad', bad );
		$( '#rr-consent-err' ).prop( 'hidden', ! bad );
	}
	$( document.body ).on( 'checkout_error', function () {
		var $cb = $( '#rr-consent' );
		if ( $cb.length && ! $cb.is( ':checked' ) ) {
			mark( true );
		}
	} );
	$( document.body ).on( 'change', '#rr-consent', function () {
		if ( this.checked ) {
			mark( false );
		}
	} );
}( jQuery ) );

/* Renewal Ready admin: readiness ring, "ready" list toggle, tick warning, email picker. No outside requests. */
( function () {
	'use strict';
	document.querySelectorAll( '.a-ring[data-dash]' ).forEach( function ( ring ) {
		var fg = ring.querySelector( '.a-ring-fg' );
		if ( fg ) {
			window.requestAnimationFrame( function () {
				fg.style.strokeDasharray = ring.getAttribute( 'data-dash' ) + ' 200';
			} );
		}
	} );
	document.querySelectorAll( '.a-more' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var open = btn.getAttribute( 'aria-expanded' ) === 'true';
			var list = document.getElementById( btn.getAttribute( 'aria-controls' ) );
			btn.setAttribute( 'aria-expanded', open ? 'false' : 'true' );
			if ( list ) {
				list.hidden = open;
			}
		} );
	} );
	var tick = document.getElementById( 'rr-tick' );
	var warn = document.querySelector( '.rr-tick-warn' );
	if ( tick && warn ) {
		tick.addEventListener( 'change', function () {
			warn.hidden = tick.checked;
		} );
	}
	document.querySelectorAll( '.rr-autosubmit' ).forEach( function ( sel ) {
		sel.addEventListener( 'change', function () {
			sel.form.submit();
		} );
	} );
}() );

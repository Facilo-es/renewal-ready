/* Renewal Ready: key information and payment tick on the block checkout. */
( function () {
	'use strict';
	if ( ! window.wc || ! window.wc.blocksCheckout || ! window.wp || ! window.wp.element ) {
		return;
	}
	var el = wp.element.createElement;
	var useEffect = wp.element.useEffect;
	var useState = wp.element.useState;
	var useSelect = wp.data.useSelect;
	var bc = window.wc.blocksCheckout;
	var NS = 'renewal-ready';
	var ERR = 'renewal-ready-consent';
	var VALID = 'wc/store/validation';

	function cartData( select ) {
		var cart = ( select || wp.data.select )( 'wc/store/cart' ).getCartData();
		return ( cart && cart.extensions && cart.extensions[ NS ] ) || {};
	}

	function setConsent( value ) {
		var co = wp.data.dispatch( 'wc/store/checkout' );
		if ( co && co.setExtensionData ) {
			co.setExtensionData( NS, { consent: !! value } );
		}
	}

	function KeyInfo() {
		var data = useSelect( function ( select ) {
			return cartData( select );
		}, [] );
		var state = useState( false );
		var ticked = state[ 0 ];
		var setTicked = state[ 1 ];
		var error = useSelect( function ( select ) {
			var v = select( VALID );
			return v && v.getValidationError ? v.getValidationError( ERR ) : null;
		}, [] );
		var needs = !! ( data.has_sub && data.tick );

		useEffect( function () {
			var v = wp.data.dispatch( VALID );
			setConsent( ticked );
			if ( needs && ! ticked ) {
				var e = {};
				e[ ERR ] = { message: data.error, hidden: true };
				v.setValidationErrors( e );
			} else {
				v.clearValidationError( ERR );
			}
			return function () {
				v.clearValidationError( ERR );
			};
		}, [ needs, ticked, data.error ] );

		if ( ! data.has_sub ) {
			return null;
		}
		var shown = !! ( error && ! error.hidden );
		var parts = [ el( 'div', { key: 'box', dangerouslySetInnerHTML: { __html: data.box } } ) ];
		if ( data.tick ) {
			parts.push(
				el( 'label', { key: 'tick', className: 'rr-tick' + ( shown ? ' bad' : '' ), htmlFor: 'rr-consent' },
					el( 'input', {
						type: 'checkbox',
						id: 'rr-consent',
						checked: ticked,
						'aria-describedby': shown ? 'rr-consent-err' : undefined,
						onChange: function ( ev ) {
							setTicked( ev.target.checked );
						}
					} ),
					el( 'span', null, data.tick_text )
				)
			);
			if ( shown ) {
				parts.push( el( 'p', { key: 'err', className: 'rr-err', id: 'rr-consent-err', role: 'alert' }, error.message ) );
			}
		}
		return el( 'div', { className: 'rr-checkout' }, parts );
	}

	bc.registerCheckoutBlock( {
		metadata: {
			name: 'renewal-ready/key-info',
			parent: [ 'woocommerce/checkout-fields-block' ],
			attributes: { lock: { type: 'object', default: { remove: true, move: false } } }
		},
		component: KeyInfo
	} );

	var register = bc.registerCheckoutFilters || bc.__experimentalRegisterCheckoutFilters;
	function subLabel() {
		var d = cartData();
		return d.has_sub && d.button ? d.button : '';
	}
	function registerLabel() {
		register( NS, {
			placeOrderButtonLabel: function ( value ) {
				return subLabel() || value;
			}
		} );
	}
	if ( register ) {
		// WooCommerce caches filter results; registering again clears the cache when the cart changes.
		var shown = subLabel();
		registerLabel();
		wp.data.subscribe( function () {
			var now = subLabel();
			if ( now !== shown ) {
				shown = now;
				registerLabel();
			}
		}, 'wc/store/cart' );
	}
}() );

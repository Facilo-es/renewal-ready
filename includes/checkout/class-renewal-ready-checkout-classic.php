<?php
/**
 * Classic (shortcode) checkout.
 *
 * @package RenewalReady
 */

defined( 'ABSPATH' ) || exit;

/**
 * Box and tick above the place-order button, refused server-side without the tick.
 */
class Renewal_Ready_Checkout_Classic {

	const FIELD = 'renewal_ready_consent';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_review_order_before_submit', array( __CLASS__, 'render' ) );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'save' ), 10, 2 );
		add_filter( 'woocommerce_order_button_text', array( __CLASS__, 'label' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Assets on the classic checkout page.
	 */
	public static function assets() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}
		Renewal_Ready_Checkout::enqueue_style();
		wp_enqueue_script( 'renewal-ready-checkout-classic', Renewal_Ready_Plugin::asset( 'js/checkout-classic.js' ), array( 'jquery' ), RENEWAL_READY_VERSION, true );
	}

	/**
	 * Print the box and the tick.
	 */
	public static function render() {
		$list = Renewal_Ready_Checkout::cart_terms();
		if ( ! $list ) {
			return;
		}
		$html = Renewal_Ready_Checkout::box_html( $list );
		if ( Renewal_Ready_Checkout::tick_on() ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce checks the checkout nonce; this only keeps the tick after a refresh.
			$checked = ! empty( $_POST['post_data'] ) && false !== strpos( sanitize_text_field( wp_unslash( $_POST['post_data'] ) ), self::FIELD . '=1' );
			$html   .= '<label class="rr-tick" for="rr-consent"><input type="checkbox" name="' . esc_attr( self::FIELD ) . '" id="rr-consent" value="1"' . ( $checked ? ' checked="checked"' : '' ) . '><span>' . esc_html( Renewal_Ready_Checkout::tick_text( $list ) ) . '</span></label>';
			$html   .= '<p class="rr-err" id="rr-consent-err" role="alert" hidden>' . esc_html( self::error_text() ) . '</p>';
		}
		echo wp_kses( $html, Renewal_Ready_Checkout::box_allowed() );
	}

	/**
	 * Tick error text.
	 *
	 * @return string
	 */
	public static function error_text() {
		return __( 'Please tick the box to confirm you understand this is a paid subscription.', 'renewal-ready' );
	}

	/**
	 * Refuse the order without the tick.
	 *
	 * @param array    $data   Posted data.
	 * @param WP_Error $errors Errors.
	 */
	public static function validate( $data, $errors ) {
		if ( ! Renewal_Ready_Checkout::tick_on() || ! Renewal_Ready_Checkout::cart_terms() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the checkout nonce before this hook.
		if ( empty( $_POST[ self::FIELD ] ) ) {
			$errors->add( 'renewal_ready_consent', self::error_text(), array( 'id' => 'rr-consent' ) );
		}
	}

	/**
	 * Store the consent record.
	 *
	 * @param WC_Order $order Order.
	 * @param array    $data  Posted data.
	 */
	public static function save( $order, $data ) {
		unset( $data );
		$list = Renewal_Ready_Checkout::cart_terms();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the checkout nonce before this hook.
		Renewal_Ready_Checkout::record( $order, $list, ! empty( $_POST[ self::FIELD ] ), 'classic' );
	}

	/**
	 * Place-order label.
	 *
	 * @param string $text Label.
	 * @return string
	 */
	public static function label( $text ) {
		if ( ! Renewal_Ready_Checkout::cart_terms() ) {
			return $text;
		}
		$label = Renewal_Ready_Checkout::button_label();
		return '' !== $label ? $label : $text;
	}
}

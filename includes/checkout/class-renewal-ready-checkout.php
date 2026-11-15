<?php
/**
 * Checkout parts shared by the classic and block checkouts.
 *
 * @package RenewalReady
 */

defined( 'ABSPATH' ) || exit;

/**
 * Key-information box, payment tick, consent record and button label.
 */
class Renewal_Ready_Checkout {

	const META = '_rr_consent';

	/**
	 * Hooks.
	 */
	public static function init() {
		Renewal_Ready_Checkout_Classic::init();
		Renewal_Ready_Checkout_Blocks::init();
		add_action( 'woocommerce_admin_order_data_after_order_details', array( __CLASS__, 'admin_order' ) );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'product_box' ), 25 );
	}

	/**
	 * Terms for each subscription product in the cart.
	 *
	 * @return array
	 */
	public static function cart_terms() {
		$out = array();
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $out;
		}
		foreach ( WC()->cart->get_cart() as $item ) {
			$product = isset( $item['data'] ) ? $item['data'] : null;
			$terms   = Renewal_Ready_Terms::for_product( $product );
			if ( is_array( $terms ) ) {
				$out[] = $terms;
			}
		}
		return $out;
	}

	/**
	 * Terms for each subscription product in an order.
	 *
	 * @param WC_Order $order Order.
	 * @return array
	 */
	public static function order_terms( $order ) {
		$out = array();
		foreach ( $order->get_items() as $item ) {
			$product = is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : null;
			$terms   = Renewal_Ready_Terms::for_product( $product );
			if ( is_array( $terms ) ) {
				$out[] = $terms;
			}
		}
		return $out;
	}

	/**
	 * Is the payment tick switched on?
	 *
	 * @return bool
	 */
	public static function tick_on() {
		return 'yes' === Renewal_Ready_Plugin::get( 'tick' );
	}

	/**
	 * Tick text for a list of terms.
	 *
	 * @param array $items Terms list.
	 * @return string
	 */
	public static function tick_text( array $items ) {
		if ( ! $items ) {
			return '';
		}
		if ( count( $items ) > 1 ) {
			return __( 'I understand these are subscriptions and I agree to pay the amounts shown above, each time they renew, until I end them.', 'renewal-ready' );
		}
		return Renewal_Ready_Terms::tick_text( $items[0] );
	}

	/**
	 * Place-order label for the cart, or '' to keep the theme's.
	 *
	 * @param float|null $total Total due today.
	 * @return string
	 */
	public static function button_label( $total = null ) {
		if ( 'yes' !== Renewal_Ready_Plugin::get( 'button_label' ) ) {
			return '';
		}
		if ( null === $total ) {
			$total = WC()->cart ? (float) WC()->cart->get_total( 'edit' ) : 1.0;
		}
		return $total > 0 ? __( 'Subscribe and pay', 'renewal-ready' ) : __( 'Start subscription', 'renewal-ready' );
	}

	/**
	 * Icons for the six key-information lines.
	 *
	 * @return array
	 */
	public static function icons() {
		return array(
			array( '', '&#8635;' ),
			array( '', esc_html( html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) ) ),
			array( '', '&#8211;' ),
			array( ' rr-bell', '' ),
			array( ' rr-door', '' ),
			array( ' rr-cool', '' ),
		);
	}

	/**
	 * Key-information lines for a terms list (one group per product).
	 *
	 * @param array $items Terms list.
	 * @return array Groups of HTML lines.
	 */
	public static function groups( array $items ) {
		$out = array();
		foreach ( $items as $t ) {
			$out[] = array_map(
				function ( $l ) {
					return wp_kses( $l, Renewal_Ready_Terms::allowed() );
				},
				Renewal_Ready_Terms::lines( $t )
			);
		}
		return $out;
	}

	/**
	 * Box HTML (escaped).
	 *
	 * @param array $items Terms list.
	 * @return string
	 */
	public static function box_html( array $items ) {
		if ( ! $items ) {
			return '';
		}
		$icons = self::icons();
		$html  = '<section class="rr-box" aria-labelledby="rr-key-info-h"><h2 id="rr-key-info-h">' . esc_html__( 'Subscription: key information', 'renewal-ready' ) . '</h2>';
		foreach ( self::groups( $items ) as $lines ) {
			$html .= '<ul>';
			foreach ( $lines as $i => $line ) {
				$icon  = isset( $icons[ $i ] ) ? $icons[ $i ] : array( '', '&#8226;' );
				$html .= '<li><i class="rr-i' . esc_attr( $icon[0] ) . '" aria-hidden="true">' . $icon[1] . '</i><span>' . $line . '</span></li>';
			}
			$html .= '</ul>';
		}
		return $html . '</section>';
	}

	/**
	 * Plain text of the box, for the consent record.
	 *
	 * @param array $items Terms list.
	 * @return array
	 */
	public static function plain_lines( array $items ) {
		$out = array();
		foreach ( self::groups( $items ) as $lines ) {
			foreach ( $lines as $l ) {
				$out[] = trim( html_entity_decode( wp_strip_all_tags( $l ), ENT_QUOTES, 'UTF-8' ) );
			}
		}
		return $out;
	}

	/**
	 * Write the consent record on an order.
	 *
	 * @param WC_Order $order   Order.
	 * @param array    $items    Terms list.
	 * @param bool     $ticked  Whether the customer ticked.
	 * @param string   $channel classic or blocks.
	 */
	public static function record( $order, array $items, $ticked, $channel ) {
		if ( ! $items ) {
			return;
		}
		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$record = array(
			'text'     => self::tick_on() ? self::tick_text( $items ) : '',
			'ticked'   => (bool) $ticked,
			'lines'    => self::plain_lines( $items ),
			'button'   => self::button_label( (float) $order->get_total( 'edit' ) ),
			'time'     => gmdate( 'c' ),
			'order_id' => (int) $order->get_id(),
			'channel'  => $channel,
			'ip_hash'  => Renewal_Ready_Plugin::hash_ip( $ip ),
			'version'  => RENEWAL_READY_VERSION,
		);
		$order->update_meta_data( self::META, $record );
		/**
		 * The consent record was written on an order (the order is saved after this).
		 *
		 * @param array    $record Record.
		 * @param WC_Order $order  Order.
		 */
		do_action( 'renewal_ready_consent_recorded', $record, $order );
	}

	/**
	 * Show the record on the admin order screen.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function admin_order( $order ) {
		$r = $order->get_meta( self::META );
		if ( ! is_array( $r ) || empty( $r['time'] ) ) {
			return;
		}
		$when = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $r['time'] ) );
		echo '<div class="form-field form-field-wide rr-consent"><h3>' . esc_html__( 'Subscription agreement', 'renewal-ready' ) . '</h3><p>';
		if ( ! empty( $r['ticked'] ) ) {
			/* translators: %s: date and time. */
			echo esc_html( sprintf( __( 'Ticked by the customer on %s:', 'renewal-ready' ), $when ) ) . '<br><em>' . esc_html( $r['text'] ) . '</em>';
		} else {
			/* translators: %s: date and time. */
			echo esc_html( sprintf( __( 'Key information shown on %s (payment tick switched off).', 'renewal-ready' ), $when ) );
		}
		echo '</p><details><summary>' . esc_html__( 'Key information shown', 'renewal-ready' ) . '</summary><ul>';
		foreach ( (array) $r['lines'] as $l ) {
			echo '<li>' . esc_html( $l ) . '</li>';
		}
		echo '</ul></details></div>';
	}

	/**
	 * Optional box on the product page.
	 */
	public static function product_box() {
		if ( 'yes' !== Renewal_Ready_Plugin::get( 'product_box' ) ) {
			return;
		}
		global $product;
		$terms = Renewal_Ready_Terms::for_product( $product );
		if ( ! is_array( $terms ) ) {
			return;
		}
		self::enqueue_style();
		echo wp_kses( self::box_html( array( $terms ) ), self::box_allowed() );
	}

	/**
	 * Allowed HTML for the box.
	 *
	 * @return array
	 */
	public static function box_allowed() {
		return array_merge(
			Renewal_Ready_Terms::allowed(),
			array(
				'section' => array(
					'class'           => true,
					'aria-labelledby' => true,
				),
				'h2'      => array( 'id' => true ),
				'ul'      => array(),
				'li'      => array(),
				'i'       => array(
					'class'       => true,
					'aria-hidden' => true,
				),
				'label'   => array(
					'class' => true,
					'for'   => true,
				),
				'input'   => array(
					'type'     => true,
					'name'     => true,
					'id'       => true,
					'value'    => true,
					'checked'  => true,
					'required' => true,
				),
				'p'       => array(
					'class'     => true,
					'id'        => true,
					'hidden'    => true,
					'role'      => true,
					'aria-live' => true,
				),
				'div'     => array( 'class' => true ),
			)
		);
	}

	/**
	 * Enqueue the checkout styles.
	 */
	public static function enqueue_style() {
		wp_enqueue_style( 'renewal-ready-checkout', Renewal_Ready_Plugin::asset( 'css/checkout.css' ), array(), RENEWAL_READY_VERSION );
	}
}

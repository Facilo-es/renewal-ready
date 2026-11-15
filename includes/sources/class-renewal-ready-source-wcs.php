<?php
/**
 * WooCommerce Subscriptions (beta). Built only on the public functions and hooks
 * that WooCommerce Subscriptions shares with its open-source core library.
 *
 * @package RenewalReady
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adapter for WooCommerce Subscriptions.
 */
class Renewal_Ready_Source_WCS extends Renewal_Ready_Source_Base {

	/**
	 * Id.
	 *
	 * @return string
	 */
	public function id() {
		return 'wcs';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'WooCommerce Subscriptions (beta)', 'renewal-ready' );
	}

	/**
	 * Active?
	 *
	 * @return bool
	 */
	public function is_active() {
		return function_exists( 'wcs_get_subscription' ) && function_exists( 'wcs_get_subscriptions' );
	}

	/**
	 * Hook into the source's events.
	 */
	public function hooks() {
		add_action( 'woocommerce_subscription_status_updated', array( $this, 'on_status' ), 10, 3 );
		add_action( 'woocommerce_subscription_renewal_payment_complete', array( $this, 'on_renewal' ), 10, 1 );
		add_action( 'woocommerce_scheduled_subscription_payment', array( $this, 'guard' ), 0, 1 );
	}

	/**
	 * Status changed.
	 *
	 * @param WC_Subscription $sub Subscription.
	 * @param string          $new_status New status.
	 * @param string          $old Old status.
	 */
	public function on_status( $sub, $new_status, $old ) {
		if ( ! is_object( $sub ) || ! method_exists( $sub, 'get_id' ) ) {
			return;
		}
		$id    = (int) $sub->get_id();
		$ended = array( 'pending-cancel', 'cancelled' );
		if ( in_array( $new_status, $ended, true ) && ! in_array( $old, array( 'pending-cancel', 'cancelled', 'expired' ), true ) ) {
			Renewal_Ready_Sources::changed( $this, $id, 'ended' );
		} elseif ( 'expired' === $new_status ) {
			Renewal_Ready_Sources::changed( $this, $id, 'expired' );
		} elseif ( 'active' === $new_status && 'pending-cancel' === $old ) {
			Renewal_Ready_Sources::changed( $this, $id, 'reactivated' );
		}
	}

	/**
	 * Renewal paid.
	 *
	 * @param WC_Subscription $sub Subscription.
	 */
	public function on_renewal( $sub ) {
		if ( is_object( $sub ) && method_exists( $sub, 'get_id' ) ) {
			Renewal_Ready_Sources::changed( $this, (int) $sub->get_id(), 'renewed' );
		}
	}

	/**
	 * Runs before WooCommerce Subscriptions takes a renewal. If an end was asked for
	 * and the subscription is somehow still active, end it so no renewal is made.
	 *
	 * @param int $id Subscription id.
	 */
	public function guard( $id ) {
		$s = wcs_get_subscription( (int) $id );
		if ( $s && $s->has_status( 'active' ) && '' !== (string) $s->get_meta( '_rr_end_at', true ) ) {
			$s->update_status( 'cancelled', __( 'Renewal Ready: ended before renewal, as the customer asked.', 'renewal-ready' ) );
		}
	}

	/**
	 * Y-m-d from a subscription date.
	 *
	 * @param WC_Subscription $s    Subscription.
	 * @param string          $type Date type.
	 * @return string
	 */
	private function d( $s, $type ) {
		return $this->ymd( (int) $s->get_time( $type ) );
	}

	/**
	 * Record.
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public function get( $id ) {
		$s = wcs_get_subscription( (int) $id );
		if ( ! $s ) {
			return null;
		}
		$status = $s->get_status();
		if ( 'active' === $status ) {
			$state = 'active';
		} elseif ( 'pending-cancel' === $status ) {
			$state = 'ending';
		} elseif ( in_array( $status, array( 'cancelled', 'expired' ), true ) ) {
			$state = 'ended';
		} else {
			$state = 'other';
		}
		$end = $this->d( $s, 'end' );
		if ( 'ended' === $state && ! $end ) {
			$end = $this->d( $s, 'cancelled' );
		}
		$products = array();
		$name     = '';
		foreach ( $s->get_items() as $item ) {
			if ( is_callable( array( $item, 'get_product_id' ) ) ) {
				$products[] = (int) $item->get_product_id();
				if ( '' === $name ) {
					$name = $item->get_name();
				}
			}
		}
		$orders = $s->get_related_orders( 'all', array( 'parent', 'renewal' ) );
		$trial  = $this->d( $s, 'trial_end' );
		$r      = array_merge(
			array(
				'id'                => (int) $s->get_id(),
				'customer_id'       => (int) $s->get_customer_id(),
				'status'            => $state,
				'start_date'        => $this->d( $s, 'start' ),
				'next_payment_date' => 'active' === $state ? $this->d( $s, 'next_payment' ) : '',
				'end_date'          => $end ? $end : null,
				'period'            => (string) $s->get_billing_period(),
				'interval'          => (int) $s->get_billing_interval(),
				'recurring_amount'  => (float) $s->get_total(),
				'trial_end'         => $trial ? $trial : null,
				'product_ids'       => $products,
				'product_name'      => $name,
				'can_end_at_term'   => 'active' !== $state || $s->can_be_updated_to( 'pending-cancel' ),
				'view_url'          => (string) $s->get_view_order_url(),
			),
			$this->billing( $s ),
			$this->history( is_array( $orders ) ? array_values( $orders ) : array() )
		);
		return $this->record( $this->from_customer( $r ) );
	}

	/**
	 * Ids.
	 *
	 * @param string $state  active or ended.
	 * @param int    $offset Offset.
	 * @param int    $limit  Limit.
	 * @return int[]
	 */
	public function ids( $state, $offset, $limit ) {
		$subs = wcs_get_subscriptions(
			array(
				'subscription_status'    => 'active' === $state ? 'active' : 'pending-cancel',
				'subscriptions_per_page' => (int) $limit,
				'offset'                 => (int) $offset,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
			)
		);
		return array_map( 'intval', array_keys( is_array( $subs ) ? $subs : array() ) );
	}

	/**
	 * Customer's subscriptions.
	 *
	 * @param int $user_id User.
	 * @return int[]
	 */
	public function ids_for_customer( $user_id ) {
		$subs = function_exists( 'wcs_get_users_subscriptions' ) ? wcs_get_users_subscriptions( (int) $user_id ) : array();
		return array_map( 'intval', array_keys( is_array( $subs ) ? $subs : array() ) );
	}

	/**
	 * End at the end of the paid period with the source's own pending-cancel status.
	 *
	 * @param int    $id  Id.
	 * @param string $who customer or admin.
	 * @return string|WP_Error
	 */
	public function end( $id, $who = 'customer' ) {
		$s = wcs_get_subscription( (int) $id );
		if ( ! $s || ! $s->has_status( 'active' ) ) {
			return new WP_Error( 'rr_not_active', __( 'This subscription is not active.', 'renewal-ready' ) );
		}
		$this->meta_set( $id, '_rr_end_by', 'admin' === $who ? 'admin' : 'customer' );
		$this->meta_set( $id, '_rr_end_at', $this->d( $s, 'next_payment' ) ? $this->d( $s, 'next_payment' ) : Renewal_Ready_Plugin::today() );
		$note = __( 'Renewal Ready: the customer asked to end this subscription.', 'renewal-ready' );
		if ( 'admin' === $who ) {
			$note = __( 'Renewal Ready: ended by the shop.', 'renewal-ready' );
		}
		$s->update_status( $s->can_be_updated_to( 'pending-cancel' ) ? 'pending-cancel' : 'cancelled', $note );
		$s   = wcs_get_subscription( (int) $id );
		$end = $s ? $this->d( $s, 'end' ) : '';
		$end = $end ? $end : Renewal_Ready_Plugin::today();
		$this->meta_set( $id, '_rr_end_at', $end );
		return $end;
	}

	/**
	 * Note on the subscription.
	 *
	 * @param int    $id   Id.
	 * @param string $text Note.
	 */
	public function note( $id, $text ) {
		$this->order_note( (int) $id, $text );
	}

	/**
	 * Meta get.
	 *
	 * @param int    $id  Id.
	 * @param string $key Key.
	 * @return mixed
	 */
	public function meta_get( $id, $key ) {
		return $this->order_meta_get( $id, $key );
	}

	/**
	 * Meta set.
	 *
	 * @param int    $id    Id.
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 */
	public function meta_set( $id, $key, $value ) {
		$this->order_meta_set( $id, $key, $value );
	}

	/**
	 * Subscription product?
	 *
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	public function is_subscription_product( $product ) {
		return $product instanceof WC_Product && class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $product );
	}

	/**
	 * Product terms.
	 *
	 * @param WC_Product $product Product.
	 * @return array|null
	 */
	public function product_terms( $product ) {
		if ( ! $this->is_subscription_product( $product ) ) {
			return null;
		}
		return array(
			'period'     => (string) WC_Subscriptions_Product::get_period( $product ),
			'interval'   => max( 1, (int) WC_Subscriptions_Product::get_interval( $product ) ),
			'price'      => (float) WC_Subscriptions_Product::get_price( $product ),
			'trial_days' => $this->trial_days( (int) WC_Subscriptions_Product::get_trial_length( $product ), (string) WC_Subscriptions_Product::get_trial_period( $product ) ),
			'signup_fee' => (float) WC_Subscriptions_Product::get_sign_up_fee( $product ),
			'intro'      => null,
			'min_term'   => 0,
		);
	}
}

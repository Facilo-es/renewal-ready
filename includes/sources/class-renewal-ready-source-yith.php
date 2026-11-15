<?php
/**
 * YITH WooCommerce Subscription (free).
 *
 * Its timestamps are real UTC. It has no subscription notes, so notes go on
 * the parent order.
 *
 * @package RenewalReady
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adapter for YITH subscriptions.
 */
class Renewal_Ready_Source_YITH extends Renewal_Ready_Source_Base {

	/**
	 * Id.
	 *
	 * @return string
	 */
	public function id() {
		return 'yith';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'YITH WooCommerce Subscription', 'renewal-ready' );
	}

	/**
	 * Active?
	 *
	 * @return bool
	 */
	public function is_active() {
		return function_exists( 'ywsbs_get_subscription' );
	}

	/**
	 * Hooks.
	 */
	public function hooks() {
		add_action( 'ywsbs_subscription_status_changed', array( $this, 'on_status' ), 10, 3 );
		add_filter( 'ywsbs_skip_create_renew_order', array( $this, 'skip_renewal' ), 10, 2 );
	}

	/**
	 * Status changed.
	 *
	 * @param int    $id  Id.
	 * @param string $old Old status.
	 * @param string $new_status New status.
	 */
	public function on_status( $id, $old, $new_status ) {
		if ( in_array( $new_status, array( 'cancelled', 'expired' ), true ) && ! in_array( $old, array( 'cancelled', 'expired' ), true ) ) {
			Renewal_Ready_Sources::changed( $this, (int) $id, 'ended' );
		} elseif ( 'active' === $new_status && 'cancelled' === $old ) {
			Renewal_Ready_Sources::changed( $this, (int) $id, 'reactivated' );
		}
	}

	/**
	 * Never create a renewal for a subscription whose end was asked for.
	 *
	 * @param bool   $skip Skip.
	 * @param object $sub  YITH subscription.
	 * @return bool
	 */
	public function skip_renewal( $skip, $sub ) {
		if ( is_object( $sub ) && method_exists( $sub, 'get_id' ) && '' !== (string) $this->meta_get( $sub->get_id(), '_rr_end_at' ) ) {
			return true;
		}
		return $skip;
	}

	/**
	 * Y-m-d from a UTC timestamp.
	 *
	 * @param mixed $ts Timestamp.
	 * @return string
	 */
	private function d( $ts ) {
		return (int) $ts > 0 ? $this->ymd( (int) $ts ) : '';
	}

	/**
	 * Record.
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public function get( $id ) {
		$id = (int) $id;
		if ( ! $id || 'ywsbs_subscription' !== get_post_type( $id ) ) {
			return null;
		}
		$s = ywsbs_get_subscription( $id );
		if ( ! $s ) {
			return null;
		}
		$status = (string) $s->get( 'status' );
		$end    = $this->d( $s->get( 'end_date' ) );
		$today  = Renewal_Ready_Plugin::today();
		if ( 'active' === $status ) {
			$state = 'active';
		} elseif ( 'cancelled' === $status && $end && $end > $today ) {
			$state = 'ending';
		} elseif ( in_array( $status, array( 'cancelled', 'expired' ), true ) ) {
			$state = 'ended';
		} else {
			$state = 'other';
		}
		if ( 'ended' === $state && ! $end ) {
			$end = $this->d( $s->get( 'cancelled_date' ) );
			$end = $end ? $end : $today;
		}
		$orders = array();
		$ids    = $s->get( 'order_ids' );
		foreach ( is_array( $ids ) ? $ids : array() as $oid ) {
			$o = wc_get_order( (int) $oid );
			if ( $o ) {
				$orders[] = $o;
			}
		}
		$parent = wc_get_order( (int) $s->get( 'order_id' ) );
		$per    = rtrim( (string) $s->get( 'price_time_option' ), 's' );
		$r      = array_merge(
			array(
				'id'                => $id,
				'customer_id'       => (int) $s->get( 'user_id' ),
				'status'            => $state,
				'start_date'        => $this->d( $s->get( 'start_date' ) ),
				'next_payment_date' => 'active' === $state ? $this->d( $s->get( 'payment_due_date' ) ) : '',
				'end_date'          => $end ? $end : null,
				'period'            => $per,
				'interval'          => (int) $s->get( 'price_is_per' ),
				'recurring_amount'  => (float) $s->get( 'subscription_total' ),
				'product_ids'       => array( (int) $s->get( 'product_id' ) ),
				'product_name'      => (string) $s->get( 'product_name' ),
				'view_url'          => wc_get_endpoint_url( 'view-subscription', $id, wc_get_page_permalink( 'myaccount' ) ),
			),
			$this->billing( $parent ),
			$this->history( $orders )
		);
		if ( $s->get( 'order_currency' ) ) {
			$r['currency'] = (string) $s->get( 'order_currency' );
		}
		if ( '' === $r['email'] ) {
			$r['email'] = (string) $s->get_billing_email();
		}
		if ( '' === $r['first_name'] ) {
			$r['first_name'] = (string) $s->get_billing_first_name();
		}
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
		$meta = 'active' === $state
			? array(
				'key'   => 'status',
				'value' => 'active',
			)
			: array(
				'key'   => '_rr_watch',
				'value' => '1',
			);
		return $this->query( array( $meta ), $offset, $limit );
	}

	/**
	 * Query.
	 *
	 * @param array $meta_query Meta query.
	 * @param int   $offset     Offset.
	 * @param int   $limit      Limit.
	 * @return int[]
	 */
	private function query( array $meta_query, $offset, $limit ) {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => 'ywsbs_subscription',
					'post_status'    => 'any',
					'fields'         => 'ids',
					'posts_per_page' => (int) $limit,
					'offset'         => (int) $offset,
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Subscriptions are found by their status, a page at a time.
				)
			)
		);
	}

	/**
	 * Customer's subscriptions.
	 *
	 * @param int $user_id User.
	 * @return int[]
	 */
	public function ids_for_customer( $user_id ) {
		return $this->query(
			array(
				array(
					'key'   => 'user_id',
					'value' => (int) $user_id,
				),
			),
			0,
			100
		);
	}

	/**
	 * End at the end of the paid period. YITH keeps a cancelled subscription
	 * running until its due date and takes no more payments.
	 *
	 * @param int    $id  Id.
	 * @param string $who customer or admin.
	 * @return string|WP_Error
	 */
	public function end( $id, $who = 'customer' ) {
		$r = $this->get( $id );
		if ( ! $r || 'active' !== $r['status'] ) {
			return new WP_Error( 'rr_not_active', __( 'This subscription is not active.', 'renewal-ready' ) );
		}
		$end = $r['next_payment_date'] ? $r['next_payment_date'] : Renewal_Ready_Plugin::today();
		$this->meta_set( $id, '_rr_end_at', $end );
		$s = ywsbs_get_subscription( $id );
		$s->update_status( 'cancelled', 'admin' === $who ? 'administrator' : 'customer' );
		return $end;
	}

	/**
	 * Note on the parent order.
	 *
	 * @param int    $id   Id.
	 * @param string $text Note.
	 */
	public function note( $id, $text ) {
		$s = ywsbs_get_subscription( $id );
		if ( $s ) {
			/* translators: 1: subscription number, 2: note. */
			$this->order_note( (int) $s->get( 'order_id' ), sprintf( __( 'Subscription #%1$d: %2$s', 'renewal-ready' ), $id, $text ) );
		}
	}

	/**
	 * Meta get.
	 *
	 * @param int    $id  Id.
	 * @param string $key Key.
	 * @return mixed
	 */
	public function meta_get( $id, $key ) {
		return get_post_meta( (int) $id, $key, true );
	}

	/**
	 * Meta set.
	 *
	 * @param int    $id    Id.
	 * @param string $key   Key.
	 * @param mixed  $value Value, null deletes.
	 */
	public function meta_set( $id, $key, $value ) {
		if ( null === $value ) {
			delete_post_meta( (int) $id, $key );
		} else {
			update_post_meta( (int) $id, $key, $value );
		}
	}

	/**
	 * Subscription product?
	 *
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	public function is_subscription_product( $product ) {
		$pid = $this->meta_holder( $product, '_ywsbs_subscription' );
		return $pid && 'yes' === get_post_meta( $pid, '_ywsbs_subscription', true );
	}

	/**
	 * Product terms. YITH free has no trial or sign-up fee.
	 *
	 * @param WC_Product $product Product.
	 * @return array|null
	 */
	public function product_terms( $product ) {
		if ( ! $this->is_subscription_product( $product ) ) {
			return null;
		}
		$pid = $this->meta_holder( $product, '_ywsbs_subscription' );
		return array(
			'period'     => rtrim( (string) get_post_meta( $pid, '_ywsbs_price_time_option', true ), 's' ),
			'interval'   => max( 1, (int) get_post_meta( $pid, '_ywsbs_price_is_per', true ) ),
			'price'      => (float) $product->get_price(),
			'trial_days' => 0,
			'signup_fee' => 0.0,
			'intro'      => null,
			'min_term'   => 0,
		);
	}
}

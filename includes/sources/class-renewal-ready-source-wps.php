<?php
/**
 * WP Swings "Subscriptions for WooCommerce".
 *
 * Its timestamps are site wall-clock times stored as if they were UTC, so they
 * are read with gmdate() and compared with current_time( 'timestamp' ).
 *
 * @package RenewalReady
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adapter for WP Swings subscriptions.
 */
class Renewal_Ready_Source_WPS extends Renewal_Ready_Source_Base {

	/**
	 * Id.
	 *
	 * @return string
	 */
	public function id() {
		return 'wps';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'WP Swings Subscriptions for WooCommerce', 'renewal-ready' );
	}

	/**
	 * Active?
	 *
	 * @return bool
	 */
	public function is_active() {
		return function_exists( 'wps_sfw_get_meta_data' );
	}

	/**
	 * Hook into the source's events.
	 */
	public function hooks() {
		add_action( 'wps_sfw_subscription_cancel', array( $this, 'on_cancel' ), 5, 1 );
		add_action( 'wps_sfw_expire_subscription_scheduler', array( $this, 'on_expire' ), 10, 1 );
		add_action( 'wps_sfw_after_renewal_payment', array( $this, 'on_renewal' ), 10, 2 );
		add_action( 'wps_sfw_create_renewal_order_schedule', array( $this, 'finish_due' ), 0 );
		add_action( 'wps_sfw_expired_renewal_subscription', array( $this, 'finish_due' ), 0 );
	}

	/**
	 * The source is cancelling a subscription (its button, admin or ours).
	 * It fires before the status is saved, so the end is now.
	 *
	 * @param int $id Subscription id.
	 */
	public function on_cancel( $id ) {
		Renewal_Ready_Sources::changed( $this, (int) $id, 'ended', array( 'ended_now' => true ) );
	}

	/**
	 * Expired.
	 *
	 * @param int $id Subscription id.
	 */
	public function on_expire( $id ) {
		Renewal_Ready_Sources::changed( $this, (int) $id, 'expired' );
	}

	/**
	 * Renewal paid.
	 *
	 * @param WC_Order $order Order.
	 * @param int      $id    Subscription id.
	 */
	public function on_renewal( $order, $id ) {
		Renewal_Ready_Sources::changed( $this, (int) $id, 'renewed' );
	}

	/**
	 * Read WPS meta.
	 *
	 * @param int    $id  Id.
	 * @param string $key Key.
	 * @return mixed
	 */
	private function m( $id, $key ) {
		return wps_sfw_get_meta_data( $id, $key, true );
	}

	/**
	 * Y-m-d from a WPS timestamp.
	 *
	 * @param mixed $ts Timestamp.
	 * @return string
	 */
	private function wps_ymd( $ts ) {
		return (int) $ts > 0 ? gmdate( 'Y-m-d', (int) $ts ) : '';
	}

	/**
	 * Record.
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public function get( $id ) {
		$id = (int) $id;
		if ( ! $id || ! function_exists( 'wps_sfw_check_valid_subscription' ) || ! wps_sfw_check_valid_subscription( $id ) ) {
			return null;
		}
		$status = (string) $this->m( $id, 'wps_subscription_status' );
		$end_at = (string) $this->meta_get( $id, '_rr_end_at' );
		$end    = $this->wps_ymd( $this->m( $id, 'wps_susbcription_end' ) );
		if ( 'active' === $status ) {
			$state = $end_at ? 'ending' : 'active';
		} elseif ( in_array( $status, array( 'cancelled', 'expired' ), true ) ) {
			$state = 'ended';
		} else {
			$state = 'other';
		}
		if ( 'ended' === $state ) {
			$cancelled = (int) $this->m( $id, 'wps_subscription_cancelled_date' );
			if ( ! $end && $cancelled ) {
				$end = wp_date( 'Y-m-d', $cancelled );
			}
			if ( ! $end ) {
				$end = $end_at ? $end_at : Renewal_Ready_Plugin::today();
			}
		}
		if ( $end_at ) {
			$end = $end_at;
		}
		$parent_id = (int) $this->m( $id, 'wps_parent_order' );
		$parent    = $parent_id ? wc_get_order( $parent_id ) : false;
		$orders    = $parent ? array( $parent ) : array();
		$renewals  = $this->m( $id, 'wps_wsp_renewal_order_data' );
		if ( is_array( $renewals ) ) {
			foreach ( $renewals as $rid ) {
				$o = wc_get_order( (int) $rid );
				if ( $o ) {
					$orders[] = $o;
				}
			}
		}
		$product_id = (int) $this->m( $id, 'product_id' );
		$product    = $product_id ? wc_get_product( $product_id ) : false;
		$name       = (string) $this->m( $id, 'product_name' );
		if ( '' === $name && $product ) {
			$name = $product->get_name();
		}
		$trial_end = $this->wps_ymd( $this->m( $id, 'wps_susbcription_trial_end' ) );
		$r         = array_merge(
			array(
				'id'                => $id,
				'customer_id'       => (int) $this->m( $id, 'wps_customer_id' ),
				'status'            => $state,
				'start_date'        => $this->wps_ymd( $this->m( $id, 'wps_schedule_start' ) ),
				'next_payment_date' => 'active' === $state ? $this->wps_ymd( $this->m( $id, 'wps_next_payment_date' ) ) : '',
				'end_date'          => $end ? $end : null,
				'period'            => (string) $this->m( $id, 'wps_sfw_subscription_interval' ),
				'interval'          => (int) $this->m( $id, 'wps_sfw_subscription_number' ),
				'recurring_amount'  => (float) $this->m( $id, 'wps_recurring_total' ),
				'trial_end'         => $trial_end ? $trial_end : null,
				'product_ids'       => $product_id ? array( $product_id ) : array(),
				'product_name'      => $name,
				'view_url'          => wc_get_endpoint_url( 'show-subscription', $id, wc_get_page_permalink( 'myaccount' ) ),
			),
			$this->billing( $parent ),
			$this->history( $orders )
		);
		if ( 'ending' === $state ) {
			$r['next_payment_date'] = '';
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
				'key'   => 'wps_subscription_status',
				'value' => 'active',
			)
			: array(
				'key'   => '_rr_watch',
				'value' => '1',
			);
		return $this->query( array( $meta ), $offset, $limit );
	}

	/**
	 * Run a subscription query.
	 *
	 * @param array $meta_query Meta query.
	 * @param int   $offset     Offset.
	 * @param int   $limit      Limit.
	 * @return int[]
	 */
	private function query( array $meta_query, $offset, $limit ) {
		$hpos = class_exists( '\\Automattic\\WooCommerce\\Utilities\\OrderUtil' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		if ( $hpos ) {
			$ids = wc_get_orders(
				array(
					'type'       => 'wps_subscriptions',
					'status'     => array( 'wc-wps_renewal' ),
					'return'     => 'ids',
					'limit'      => (int) $limit,
					'offset'     => (int) $offset,
					'orderby'    => 'ID',
					'order'      => 'ASC',
					'meta_query' => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Subscriptions are found by their status meta, a page at a time.
				)
			);
		} else {
			// The posts order store ignores meta_query in wc_get_orders, so ask WP_Query directly.
			$ids = get_posts(
				array(
					'post_type'      => 'wps_subscriptions',
					'post_status'    => 'wc-wps_renewal',
					'fields'         => 'ids',
					'posts_per_page' => (int) $limit,
					'offset'         => (int) $offset,
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Subscriptions are found by their status meta, a page at a time.
				)
			);
		}
		return array_map( 'intval', is_array( $ids ) ? $ids : array() );
	}

	/**
	 * A customer's subscriptions.
	 *
	 * @param int $user_id User.
	 * @return int[]
	 */
	public function ids_for_customer( $user_id ) {
		return $this->query(
			array(
				array(
					'key'   => 'wps_customer_id',
					'value' => (int) $user_id,
				),
			),
			0,
			100
		);
	}

	/**
	 * End at the end of the paid period: remember the date and cancel then.
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
		$this->meta_set( $id, '_rr_end_by', $who );
		if ( $end <= Renewal_Ready_Plugin::today() ) {
			$this->cancel_now( $id, $who );
		}
		return $end;
	}

	/**
	 * Cancel through the source, as its own admin action does.
	 *
	 * @param int    $id  Id.
	 * @param string $who customer or admin.
	 */
	private function cancel_now( $id, $who ) {
		do_action( 'wps_sfw_subscription_cancel', $id, 'Cancel' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WP Swings' own cancel hook.
		wps_sfw_update_meta_data( $id, 'wps_subscription_status', 'cancelled' );
		wps_sfw_update_meta_data( $id, 'wps_subscription_cancelled_by', 'admin' === $who ? 'by_admin' : 'by_user' );
		wps_sfw_update_meta_data( $id, 'wps_subscription_cancelled_date', time() );
	}

	/**
	 * Before WPS makes renewal orders: cancel every subscription whose end was
	 * asked for and whose paid period is over, so no renewal is created.
	 */
	public function finish_due() {
		$ids   = $this->query(
			array(
				array(
					'key'     => '_rr_end_at',
					'compare' => 'EXISTS',
				),
				array(
					'key'   => 'wps_subscription_status',
					'value' => 'active',
				),
			),
			0,
			500
		);
		$now   = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- WPS stores its dates on this basis.
		$today = Renewal_Ready_Plugin::today();
		foreach ( $ids as $id ) {
			$next = (int) $this->m( $id, 'wps_next_payment_date' );
			$at   = (string) $this->meta_get( $id, '_rr_end_at' );
			if ( ( $at && $at <= $today ) || ( $next && $next <= $now + HOUR_IN_SECONDS ) ) {
				$this->cancel_now( $id, (string) $this->meta_get( $id, '_rr_end_by' ) );
			}
		}
	}

	/**
	 * Note on the subscription.
	 *
	 * @param int    $id   Id.
	 * @param string $text Note.
	 */
	public function note( $id, $text ) {
		if ( ! $this->order_note( $id, $text ) ) {
			$this->order_note( (int) $this->m( $id, 'wps_parent_order' ), $text );
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
		$pid = $this->meta_holder( $product, '_wps_sfw_product' );
		return $pid && 'yes' === get_post_meta( $pid, '_wps_sfw_product', true );
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
		$pid = $this->meta_holder( $product, '_wps_sfw_product' );
		$g   = function ( $k ) use ( $pid ) {
			return get_post_meta( $pid, $k, true );
		};
		return array(
			'period'     => (string) $g( 'wps_sfw_subscription_interval' ),
			'interval'   => max( 1, (int) $g( 'wps_sfw_subscription_number' ) ),
			'price'      => (float) $product->get_price(),
			'trial_days' => $this->trial_days( (int) $g( 'wps_sfw_subscription_free_trial_number' ), (string) $g( 'wps_sfw_subscription_free_trial_interval' ) ),
			'signup_fee' => (float) $g( 'wps_sfw_subscription_initial_signup_price' ),
			'intro'      => null,
			'min_term'   => 0,
		);
	}
}

<?php
/**
 * Shared parts of the source adapters.
 *
 * @package RenewalReady
 */

defined( 'ABSPATH' ) || exit;

/**
 * Helpers every adapter uses.
 */
abstract class Renewal_Ready_Source_Base implements Renewal_Ready_Source {

	/**
	 * Active subscription ids, a page at a time.
	 *
	 * @param int $offset Offset.
	 * @param int $limit  Limit.
	 * @return int[]
	 */
	public function active_ids( $offset = 0, $limit = 50 ) {
		return $this->ids( 'active', (int) $offset, (int) $limit );
	}

	/**
	 * Fill in a record and let extensions adjust it.
	 *
	 * @param array $r Partial record.
	 * @return array
	 */
	protected function record( array $r ) {
		$r = array_merge(
			array(
				'id'                   => 0,
				'source'               => $this->id(),
				'customer_id'          => 0,
				'email'                => '',
				'first_name'           => '',
				'country'              => '',
				'company'              => '',
				'status'               => 'other',
				'start_date'           => '',
				'next_payment_date'    => '',
				'end_date'             => null,
				'period'               => 'month',
				'interval'             => 1,
				'recurring_amount'     => 0.0,
				'currency'             => get_woocommerce_currency(),
				'trial_end'            => null,
				'intro'                => null,
				'min_term'             => 0,
				'previous_payment'     => null,
				'payments_count'       => 0,
				'paid_total'           => 0.0,
				'since_date'           => '',
				'first_amount'         => null,
				'product_ids'          => array(),
				'product_name'         => '',
				'payment_method_title' => '',
				'can_end_at_term'      => true,
				'view_url'             => '',
			),
			$r
		);
		if ( ! in_array( $r['period'], array( 'day', 'week', 'month', 'year' ), true ) ) {
			$r['period'] = 'month';
		}
		$r['interval']         = max( 1, (int) $r['interval'] );
		$r['recurring_amount'] = (float) $r['recurring_amount'];
		if ( $r['trial_end'] && $r['trial_end'] <= $r['start_date'] ) {
			$r['trial_end'] = null;
		}
		/**
		 * Adjust a normalised subscription record, for example to add intro pricing.
		 *
		 * @param array  $r      Record.
		 * @param string $source Source id.
		 */
		return apply_filters( 'renewal_ready_subscription', $r, $this->id() );
	}

	/**
	 * Y-m-d in site time from a real UTC timestamp.
	 *
	 * @param int $ts Timestamp.
	 * @return string
	 */
	protected function ymd( $ts ) {
		return $ts ? wp_date( 'Y-m-d', (int) $ts ) : '';
	}

	/**
	 * Payment history from a list of orders.
	 *
	 * @param WC_Order[] $orders Orders (parent and renewals).
	 * @return array previous_payment, payments_count, paid_total, since_date, first_amount.
	 */
	protected function history( array $orders ) {
		$paid = array();
		foreach ( $orders as $o ) {
			if ( ! $o instanceof WC_Order || ! $o->is_paid() || (float) $o->get_total() <= 0 ) {
				continue;
			}
			$d      = $o->get_date_paid() ? $o->get_date_paid() : $o->get_date_created();
			$paid[] = array(
				'amount' => (float) $o->get_total(),
				'date'   => $d ? wp_date( 'Y-m-d', $d->getTimestamp() ) : '',
				'ts'     => $d ? $d->getTimestamp() : 0,
			);
		}
		usort(
			$paid,
			function ( $a, $b ) {
				return $a['ts'] - $b['ts'];
			}
		);
		$out = array(
			'previous_payment' => null,
			'payments_count'   => count( $paid ),
			'paid_total'       => 0.0,
			'since_date'       => '',
			'first_amount'     => null,
		);
		foreach ( $paid as $p ) {
			$out['paid_total'] += $p['amount'];
		}
		if ( $paid ) {
			$last                    = end( $paid );
			$out['previous_payment'] = array(
				'amount' => $last['amount'],
				'date'   => $last['date'],
			);
			$out['since_date']       = $paid[0]['date'];
			$out['first_amount']     = $paid[0]['amount'];
		}
		return $out;
	}

	/**
	 * Billing details from an order.
	 *
	 * @param WC_Order|false $order Order.
	 * @return array
	 */
	protected function billing( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return array();
		}
		return array_filter(
			array(
				'email'                => $order->get_billing_email(),
				'first_name'           => $order->get_billing_first_name(),
				'country'              => $order->get_billing_country(),
				'company'              => $order->get_billing_company(),
				'currency'             => $order->get_currency(),
				'payment_method_title' => $order->get_payment_method_title(),
			)
		);
	}

	/**
	 * Fill missing details from the customer account.
	 *
	 * @param array $r Record so far.
	 * @return array
	 */
	protected function from_customer( array $r ) {
		if ( ( '' === $r['email'] || '' === $r['first_name'] ) && $r['customer_id'] ) {
			$u = get_userdata( (int) $r['customer_id'] );
			if ( $u ) {
				if ( '' === $r['email'] ) {
					$r['email'] = $u->user_email;
				}
				if ( '' === $r['first_name'] ) {
					$r['first_name'] = (string) get_user_meta( $u->ID, 'billing_first_name', true );
					if ( '' === $r['first_name'] ) {
						$r['first_name'] = $u->first_name;
					}
				}
			}
		}
		return $r;
	}

	/**
	 * Read meta on an order-type subscription.
	 *
	 * @param int    $id  Id.
	 * @param string $key Key.
	 * @return mixed
	 */
	protected function order_meta_get( $id, $key ) {
		$o = wc_get_order( $id );
		return $o ? $o->get_meta( $key, true ) : '';
	}

	/**
	 * Write meta on an order-type subscription.
	 *
	 * @param int    $id    Id.
	 * @param string $key   Key.
	 * @param mixed  $value Value, null deletes.
	 */
	protected function order_meta_set( $id, $key, $value ) {
		$o = wc_get_order( $id );
		if ( ! $o ) {
			return;
		}
		if ( null === $value ) {
			$o->delete_meta_data( $key );
		} else {
			$o->update_meta_data( $key, $value );
		}
		$o->save_meta_data();
	}

	/**
	 * Days in a trial of N units.
	 *
	 * @param int    $n    Count.
	 * @param string $unit day, week, month or year (plural allowed).
	 * @return int
	 */
	protected function trial_days( $n, $unit ) {
		$unit = rtrim( (string) $unit, 's' );
		$mult = array(
			'day'   => 1,
			'week'  => 7,
			'month' => 30,
			'year'  => 365,
		);
		return (int) $n * ( isset( $mult[ $unit ] ) ? $mult[ $unit ] : 1 );
	}

	/**
	 * Product, or its parent for variations, holding subscription meta.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $key     Meta key to look for.
	 * @return int Product id holding the key, or 0.
	 */
	protected function meta_holder( $product, $key ) {
		if ( ! $product instanceof WC_Product ) {
			return 0;
		}
		foreach ( array( $product->get_id(), $product->get_parent_id() ) as $pid ) {
			if ( $pid && '' !== (string) get_post_meta( $pid, $key, true ) ) {
				return (int) $pid;
			}
		}
		return 0;
	}

	/**
	 * Order note on an order, if it exists.
	 *
	 * @param int    $order_id Order id.
	 * @param string $text     Note.
	 * @return bool
	 */
	protected function order_note( $order_id, $text ) {
		$o = $order_id ? wc_get_order( $order_id ) : false;
		if ( $o ) {
			$o->add_order_note( $text );
			return true;
		}
		return false;
	}
}

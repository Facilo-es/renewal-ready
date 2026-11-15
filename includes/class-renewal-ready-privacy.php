<?php
/**
 * Privacy: suggested policy text, personal data exporter and eraser.
 *
 * @package RenewalReady
 */

defined( 'ABSPATH' ) || exit;

/**
 * What Renewal Ready stores about customers, and how to export or erase it.
 */
class Renewal_Ready_Privacy {

	const PAGE = 50;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'policy' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'erasers' ) );
	}

	/**
	 * Suggested privacy policy text.
	 */
	public static function policy() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$text  = '<p>' . esc_html__( 'When you buy a subscription, we keep a record of the key information shown to you at checkout, whether you ticked the box agreeing to pay, and when. We keep it as proof of what you agreed to.', 'renewal-ready' ) . '</p>';
		$text .= '<p>' . esc_html__( 'We send you reminders before your subscription renews and a confirmation when it ends. We keep a note of each one on your subscription, and the reason you gave if you ended it.', 'renewal-ready' ) . '</p>';
		$text .= '<p>' . esc_html__( 'We store a one-way scrambled form of your IP address with the checkout record, never the address itself. Nothing is sent to anyone else.', 'renewal-ready' ) . '</p>';
		wp_add_privacy_policy_content( 'Renewal Ready', wp_kses_post( $text ) );
	}

	/**
	 * Register the exporter.
	 *
	 * @param array $items Exporters.
	 * @return array
	 */
	public static function exporters( $items ) {
		$items['renewal-ready'] = array(
			'exporter_friendly_name' => __( 'Renewal Ready subscription records', 'renewal-ready' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $items;
	}

	/**
	 * Register the eraser.
	 *
	 * @param array $items Erasers.
	 * @return array
	 */
	public static function erasers( $items ) {
		$items['renewal-ready'] = array(
			'eraser_friendly_name' => __( 'Renewal Ready subscription records', 'renewal-ready' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $items;
	}

	/**
	 * Orders of a customer that hold a consent record.
	 *
	 * @param string $email Email.
	 * @param int    $page  Page (1-based).
	 * @return WC_Order[]
	 */
	private static function orders( $email, $page ) {
		$orders = wc_get_orders(
			array(
				'billing_email' => $email,
				'limit'         => self::PAGE,
				'page'          => max( 1, (int) $page ),
				'orderby'       => 'ID',
				'order'         => 'ASC',
				'type'          => 'shop_order',
			)
		);
		$user   = get_user_by( 'email', $email );
		if ( $user && 1 === (int) $page ) {
			$more = wc_get_orders(
				array(
					'customer_id' => $user->ID,
					'limit'       => -1,
					'type'        => 'shop_order',
				)
			);
			foreach ( $more as $o ) {
				$orders[] = $o;
			}
		}
		$out = array();
		foreach ( $orders as $o ) {
			if ( $o instanceof WC_Order && is_array( $o->get_meta( Renewal_Ready_Checkout::META ) ) ) {
				$out[ $o->get_id() ] = $o;
			}
		}
		return $out;
	}

	/**
	 * Subscription ids of the user with this email.
	 *
	 * @param string $email Email.
	 * @return int[]
	 */
	private static function subs( $email ) {
		$src  = Renewal_Ready_Sources::active();
		$user = get_user_by( 'email', $email );
		return $src && $user ? array_map( 'intval', (array) $src->ids_for_customer( $user->ID ) ) : array();
	}

	/**
	 * Export.
	 *
	 * @param string $email Email.
	 * @param int    $page  Page.
	 * @return array
	 */
	public static function export( $email, $page = 1 ) {
		$items  = array();
		$orders = self::orders( $email, $page );
		foreach ( $orders as $o ) {
			$r       = $o->get_meta( Renewal_Ready_Checkout::META );
			$items[] = array(
				'group_id'    => 'renewal-ready-consent',
				'group_label' => __( 'Subscription agreements', 'renewal-ready' ),
				'item_id'     => 'rr-consent-' . $o->get_id(),
				'data'        => array(
					array(
						'name'  => __( 'Order', 'renewal-ready' ),
						'value' => (string) $o->get_order_number(),
					),
					array(
						'name'  => __( 'Ticked', 'renewal-ready' ),
						'value' => ! empty( $r['ticked'] ) ? __( 'Yes', 'renewal-ready' ) : __( 'No', 'renewal-ready' ),
					),
					array(
						'name'  => __( 'Text agreed to', 'renewal-ready' ),
						'value' => isset( $r['text'] ) ? (string) $r['text'] : '',
					),
					array(
						'name'  => __( 'Key information shown', 'renewal-ready' ),
						'value' => isset( $r['lines'] ) ? implode( ' | ', (array) $r['lines'] ) : '',
					),
					array(
						'name'  => __( 'Time', 'renewal-ready' ),
						'value' => isset( $r['time'] ) ? (string) $r['time'] : '',
					),
				),
			);
		}
		$src = Renewal_Ready_Sources::active();
		if ( $src && 1 === (int) $page ) {
			foreach ( self::subs( $email ) as $id ) {
				$sent   = array_keys( Renewal_Ready_Notice_Log::keys( $src, $id ) );
				$reason = (string) $src->meta_get( $id, '_rr_reason' );
				$end    = (string) $src->meta_get( $id, '_rr_end_at' );
				if ( ! $sent && '' === $reason && '' === $end ) {
					continue;
				}
				$items[] = array(
					'group_id'    => 'renewal-ready-notices',
					'group_label' => __( 'Subscription notices', 'renewal-ready' ),
					'item_id'     => 'rr-sub-' . $id,
					'data'        => array(
						array(
							'name'  => __( 'Subscription', 'renewal-ready' ),
							'value' => (string) $id,
						),
						array(
							'name'  => __( 'Notices sent', 'renewal-ready' ),
							'value' => implode( ', ', $sent ),
						),
						array(
							'name'  => __( 'Ends on', 'renewal-ready' ),
							'value' => $end,
						),
						array(
							'name'  => __( 'Reason for ending', 'renewal-ready' ),
							'value' => $reason,
						),
					),
				);
			}
		}
		return array(
			'data' => $items,
			'done' => count( $orders ) < self::PAGE,
		);
	}

	/**
	 * Erase what isn't needed as proof. The agreement itself is kept, because the
	 * shop may need it to show what the customer agreed to pay.
	 *
	 * @param string $email Email.
	 * @param int    $page  Page.
	 * @return array
	 */
	public static function erase( $email, $page = 1 ) {
		$removed  = false;
		$retained = false;
		$messages = array();
		$orders   = self::orders( $email, $page );
		foreach ( $orders as $o ) {
			$r = $o->get_meta( Renewal_Ready_Checkout::META );
			if ( ! empty( $r['ip_hash'] ) ) {
				$r['ip_hash'] = '';
				$o->update_meta_data( Renewal_Ready_Checkout::META, $r );
				$o->save();
				$removed = true;
			}
			$retained = true;
		}
		if ( $retained ) {
			$messages[] = __( 'Subscription agreements were kept as proof of what the customer agreed to pay.', 'renewal-ready' );
		}
		$src = Renewal_Ready_Sources::active();
		if ( $src && 1 === (int) $page ) {
			foreach ( self::subs( $email ) as $id ) {
				if ( '' !== (string) $src->meta_get( $id, '_rr_reason' ) ) {
					$src->meta_set( $id, '_rr_reason', null );
					$removed = true;
				}
			}
		}
		return array(
			'items_removed'  => $removed,
			'items_retained' => $retained,
			'messages'       => $messages,
			'done'           => count( $orders ) < self::PAGE,
		);
	}
}

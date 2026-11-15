<?php
/**
 * Renewal reminder.
 *
 * @package RenewalReady
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reminder before each renewal.
 */
class Renewal_Ready_Email_Reminder extends Renewal_Ready_Email {

	/**
	 * Set up.
	 */
	public function __construct() {
		if ( empty( $this->id ) ) {
			$this->id          = 'renewal_ready_reminder';
			$this->title       = __( 'Renewal reminder (Renewal Ready)', 'renewal-ready' );
			$this->description = __( 'Sent before each renewal, inside the reminder window, with what the customer will pay and how to end it.', 'renewal-ready' );
		}
		parent::__construct();
	}

	/**
	 * Type.
	 *
	 * @return string
	 */
	public function rr_type() {
		return 'reminder';
	}

	/**
	 * Default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( 'Your {product} subscription renews on {renewal_date}: {amount}', 'renewal-ready' );
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Your renewal reminder', 'renewal-ready' );
	}

	/**
	 * Box heading.
	 *
	 * @return string
	 */
	public function box_heading() {
		return __( 'Your subscription will renew unless you end it', 'renewal-ready' );
	}

	/**
	 * Button.
	 *
	 * @return string
	 */
	public function button() {
		return __( 'End my subscription', 'renewal-ready' );
	}

	/**
	 * Rows.
	 *
	 * @return array
	 */
	public function rows() {
		$s    = $this->sub;
		$n    = $this->data;
		$amt  = $this->m( $n['amount'] );
		$rows = array();

		$rows[] = array(
			__( 'Renews', 'renewal-ready' ),
			/* translators: 1: date, 2: amount, 3: product. */
			sprintf( __( 'On %1$s you\'ll pay <strong>%2$s</strong> for %3$s.', 'renewal-ready' ), $this->d( $n['payment_date'] ), $amt, esc_html( $s['product_name'] ) ),
			false,
		);
		$prev   = isset( $n['previous_amount'] ) ? $n['previous_amount'] : null;
		if ( null !== $prev ) {
			$when   = ! empty( $s['previous_payment']['date'] ) ? $s['previous_payment']['date'] : '';
			$rows[] = array(
				__( 'Last time', 'renewal-ready' ),
				$when
					/* translators: 1: amount, 2: date. */
					? sprintf( __( 'Last time you paid %1$s on %2$s.', 'renewal-ready' ), $this->m( $prev ), $this->d( $when ) )
					/* translators: %s: amount. */
					: sprintf( __( 'Last time you paid %s.', 'renewal-ready' ), $this->m( $prev ) ),
				false,
			);
			if ( (float) $n['amount'] > (float) $prev ) {
				/* translators: %s: amount of the increase. */
				$rows[] = array( __( 'Change', 'renewal-ready' ), sprintf( __( 'This is <strong>%s more</strong> than last time.', 'renewal-ready' ), $this->m( (float) $n['amount'] - (float) $prev ) ), false );
			} elseif ( abs( (float) $n['amount'] - (float) $prev ) < 0.005 ) {
				$rows[] = array( __( 'Change', 'renewal-ready' ), esc_html__( 'This is the same as last time.', 'renewal-ready' ), false );
			}
		}
		$rows[] = array(
			__( 'After that', 'renewal-ready' ),
			/* translators: 1: amount, 2: frequency such as "month". */
			sprintf( __( 'After that you\'ll pay %1$s every %2$s.', 'renewal-ready' ), $amt, esc_html( Renewal_Ready_Plugin::frequency( $s['period'], $s['interval'] ) ) ),
			false,
		);
		$count  = (int) $s['payments_count'];
		if ( $count > 0 && ! empty( $s['since_date'] ) ) {
			$rows[] = array(
				__( 'So far', 'renewal-ready' ),
				sprintf(
					/* translators: 1: date, 2: number of payments, 3: total. */
					_n( 'Since %1$s you\'ve paid %2$d payment, %3$s in total.', 'Since %1$s you\'ve paid %2$d payments, %3$s in total.', $count, 'renewal-ready' ),
					$this->d( $s['since_date'] ),
					$count,
					$this->m( $s['paid_total'] )
				),
				false,
			);
		}
		/* translators: %s: amount. */
		$rows[] = array( __( 'If you stay', 'renewal-ready' ), sprintf( __( 'If you don\'t end it, you\'ll pay at least %s more.', 'renewal-ready' ), $amt ), false );
		$next   = Renewal_Ready_Schedule::add_period( $n['payment_date'], $s['period'], $s['interval'] );
		if ( ! empty( $s['end_date'] ) && $s['end_date'] <= $next ) {
			/* translators: %s: date. */
			$rows[] = array( __( 'Next', 'renewal-ready' ), sprintf( __( 'Your subscription ends on %s.', 'renewal-ready' ), $this->d( $s['end_date'] ) ), false );
		} else {
			/* translators: %s: date. */
			$rows[] = array( __( 'Next', 'renewal-ready' ), sprintf( __( 'Your next payment after this one is due on %s.', 'renewal-ready' ), $this->d( $next ) ), false );
		}
		/* translators: %s: date. */
		$rows[] = array( __( 'End it by', 'renewal-ready' ), '<strong>' . sprintf( __( 'To avoid this payment, end it by %s.', 'renewal-ready' ), $this->d( $n['last_cancel'] ) ) . '</strong>', true );
		return $rows;
	}

	/**
	 * Message after the box.
	 *
	 * @return string
	 */
	public function message() {
		$name = '' !== (string) $this->sub['first_name'] ? $this->sub['first_name'] : __( 'there', 'renewal-ready' );
		/* translators: 1: first name, 2: product. */
		return sprintf( __( 'Hello %1$s, this is your regular reminder about your %2$s subscription. Nothing changes if you want to keep it, and you don\'t need to do anything.', 'renewal-ready' ), $name, $this->sub['product_name'] );
	}
}

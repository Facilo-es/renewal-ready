<?php
/**
 * End-of-contract confirmation.
 *
 * @package RenewalReady
 */

defined( 'ABSPATH' ) || exit;

/**
 * Confirmation sent whenever a subscription is ended.
 */
class Renewal_Ready_Email_End extends Renewal_Ready_Email {

	/**
	 * Set up.
	 */
	public function __construct() {
		$this->id          = 'renewal_ready_end';
		$this->title       = __( 'Ending confirmation (Renewal Ready)', 'renewal-ready' );
		$this->description = __( 'Sent within minutes whenever a subscription is ended, by the customer, by the shop or by the subscription plugin.', 'renewal-ready' );
		parent::__construct();
	}

	/**
	 * Type.
	 *
	 * @return string
	 */
	public function rr_type() {
		return 'end';
	}

	/**
	 * Default subject (ended now).
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( 'Your {product} subscription has ended: confirmation', 'renewal-ready' );
	}

	/**
	 * Subject: a second wording when access carries on.
	 *
	 * @return string
	 */
	public function get_subject() {
		if ( ! empty( $this->data['continues'] ) ) {
			$subject = $this->get_option( 'subject_continues', __( 'Confirmation: your {product} subscription will end on {end_date}', 'renewal-ready' ) );
			return apply_filters( 'woocommerce_email_subject_' . $this->id, $this->format_string( $subject ), $this->object, $this ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's subject filter.
		}
		return parent::get_subject();
	}

	/**
	 * Extra setting for the second subject.
	 */
	public function init_form_fields() {
		parent::init_form_fields();
		$this->form_fields = array_merge(
			array_slice( $this->form_fields, 0, 2, true ),
			array(
				'subject_continues' => array(
					'title'       => __( 'Subject when it ends later', 'renewal-ready' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => __( 'Used when the customer can keep using the subscription until the end of the paid period.', 'renewal-ready' ),
					'placeholder' => __( 'Confirmation: your {product} subscription will end on {end_date}', 'renewal-ready' ),
					'default'     => '',
				),
			),
			array_slice( $this->form_fields, 2, null, true )
		);
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Your confirmation', 'renewal-ready' );
	}

	/**
	 * Box heading.
	 *
	 * @return string
	 */
	public function box_heading() {
		return __( 'Your subscription is ending', 'renewal-ready' );
	}

	/**
	 * Rows.
	 *
	 * @return array
	 */
	public function rows() {
		$d    = $this->data;
		$ends = sprintf(
			/* translators: %s: date. */
			__( 'Your subscription ends on <strong>%s</strong>.', 'renewal-ready' ),
			$this->d( $d['end_date'] )
		);
		if ( ! empty( $d['continues'] ) ) {
			$ends .= ' ' . esc_html__( 'You can keep using it until then.', 'renewal-ready' );
		}
		if ( ! empty( $d['paid_until'] ) ) {
			$refund = sprintf(
				/* translators: 1: date, 2: email address. */
				__( 'You had paid up to %1$s. To ask about a refund for the days left, email %2$s.', 'renewal-ready' ),
				$this->d( $d['paid_until'] ),
				esc_html( Renewal_Ready_Plugin::contact_email() )
			);
		} else {
			$refund = esc_html__( 'No refund is due.', 'renewal-ready' );
		}
		return array(
			/* translators: %s: date. */
			array( __( 'Received', 'renewal-ready' ), sprintf( __( 'We received your request on %s.', 'renewal-ready' ), $this->d( $d['request_date'] ) ), false ),
			array( __( 'Ends', 'renewal-ready' ), $ends, false ),
			array( __( 'Payments', 'renewal-ready' ), esc_html__( 'We won\'t take any more payments.', 'renewal-ready' ), false ),
			array( __( 'Refund', 'renewal-ready' ), $refund, true ),
		);
	}

	/**
	 * Message after the box.
	 *
	 * @return string
	 */
	public function message() {
		/* translators: %s: email address. */
		return sprintf( __( 'Keep this email as your confirmation. If you didn\'t ask for this, reply or email %s straight away.', 'renewal-ready' ), Renewal_Ready_Plugin::contact_email() );
	}
}

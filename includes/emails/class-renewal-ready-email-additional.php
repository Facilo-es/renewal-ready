<?php
/**
 * Early reminder for yearly plans.
 *
 * @package RenewalReady
 */

defined( 'ABSPATH' ) || exit;

/**
 * Early reminder before a yearly renewal.
 */
class Renewal_Ready_Email_Additional extends Renewal_Ready_Email_Reminder {

	/**
	 * Set up.
	 */
	public function __construct() {
		$this->id          = 'renewal_ready_additional';
		$this->title       = __( 'Early yearly reminder (Renewal Ready)', 'renewal-ready' );
		$this->description = __( 'Sent before the regular reminder for plans that renew once a year or less often.', 'renewal-ready' );
		parent::__construct();
	}

	/**
	 * Type.
	 *
	 * @return string
	 */
	public function rr_type() {
		return 'additional';
	}

	/**
	 * Default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( 'Early reminder: your yearly {product} subscription renews on {renewal_date}', 'renewal-ready' );
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Your early renewal reminder', 'renewal-ready' );
	}
}

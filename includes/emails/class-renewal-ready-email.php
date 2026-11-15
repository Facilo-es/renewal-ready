<?php
/**
 * Shared parts of the Renewal Ready emails.
 *
 * @package RenewalReady
 */

defined( 'ABSPATH' ) || exit;

/**
 * Base email: a statutory box first, then a short message.
 */
abstract class Renewal_Ready_Email extends WC_Email {

	/**
	 * Subscription record.
	 *
	 * @var array
	 */
	public $sub = array();

	/**
	 * Notice data.
	 *
	 * @var array
	 */
	public $data = array();

	/**
	 * Set up.
	 */
	public function __construct() {
		$this->customer_email = true;
		$this->template_base  = RENEWAL_READY_DIR . 'templates/';
		$this->template_html  = 'emails/renewal-ready-notice.php';
		$this->template_plain = 'emails/plain/renewal-ready-notice.php';
		$this->placeholders   = array(
			'{product}'      => '',
			'{renewal_date}' => '',
			'{amount}'       => '',
			'{end_date}'     => '',
		);
		parent::__construct();
	}

	/**
	 * Box heading.
	 *
	 * @return string
	 */
	abstract public function box_heading();

	/**
	 * Box rows: list of array( label, html, highlight ).
	 *
	 * @return array
	 */
	abstract public function rows();

	/**
	 * Text after the box.
	 *
	 * @return string
	 */
	abstract public function message();

	/**
	 * Button text, or '' for none.
	 *
	 * @return string
	 */
	public function button() {
		return '';
	}

	/**
	 * Fill the email for one subscription.
	 *
	 * @param array $sub  Subscription record.
	 * @param array $data Notice data.
	 */
	public function prepare( array $sub, array $data ) {
		$this->sub                            = $sub;
		$this->data                           = $data;
		$this->recipient                      = isset( $sub['email'] ) ? (string) $sub['email'] : '';
		$this->placeholders['{product}']      = (string) $sub['product_name'];
		$this->placeholders['{renewal_date}'] = ! empty( $data['payment_date'] ) ? Renewal_Ready_Plugin::date_label( $data['payment_date'] ) : '';
		$this->placeholders['{amount}']       = isset( $data['amount'] ) ? Renewal_Ready_Plugin::money_text( $data['amount'], $sub['currency'] ) : '';
		$this->placeholders['{end_date}']     = ! empty( $data['end_date'] ) ? Renewal_Ready_Plugin::date_label( $data['end_date'] ) : '';
	}

	/**
	 * Send to the customer (or to another address for a test).
	 *
	 * @param array  $sub  Subscription record.
	 * @param array  $data Notice data.
	 * @param string $to   Override recipient.
	 * @return bool
	 */
	public function trigger( array $sub, array $data, $to = '' ) {
		$this->setup_locale();
		$this->prepare( $sub, $data );
		if ( $to ) {
			$this->recipient = $to;
		}
		$ok = false;
		if ( $this->get_recipient() ) {
			$ok = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}
		$this->restore_locale();
		return (bool) $ok;
	}

	/**
	 * Everything the template needs.
	 *
	 * @param bool $plain Plain text.
	 * @return array
	 */
	public function template_args( $plain ) {
		$phone = (string) Renewal_Ready_Plugin::get( 'contact_phone' );
		$email = Renewal_Ready_Plugin::contact_email();
		if ( '' !== $phone ) {
			/* translators: 1: email address, 2: phone number. */
			$or = sprintf( __( 'Or go to My Account › Subscriptions. Questions? Email %1$s or call %2$s.', 'renewal-ready' ), $email, $phone );
		} else {
			/* translators: %s: email address. */
			$or = sprintf( __( 'Or go to My Account › Subscriptions. Questions? Email %s.', 'renewal-ready' ), $email );
		}
		$url  = class_exists( 'Renewal_Ready_Exit' ) && ! empty( $this->sub['id'] ) ? Renewal_Ready_Exit::url( (int) $this->sub['id'] ) : wc_get_page_permalink( 'myaccount' );
		$args = array(
			'email'              => $this,
			'email_heading'      => $this->get_heading(),
			'box_heading'        => $this->box_heading(),
			'rows'               => $this->rows(),
			/**
			 * Link behind the "End my subscription" button.
			 *
			 * @param string $url URL.
			 * @param array  $sub Subscription record.
			 */
			'button_url'         => (string) apply_filters( 'renewal_ready_end_url', $url, $this->sub ),
			'button'             => $this->button(),
			'or_line'            => $this->button() ? $or : '',
			'message'            => $this->message(),
			'exit_url'           => Renewal_Ready_Plugin::exit_url(),
			'additional_content' => $this->get_additional_content(),
			'sent_to_admin'      => false,
			'plain_text'         => $plain,
		);
		/**
		 * Change what goes into a Renewal Ready email.
		 *
		 * @param array  $args Template args.
		 * @param string $type reminder, additional or end.
		 * @param array  $sub  Subscription record.
		 * @param array  $data Notice data.
		 */
		return apply_filters( 'renewal_ready_email_args', $args, $this->rr_type(), $this->sub, $this->data );
	}

	/**
	 * Short type.
	 *
	 * @return string
	 */
	abstract public function rr_type();

	/**
	 * HTML content.
	 *
	 * @return string
	 */
	public function get_content_html() {
		return wc_get_template_html( $this->template_html, $this->template_args( false ), '', $this->template_base );
	}

	/**
	 * Plain content.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		return wc_get_template_html( $this->template_plain, $this->template_args( true ), '', $this->template_base );
	}

	/**
	 * Money for a row.
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	protected function m( $amount ) {
		return Renewal_Ready_Plugin::money( $amount, isset( $this->sub['currency'] ) ? $this->sub['currency'] : '' );
	}

	/**
	 * Date for a row.
	 *
	 * @param string $ymd Date.
	 * @return string
	 */
	protected function d( $ymd ) {
		return esc_html( Renewal_Ready_Plugin::date_label( $ymd ) );
	}
}

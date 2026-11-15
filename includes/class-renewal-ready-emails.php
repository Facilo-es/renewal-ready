<?php
/**
 * WooCommerce email registration, preview and test sends.
 *
 * @package RenewalReady
 */

defined( 'ABSPATH' ) || exit;

/**
 * Email helper.
 */
class Renewal_Ready_Emails {

	/**
	 * Our email ids by type.
	 *
	 * @var array
	 */
	const IDS = array(
		'reminder'   => 'Renewal_Ready_Email_Reminder',
		'additional' => 'Renewal_Ready_Email_Additional',
		'end'        => 'Renewal_Ready_Email_End',
	);

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_email_classes', array( __CLASS__, 'register' ) );
		add_filter( 'woocommerce_email_styles', array( __CLASS__, 'styles' ), 10, 2 );
	}

	/**
	 * Register the three emails.
	 *
	 * @param array $emails Emails.
	 * @return array
	 */
	public static function register( $emails ) {
		$dir = RENEWAL_READY_DIR . 'includes/emails/';
		require_once $dir . 'class-renewal-ready-email.php';
		require_once $dir . 'class-renewal-ready-email-reminder.php';
		require_once $dir . 'class-renewal-ready-email-additional.php';
		require_once $dir . 'class-renewal-ready-email-end.php';
		foreach ( self::IDS as $class ) {
			$emails[ $class ] = new $class();
		}
		return $emails;
	}

	/**
	 * Email object by type.
	 *
	 * @param string $type reminder, additional or end.
	 * @return Renewal_Ready_Email|null
	 */
	public static function get( $type ) {
		if ( ! isset( self::IDS[ $type ] ) || ! function_exists( 'WC' ) ) {
			return null;
		}
		$all   = WC()->mailer()->get_emails();
		$class = self::IDS[ $type ];
		return isset( $all[ $class ] ) ? $all[ $class ] : null;
	}

	/**
	 * Box styles, added only to our emails (WooCommerce inlines them).
	 *
	 * @param string        $css   CSS.
	 * @param WC_Email|null $email Email.
	 * @return string
	 */
	public static function styles( $css, $email = null ) {
		if ( ! $email instanceof Renewal_Ready_Email ) {
			return $css;
		}
		$css .= '
.rr-box{border:4px solid #1d2327;border-collapse:separate;margin:0 0 18px;background:#ffffff}
.rr-box-in{padding:16px 18px}
.rr-box-h{margin:0 0 10px;font-size:20px;line-height:1.25;font-weight:800;color:#1d2327}
.rr-rows{border-collapse:collapse}
.rr-dt{width:96px;padding:8px 12px 8px 0;text-align:left;vertical-align:top;font-weight:700;font-size:14px;color:#1d2327;border-bottom:1px solid #dcdcde}
.rr-dd{padding:8px 0;vertical-align:top;font-size:14px;line-height:1.45;color:#1d2327;border-bottom:1px solid #dcdcde}
.rr-key .rr-dt,.rr-key .rr-dd{background:#ffbf47;font-size:16px;padding-top:11px;padding-bottom:11px;border-bottom:0}
.rr-key .rr-dt{padding-left:10px}
.rr-btn-p{margin:16px 0 0}
.rr-btn{display:block;padding:14px;background:#1d2327;color:#ffffff !important;text-align:center;font-weight:700;font-size:16px;text-decoration:none}
.rr-or{margin:10px 0 0;font-size:13px;color:#50575e}
.rr-p{font-size:15px;line-height:1.55}
.rr-foot{margin-top:18px;padding-top:12px;border-top:1px solid #dcdcde;font-size:12px;color:#50575e}';
		return $css;
	}

	/**
	 * A sample subscription for previews and tests when the shop has none.
	 *
	 * @return array
	 */
	public static function sample_sub() {
		$today = Renewal_Ready_Plugin::today();
		return array(
			'id'               => 0,
			'source'           => 'sample',
			'email'            => '',
			'first_name'       => __( 'Sam', 'renewal-ready' ),
			'product_name'     => __( 'Coffee club', 'renewal-ready' ),
			'currency'         => get_woocommerce_currency(),
			'period'           => 'month',
			'interval'         => 1,
			'recurring_amount' => 12.0,
			'previous_payment' => array(
				'amount' => 10.0,
				'date'   => Renewal_Ready_Schedule::add_months( $today, -1 ),
			),
			'payments_count'   => 6,
			'paid_total'       => 60.0,
			'since_date'       => Renewal_Ready_Schedule::add_months( $today, -6 ),
			'end_date'         => null,
			'view_url'         => wc_get_page_permalink( 'myaccount' ),
		);
	}

	/**
	 * Sample notice data for a type and subscription.
	 *
	 * @param string $type Type.
	 * @param array  $sub  Subscription.
	 * @return array
	 */
	public static function sample_data( $type, array $sub ) {
		$today = Renewal_Ready_Plugin::today();
		if ( 'end' === $type ) {
			$end = ! empty( $sub['next_payment_date'] ) ? $sub['next_payment_date'] : Renewal_Ready_Schedule::add_days( $today, 20 );
			return array(
				'key'          => '',
				'type'         => 'end',
				'request_date' => $today,
				'end_date'     => $end,
				'continues'    => true,
				'paid_until'   => '',
				'late'         => false,
			);
		}
		$settings = Renewal_Ready_Plugin::timing();
		$pay      = ! empty( $sub['next_payment_date'] ) && $sub['next_payment_date'] > $today ? $sub['next_payment_date'] : Renewal_Ready_Schedule::add_days( $today, 21 );
		$prev     = ! empty( $sub['previous_payment']['amount'] ) ? (float) $sub['previous_payment']['amount'] : null;
		$amount   = (float) $sub['recurring_amount'];
		return array_merge(
			Renewal_Ready_Schedule::window( $pay, $settings, $type ),
			array(
				'key'             => '',
				'type'            => $type,
				'payment_date'    => $pay,
				'amount'          => $amount,
				'previous_amount' => $prev,
				'increase'        => null !== $prev && $amount > $prev,
				'late'            => false,
			)
		);
	}

	/**
	 * Pick a subscription for previews: the first active one, or the sample.
	 *
	 * @return array
	 */
	public static function preview_sub() {
		$src = Renewal_Ready_Sources::active();
		if ( $src ) {
			foreach ( $src->ids( 'active', 0, 1 ) as $id ) {
				$sub = $src->get( $id );
				if ( $sub ) {
					return $sub;
				}
			}
		}
		return self::sample_sub();
	}

	/**
	 * HTML preview of one email, as the customer would see it.
	 *
	 * @param string $type Type.
	 * @return string
	 */
	public static function preview_html( $type ) {
		$email = self::get( $type );
		if ( ! $email ) {
			return '';
		}
		$sub = self::preview_sub();
		$email->prepare( $sub, self::sample_data( $type, $sub ) );
		return $email->style_inline( $email->get_content_html() );
	}

	/**
	 * Send a test of one email to an address.
	 *
	 * @param string $type Type.
	 * @param string $to   Address.
	 * @return bool
	 */
	public static function send_test( $type, $to ) {
		$email = self::get( $type );
		if ( ! $email || ! is_email( $to ) ) {
			return false;
		}
		$sub = self::preview_sub();
		$was = $email->enabled;
		// A test always goes out, even while the email is switched off.
		$email->enabled = 'yes';
		$ok             = $email->trigger( $sub, self::sample_data( $type, $sub ), $to );
		$email->enabled = $was;
		if ( $ok ) {
			Renewal_Ready_Plugin::set_state( 'test_sent', time() );
		}
		return $ok;
	}
}

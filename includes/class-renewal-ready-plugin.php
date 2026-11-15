<?php
/**
 * Bootstrap, settings and small shared helpers.
 *
 * @package RenewalReady
 */

defined( 'ABSPATH' ) || exit;

/**
 * Loads the parts of the plugin and keeps its settings.
 */
final class Renewal_Ready_Plugin {

	const OPTION = 'renewal_ready_settings';
	const STATE  = 'renewal_ready_state';

	/**
	 * Settings cache.
	 *
	 * @var array|null
	 */
	private static $settings = null;

	/**
	 * Hook everything up.
	 */
	public static function boot() {
		add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_compatibility' ) );
		add_action( 'plugins_loaded', array( __CLASS__, 'load' ), 20 );
	}

	/**
	 * HPOS and Cart/Checkout Blocks.
	 */
	public static function declare_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', RENEWAL_READY_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', RENEWAL_READY_FILE, true );
		}
	}

	/**
	 * Load the parts once WooCommerce is there.
	 */
	public static function load() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		$dir   = RENEWAL_READY_DIR . 'includes/';
		$files = array(
			'sources/interface-renewal-ready-source.php',
			'sources/class-renewal-ready-source-base.php',
			'sources/class-renewal-ready-source-wps.php',
			'sources/class-renewal-ready-source-yith.php',
			'sources/class-renewal-ready-source-wcs.php',
			'class-renewal-ready-sources.php',
			'class-renewal-ready-terms.php',
			'class-renewal-ready-notice-log.php',
			'class-renewal-ready-emails.php',
			'class-renewal-ready-runner.php',
			'checkout/class-renewal-ready-checkout.php',
			'checkout/class-renewal-ready-checkout-classic.php',
			'checkout/class-renewal-ready-checkout-blocks.php',
			'account/class-renewal-ready-exit.php',
			'class-renewal-ready-privacy.php',
			'admin/class-renewal-ready-readiness.php',
			'admin/class-renewal-ready-admin.php',
		);
		foreach ( $files as $file ) {
			require_once $dir . $file;
		}
		Renewal_Ready_Sources::init();
		Renewal_Ready_Emails::init();
		Renewal_Ready_Runner::init();
		Renewal_Ready_Checkout::init();
		Renewal_Ready_Exit::init();
		Renewal_Ready_Privacy::init();
		Renewal_Ready_Admin::init();
	}

	/**
	 * Activation: remember when, and flush rewrite rules on the next load.
	 */
	public static function activate() {
		$state = get_option( self::STATE, array() );
		if ( ! is_array( $state ) ) {
			$state = array();
		}
		if ( empty( $state['installed'] ) ) {
			$state['installed'] = time();
		}
		$state['flush'] = 1;
		update_option( self::STATE, $state, false );
		delete_option( 'renewal_ready_scheduled' );
	}

	/**
	 * Deactivation: stop the daily run.
	 */
	public static function deactivate() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'renewal_ready_daily' );
			as_unschedule_all_actions( 'renewal_ready_batch' );
		}
		delete_option( 'renewal_ready_scheduled' );
		flush_rewrite_rules();
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array_merge(
			Renewal_Ready_Schedule::defaults(),
			array(
				'mode'          => 'preview',
				'customers'     => 'all',
				'exit_page'     => 0,
				'contact_email' => '',
				'contact_phone' => '',
				'tick'          => 'yes',
				'button_label'  => 'yes',
				'product_box'   => 'no',
				'skip_company'  => 'no',
				'delete_data'   => 'no',
			)
		);
	}

	/**
	 * All settings, with defaults.
	 *
	 * @return array
	 */
	public static function settings() {
		if ( null === self::$settings ) {
			$saved          = get_option( self::OPTION, array() );
			self::$settings = array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
		}
		return self::$settings;
	}

	/**
	 * One setting.
	 *
	 * @param string $key Setting name.
	 * @return mixed
	 */
	public static function get( $key ) {
		$s = self::settings();
		return isset( $s[ $key ] ) ? $s[ $key ] : null;
	}

	/**
	 * Save settings (merged into the current ones).
	 *
	 * @param array $values New values.
	 */
	public static function update( array $values ) {
		$s = array_merge( self::settings(), $values );
		update_option( self::OPTION, $s );
		self::$settings = $s;
	}

	/**
	 * Whether notices really go out.
	 *
	 * @return bool
	 */
	public static function is_live() {
		return 'live' === self::get( 'mode' );
	}

	/**
	 * Timing settings for Renewal_Ready_Schedule.
	 *
	 * @return array
	 */
	public static function timing() {
		$s   = self::settings();
		$out = array();
		foreach ( array_keys( Renewal_Ready_Schedule::defaults() ) as $k ) {
			$out[ $k ] = (int) $s[ $k ];
		}
		return $out;
	}

	/**
	 * Check timing settings: the schedule rules plus a 1 to 60 day window.
	 *
	 * @param array $t Timing settings.
	 * @return string Empty when fine.
	 */
	public static function validate_timing( array $t ) {
		$err = Renewal_Ready_Schedule::validate( $t );
		if ( '' !== $err ) {
			return $err;
		}
		foreach ( array( 'window_max', 'window_min' ) as $k ) {
			if ( (int) $t[ $k ] < 1 || (int) $t[ $k ] > 60 ) {
				return 'window_range';
			}
		}
		return '';
	}

	/**
	 * Read a value from the plugin's own state.
	 *
	 * @param string $key     Key.
	 * @param mixed  $fallback Default.
	 * @return mixed
	 */
	public static function state( $key, $fallback = null ) {
		$state = get_option( self::STATE, array() );
		return is_array( $state ) && isset( $state[ $key ] ) ? $state[ $key ] : $fallback;
	}

	/**
	 * Write a value to the plugin's own state.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value, null to remove.
	 */
	public static function set_state( $key, $value ) {
		$state = get_option( self::STATE, array() );
		if ( ! is_array( $state ) ) {
			$state = array();
		}
		if ( null === $value ) {
			unset( $state[ $key ] );
		} else {
			$state[ $key ] = $value;
		}
		update_option( self::STATE, $state, false );
	}

	/**
	 * Today in the site's time zone (Y-m-d).
	 *
	 * @return string
	 */
	public static function today() {
		return (string) apply_filters( 'renewal_ready_today', wp_date( 'Y-m-d' ) );
	}

	/**
	 * A Y-m-d date as the customer reads it (j F Y, site language).
	 *
	 * @param string $ymd Date.
	 * @return string
	 */
	public static function date_label( $ymd ) {
		if ( ! $ymd ) {
			return '';
		}
		try {
			$dt = new DateTimeImmutable( $ymd . ' 12:00:00', wp_timezone() );
		} catch ( Exception $e ) {
			return (string) $ymd;
		}
		return wp_date( 'j F Y', $dt->getTimestamp() );
	}

	/**
	 * Money for HTML, in the shop's format.
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency Currency code.
	 * @return string
	 */
	public static function money( $amount, $currency = '' ) {
		$args = $currency ? array( 'currency' => $currency ) : array();
		return '<span class="rr-num">' . wc_price( (float) $amount, $args ) . '</span>';
	}

	/**
	 * Money as plain text.
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency Currency code.
	 * @return string
	 */
	public static function money_text( $amount, $currency = '' ) {
		$args = $currency ? array( 'currency' => $currency ) : array();
		return html_entity_decode( wp_strip_all_tags( wc_price( (float) $amount, $args ) ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * "month", "3 months", "year" and so on.
	 *
	 * @param string $period   day, week, month or year.
	 * @param int    $interval Interval.
	 * @return string
	 */
	public static function frequency( $period, $interval ) {
		$interval = max( 1, (int) $interval );
		if ( 1 === $interval ) {
			switch ( $period ) {
				case 'day':
					return __( 'day', 'renewal-ready' );
				case 'week':
					return __( 'week', 'renewal-ready' );
				case 'year':
					return __( 'year', 'renewal-ready' );
				default:
					return __( 'month', 'renewal-ready' );
			}
		}
		return self::length( $interval, $period );
	}

	/**
	 * "14 days", "1 month", "2 years".
	 *
	 * @param int    $n    Count.
	 * @param string $unit day, week, month or year.
	 * @return string
	 */
	public static function length( $n, $unit ) {
		$n = (int) $n;
		switch ( $unit ) {
			case 'day':
				/* translators: %d: number of days. */
				return sprintf( _n( '%d day', '%d days', $n, 'renewal-ready' ), $n );
			case 'week':
				/* translators: %d: number of weeks. */
				return sprintf( _n( '%d week', '%d weeks', $n, 'renewal-ready' ), $n );
			case 'year':
				/* translators: %d: number of years. */
				return sprintf( _n( '%d year', '%d years', $n, 'renewal-ready' ), $n );
			default:
				/* translators: %d: number of months. */
				return sprintf( _n( '%d month', '%d months', $n, 'renewal-ready' ), $n );
		}
	}

	/**
	 * Contact email for customers: the setting, or the shop's From address.
	 *
	 * @return string
	 */
	public static function contact_email() {
		$email = (string) self::get( 'contact_email' );
		if ( '' === $email ) {
			$email = (string) get_option( 'woocommerce_email_from_address', get_option( 'admin_email' ) );
		}
		return $email;
	}

	/**
	 * Exit page URL, or '' when none is published.
	 *
	 * @return string
	 */
	public static function exit_url() {
		$id = (int) self::get( 'exit_page' );
		if ( $id && 'publish' === get_post_status( $id ) ) {
			return (string) get_permalink( $id );
		}
		return '';
	}

	/**
	 * Plain URL of an asset.
	 *
	 * @param string $path Relative path.
	 * @return string
	 */
	public static function asset( $path ) {
		return RENEWAL_READY_URL . 'assets/' . $path;
	}

	/**
	 * Hash an IP address so it can't be read back.
	 *
	 * @param string $ip IP.
	 * @return string
	 */
	public static function hash_ip( $ip ) {
		return $ip ? hash_hmac( 'sha256', (string) $ip, wp_salt( 'auth' ) ) : '';
	}
}

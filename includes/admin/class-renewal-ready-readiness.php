<?php
/**
 * The readiness check: twelve things a shop should have in place.
 *
 * @package RenewalReady
 */

defined( 'ABSPATH' ) || exit;

/**
 * Each check returns key, status (ok, warn or bad), title, detail and an optional fix.
 */
class Renewal_Ready_Readiness {

	/**
	 * Run every check.
	 *
	 * @return array
	 */
	public static function items() {
		$items = array(
			self::source(),
			self::live(),
			self::box(),
			self::tick(),
			self::exit_page(),
			self::window(),
			self::daily(),
			self::test(),
			self::contact(),
			self::terms(),
			self::trials(),
			self::late(),
		);
		/**
		 * Add or change readiness checks.
		 *
		 * @param array $items Items with key, status, title, detail, fix (action), fix_label, url.
		 */
		$items = (array) apply_filters( 'renewal_ready_readiness_items', $items );
		$out   = array();
		foreach ( $items as $i ) {
			if ( is_array( $i ) && isset( $i['key'], $i['status'], $i['title'] ) ) {
				$out[] = wp_parse_args(
					$i,
					array(
						'detail'    => '',
						'fix'       => '',
						'fix_label' => '',
						'url'       => '',
					)
				);
			}
		}
		return $out;
	}

	/**
	 * One item.
	 *
	 * @param string $key    Key.
	 * @param string $status ok, warn or bad.
	 * @param string $title  Title.
	 * @param string $detail Detail or fix line.
	 * @param string $fix    Admin action, or ''.
	 * @param string $label  Button label.
	 * @param string $url    Link instead of an action.
	 * @return array
	 */
	private static function item( $key, $status, $title, $detail, $fix = '', $label = '', $url = '' ) {
		return array(
			'key'       => $key,
			'status'    => $status,
			'title'     => $title,
			'detail'    => $detail,
			'fix'       => $fix,
			'fix_label' => $label,
			'url'       => $url,
		);
	}

	/**
	 * Source plugin found.
	 *
	 * @return array
	 */
	private static function source() {
		$t   = __( 'Subscription plugin found', 'renewal-ready' );
		$src = Renewal_Ready_Sources::active();
		if ( ! $src ) {
			return self::item( 'source', 'bad', $t, __( 'Install WP Swings Subscriptions or YITH WooCommerce Subscription. Renewal Ready works with their subscriptions.', 'renewal-ready' ), '', __( 'Find a plugin', 'renewal-ready' ), admin_url( 'plugin-install.php?s=woocommerce+subscriptions&tab=search&type=term' ) );
		}
		return self::item( 'source', 'ok', $t, $src->label() );
	}

	/**
	 * Live mode.
	 *
	 * @return array
	 */
	private static function live() {
		$t = __( 'Live mode', 'renewal-ready' );
		if ( Renewal_Ready_Plugin::is_live() ) {
			return self::item( 'live', 'ok', $t, __( 'Reminders and confirmations go to your customers.', 'renewal-ready' ) );
		}
		return self::item( 'live', 'warn', $t, __( 'You\'re in Preview mode. Customers get nothing yet.', 'renewal-ready' ), 'golive', __( 'Go live', 'renewal-ready' ) );
	}

	/**
	 * Key-information box on the checkout page.
	 *
	 * @return array
	 */
	private static function box() {
		$t = __( 'Key information box on checkout', 'renewal-ready' );
		switch ( Renewal_Ready_Checkout_Blocks::placement() ) {
			case 'yes':
				return self::item( 'box', 'ok', $t, __( 'Found on your block checkout, above the button.', 'renewal-ready' ) );
			case 'classic':
				return self::item( 'box', 'ok', $t, __( 'Shown on your checkout, above the button.', 'renewal-ready' ) );
			case 'forced':
				return self::item( 'box', 'warn', $t, __( 'Your block checkout shows it, but not in the best place. Put it just above the button.', 'renewal-ready' ), 'place_box', __( 'Place it', 'renewal-ready' ) );
		}
		return self::item( 'box', 'bad', $t, __( 'We couldn\'t find your checkout page. Set one in WooCommerce › Settings › Advanced.', 'renewal-ready' ), '', __( 'Open settings', 'renewal-ready' ), admin_url( 'admin.php?page=wc-settings&tab=advanced' ) );
	}

	/**
	 * Payment tick.
	 *
	 * @return array
	 */
	private static function tick() {
		$t = __( 'Payment tick at checkout', 'renewal-ready' );
		if ( Renewal_Ready_Checkout::tick_on() ) {
			$d = 'yes' === Renewal_Ready_Plugin::get( 'button_label' )
				? __( 'On, with "Subscribe and pay" on the button.', 'renewal-ready' )
				: __( 'On.', 'renewal-ready' );
			return self::item( 'tick', 'ok', $t, $d );
		}
		return self::item( 'tick', 'bad', $t, __( 'Off. Without it, customers may not be bound to pay.', 'renewal-ready' ), 'tick_on', __( 'Turn it on', 'renewal-ready' ) );
	}

	/**
	 * Exit page published and linked.
	 *
	 * @return array
	 */
	private static function exit_page() {
		$t   = __( 'Exit page published and linked', 'renewal-ready' );
		$url = Renewal_Ready_Plugin::exit_url();
		if ( '' === $url ) {
			return self::item( 'exit', 'bad', $t, __( 'Create your "How to end your subscription" page. We link it from every notice and from the checkout box.', 'renewal-ready' ), 'create_page', __( 'Create page', 'renewal-ready' ) );
		}
		return self::item( 'exit', 'ok', $t, get_the_title( (int) Renewal_Ready_Plugin::get( 'exit_page' ) ), '', '', '' );
	}

	/**
	 * Reminder window.
	 *
	 * @return array
	 */
	private static function window() {
		$t   = __( 'Reminder window', 'renewal-ready' );
		$tim = Renewal_Ready_Plugin::timing();
		if ( '' !== Renewal_Ready_Plugin::validate_timing( $tim ) ) {
			return self::item( 'window', 'bad', $t, __( 'Set a window of 1 to 60 days, with the first day before the last.', 'renewal-ready' ), '', __( 'Open settings', 'renewal-ready' ), Renewal_Ready_Admin::url( 'settings' ) );
		}
		/* translators: 1: days, 2: days. */
		return self::item( 'window', 'ok', $t, sprintf( __( '%1$d to %2$d days before the last day to end it.', 'renewal-ready' ), $tim['window_max'], $tim['window_min'] ) );
	}

	/**
	 * The daily run.
	 *
	 * @return array
	 */
	private static function daily() {
		$t    = __( 'Daily run', 'renewal-ready' );
		$last = (int) Renewal_Ready_Plugin::state( 'last_run', 0 );
		if ( $last && $last > time() - 26 * HOUR_IN_SECONDS ) {
			/* translators: %s: time ago. */
			return self::item( 'daily', 'ok', $t, sprintf( __( 'Last run %s ago.', 'renewal-ready' ), human_time_diff( $last ) ) );
		}
		$d = $last
			/* translators: %s: time ago. */
			? sprintf( __( 'Last run %s ago. Check that WP-Cron or Action Scheduler is running.', 'renewal-ready' ), human_time_diff( $last ) )
			: __( 'It hasn\'t run yet. It runs every day at 08:00, or you can run it now.', 'renewal-ready' );
		return self::item( 'daily', $last ? 'bad' : 'warn', $t, $d, 'run_now', __( 'Run now', 'renewal-ready' ) );
	}

	/**
	 * Email test sent.
	 *
	 * @return array
	 */
	private static function test() {
		$t = __( 'Email test sent', 'renewal-ready' );
		if ( Renewal_Ready_Plugin::state( 'test_sent' ) ) {
			return self::item( 'test', 'ok', $t, __( 'You\'ve seen what customers get.', 'renewal-ready' ) );
		}
		return self::item( 'test', 'warn', $t, __( 'Send yourself one of each notice to see what customers get.', 'renewal-ready' ), 'test_all', __( 'Send me a test', 'renewal-ready' ) );
	}

	/**
	 * Contact email.
	 *
	 * @return array
	 */
	private static function contact() {
		$t = __( 'Contact email', 'renewal-ready' );
		if ( '' !== (string) Renewal_Ready_Plugin::get( 'contact_email' ) ) {
			return self::item( 'contact', 'ok', $t, Renewal_Ready_Plugin::contact_email() );
		}
		/* translators: %s: email address. */
		return self::item( 'contact', 'warn', $t, sprintf( __( 'Not set, so customers see %s. Set the address that answers subscription questions.', 'renewal-ready' ), Renewal_Ready_Plugin::contact_email() ), '', __( 'Open settings', 'renewal-ready' ), Renewal_Ready_Admin::url( 'settings' ) );
	}

	/**
	 * Terms page.
	 *
	 * @return array
	 */
	private static function terms() {
		$t  = __( 'Terms page', 'renewal-ready' );
		$id = function_exists( 'wc_terms_and_conditions_page_id' ) ? (int) wc_terms_and_conditions_page_id() : 0;
		if ( $id && 'publish' === get_post_status( $id ) ) {
			return self::item( 'terms', 'ok', $t, get_the_title( $id ) );
		}
		return self::item( 'terms', 'warn', $t, __( 'Set your terms and conditions page in WooCommerce, so the checkout box can link to it.', 'renewal-ready' ), '', __( 'Open settings', 'renewal-ready' ), admin_url( 'admin.php?page=wc-settings&tab=advanced' ) );
	}

	/**
	 * Products whose trial is shorter than the reminder window.
	 *
	 * @return array Names.
	 */
	public static function short_trials() {
		$src = Renewal_Ready_Sources::active();
		if ( ! $src ) {
			return array();
		}
		$max   = (int) Renewal_Ready_Plugin::get( 'window_max' ) + (int) Renewal_Ready_Plugin::get( 'notice_days' );
		$names = array();
		$ids   = get_posts(
			array(
				'post_type'      => array( 'product', 'product_variation' ),
				'post_status'    => 'publish',
				'posts_per_page' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- One admin check over subscription products, ids only.
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		foreach ( $ids as $pid ) {
			$t = Renewal_Ready_Terms::for_product( wc_get_product( $pid ) );
			if ( is_array( $t ) && (int) $t['trial_days'] > 0 && (int) $t['trial_days'] < $max ) {
				/* translators: 1: product name, 2: trial days. */
				$names[] = sprintf( __( '%1$s (%2$d-day trial)', 'renewal-ready' ), $t['name'], (int) $t['trial_days'] );
			}
		}
		return array_unique( $names );
	}

	/**
	 * Trials shorter than the window.
	 *
	 * @return array
	 */
	private static function trials() {
		$t     = __( 'Trials shorter than your reminder window', 'renewal-ready' );
		$names = self::short_trials();
		if ( ! $names ) {
			return self::item( 'trials', 'ok', $t, __( 'None.', 'renewal-ready' ) );
		}
		/* translators: %s: product list. */
		return self::item( 'trials', 'warn', $t, sprintf( __( '%s. The reminder will go out late for these. Lengthen the trial or shorten the window.', 'renewal-ready' ), implode( ', ', $names ) ), '', __( 'Open settings', 'renewal-ready' ), Renewal_Ready_Admin::url( 'settings' ) );
	}

	/**
	 * Late notices in the last 30 days.
	 *
	 * @return array
	 */
	private static function late() {
		$t    = __( 'Late notices, last 30 days', 'renewal-ready' );
		$last = (int) Renewal_Ready_Plugin::state( 'last_late', 0 );
		if ( $last && $last > time() - 30 * DAY_IN_SECONDS ) {
			/* translators: %s: time ago. */
			return self::item( 'late', 'warn', $t, sprintf( __( 'A reminder went out after its window %s ago. Check the daily run and your trial lengths.', 'renewal-ready' ), human_time_diff( $last ) ) );
		}
		return self::item( 'late', 'ok', $t, __( 'None.', 'renewal-ready' ) );
	}

	/**
	 * Count of items that are ok.
	 *
	 * @param array $items Items.
	 * @return int
	 */
	public static function ok_count( array $items ) {
		return count(
			array_filter(
				$items,
				function ( $i ) {
					return 'ok' === $i['status'];
				}
			)
		);
	}
}

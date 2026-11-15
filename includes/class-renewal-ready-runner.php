<?php
/**
 * The daily run: works out which reminders are due and sends them.
 *
 * @package RenewalReady
 */

defined( 'ABSPATH' ) || exit;

/**
 * Action Scheduler jobs plus the per-subscription recompute.
 */
class Renewal_Ready_Runner {

	const DAILY = 'renewal_ready_daily';
	const BATCH = 'renewal_ready_batch';
	const GROUP = 'renewal-ready';
	const SIZE  = 50;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( self::DAILY, array( __CLASS__, 'daily' ) );
		add_action( self::BATCH, array( __CLASS__, 'batch' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'ensure_schedule' ), 30 );
	}

	/**
	 * Next 08:00 in site time, as a UTC timestamp.
	 *
	 * @return int
	 */
	public static function next_eight() {
		$tz  = wp_timezone();
		$now = new DateTimeImmutable( 'now', $tz );
		$at  = $now->setTime( 8, 0 );
		if ( $at <= $now ) {
			$at = $at->modify( '+1 day' );
		}
		return $at->getTimestamp();
	}

	/**
	 * Make sure the daily job is scheduled (checked at most once an hour).
	 */
	public static function ensure_schedule() {
		if ( ! function_exists( 'as_next_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}
		$checked = (int) get_option( 'renewal_ready_scheduled', 0 );
		if ( $checked > time() - HOUR_IN_SECONDS ) {
			return;
		}
		if ( false === as_next_scheduled_action( self::DAILY, array(), self::GROUP ) ) {
			as_schedule_recurring_action( self::next_eight(), DAY_IN_SECONDS, self::DAILY, array(), self::GROUP );
		}
		update_option( 'renewal_ready_scheduled', time(), false );
	}

	/**
	 * The daily job: queue the first batch of each list.
	 */
	public static function daily() {
		Renewal_Ready_Plugin::set_state( 'last_start', time() );
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::BATCH, array( 'active', 0 ), self::GROUP );
			as_enqueue_async_action( self::BATCH, array( 'ended', 0 ), self::GROUP );
			return;
		}
		self::run_now();
	}

	/**
	 * Run every batch at once (tests, the Run now button, no Action Scheduler).
	 *
	 * @return int Notices logged.
	 */
	public static function run_now() {
		Renewal_Ready_Plugin::set_state( 'last_start', time() );
		$count = 0;
		foreach ( array( 'active', 'ended' ) as $state ) {
			$offset = 0;
			do {
				$res     = self::work( $state, $offset );
				$count  += $res['logged'];
				$offset += self::SIZE;
			} while ( $res['more'] && $offset < 100000 );
		}
		return $count;
	}

	/**
	 * One queued batch; queues the next one if the list goes on.
	 *
	 * @param string $state  active or ended.
	 * @param int    $offset Offset.
	 */
	public static function batch( $state = 'active', $offset = 0 ) {
		$res = self::work( (string) $state, (int) $offset );
		if ( $res['more'] && function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::BATCH, array( (string) $state, (int) $offset + self::SIZE ), self::GROUP );
		}
	}

	/**
	 * Process one batch.
	 *
	 * @param string $state  active or ended.
	 * @param int    $offset Offset.
	 * @return array logged, more.
	 */
	private static function work( $state, $offset ) {
		$src = Renewal_Ready_Sources::active();
		if ( ! $src ) {
			Renewal_Ready_Plugin::set_state( 'last_run', time() );
			return array(
				'logged' => 0,
				'more'   => false,
			);
		}
		$ids    = $src->ids( 'ended' === $state ? 'ended' : 'active', $offset, self::SIZE );
		$logged = 0;
		foreach ( $ids as $id ) {
			$logged += 'ended' === $state ? self::catch_up( $src, $id ) : self::process( $src, $id );
		}
		$more = count( $ids ) >= self::SIZE;
		if ( ! $more && 'active' === $state ) {
			Renewal_Ready_Plugin::set_state( 'last_run', time() );
		}
		return array(
			'logged' => $logged,
			'more'   => $more,
		);
	}

	/**
	 * Whether the shop's settings leave this subscription out.
	 *
	 * @param array $sub Record.
	 * @return bool
	 */
	public static function skipped( array $sub ) {
		$s = Renewal_Ready_Plugin::settings();
		if ( 'uk' === $s['customers'] && ! in_array( strtoupper( (string) $sub['country'] ), array( 'GB', 'IM', 'JE', 'GG' ), true ) ) {
			return true;
		}
		if ( 'yes' === $s['skip_company'] && '' !== trim( (string) $sub['company'] ) ) {
			return true;
		}
		/**
		 * Leave a subscription out of reminders.
		 *
		 * @param bool  $skip Skip it.
		 * @param array $sub  Record.
		 */
		return (bool) apply_filters( 'renewal_ready_skip_subscription', false, $sub );
	}

	/**
	 * Work out and log what is due now for one subscription.
	 *
	 * @param Renewal_Ready_Source $src Source.
	 * @param int       $id  Subscription id.
	 * @return int Notices logged.
	 */
	public static function process( Renewal_Ready_Source $src, $id ) {
		$sub = $src->get( $id );
		if ( ! $sub || 'active' !== $sub['status'] || '' !== (string) $src->meta_get( $id, '_rr_end_at' ) ) {
			return 0;
		}
		if ( self::skipped( $sub ) ) {
			return 0;
		}
		$settings = Renewal_Ready_Plugin::timing();
		$today    = Renewal_Ready_Plugin::today();
		$sent     = Renewal_Ready_Notice_Log::seen( $src, $id );
		$due      = Renewal_Ready_Schedule::due_notices( $sub, $settings, $today, $sent );
		/**
		 * Change the notices due now for one subscription.
		 *
		 * @param array  $due      Due notices.
		 * @param array  $sub      Record.
		 * @param array  $settings Timing settings.
		 * @param string $today    Y-m-d.
		 * @param array  $sent     Keys already handled.
		 */
		$due    = (array) apply_filters( 'renewal_ready_due_notices', $due, $sub, $settings, $today, $sent );
		$logged = 0;
		foreach ( $due as $notice ) {
			if ( Renewal_Ready_Notice_Log::notice( $src, $sub, $notice ) ) {
				++$logged;
			}
		}
		return $logged;
	}

	/**
	 * Follow up a subscription whose end was asked for: confirm it if that was
	 * missed, end it once its paid time is over, then stop watching it.
	 *
	 * @param Renewal_Ready_Source $src Source.
	 * @param int       $id  Subscription id.
	 * @return int Notices logged.
	 */
	public static function catch_up( Renewal_Ready_Source $src, $id ) {
		$sub = $src->get( $id );
		if ( ! $sub ) {
			return 0;
		}
		$logged = Renewal_Ready_Notice_Log::ended( $src, $id ) ? 1 : 0;
		$end_at = (string) $src->meta_get( $id, '_rr_end_at' );
		if ( 'active' === $sub['status'] && '' !== $end_at && $end_at <= Renewal_Ready_Plugin::today() ) {
			$src->end( $id, 'system' );
			$sub = $src->get( $id );
		}
		if ( $sub && 'ended' === $sub['status'] ) {
			$src->meta_set( $id, '_rr_watch', '' );
		}
		return $logged;
	}
}

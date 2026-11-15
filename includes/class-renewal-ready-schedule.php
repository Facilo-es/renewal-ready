<?php
/**
 * Pure date logic for reminders and cooling-off periods.
 *
 * Dates in, due notices out. No WordPress calls, so it can be unit-tested
 * against the worked examples in the spec (section 6).
 *
 * @package RenewalReady
 */

defined( 'ABSPATH' ) || exit;

/**
 * Works out which payments need a reminder and when it is due.
 */
final class Renewal_Ready_Schedule {

	/**
	 * Payments before this date are never marked late (first month of the rules).
	 */
	const LATE_FROM = '2027-02-01';

	/**
	 * How far ahead payments are generated, in days.
	 */
	const HORIZON_DAYS = 800;

	/**
	 * Default timing settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'window_max'     => 14,
			'window_min'     => 7,
			'additional_max' => 45,
			'additional_min' => 30,
			'notice_days'    => 0,
		);
	}

	/**
	 * Checks timing settings. Returns an error message, or an empty string when fine.
	 *
	 * @param array $settings Timing settings.
	 * @return string
	 */
	public static function validate( array $settings ) {
		$s = array_merge( self::defaults(), $settings );
		foreach ( array( 'window_max', 'window_min', 'additional_max', 'additional_min', 'notice_days' ) as $key ) {
			if ( ! is_numeric( $s[ $key ] ) || (int) $s[ $key ] < 0 || (int) $s[ $key ] > 365 ) {
				return 'invalid_' . $key;
			}
		}
		if ( (int) $s['window_min'] > (int) $s['window_max'] ) {
			return 'window_order';
		}
		if ( (int) $s['additional_min'] > (int) $s['additional_max'] ) {
			return 'additional_order';
		}
		return '';
	}

	/**
	 * Adds whole months to a Y-m-d date, keeping the day and falling back to the
	 * month's last day (31 January + 1 month = 28 or 29 February).
	 *
	 * @param string $date   Y-m-d.
	 * @param int    $months Months to add.
	 * @return string
	 */
	public static function add_months( $date, $months ) {
		list( $y, $m, $d ) = array_map( 'intval', explode( '-', $date ) );
		$total             = $y * 12 + ( $m - 1 ) + (int) $months;
		$ny                = intdiv( $total, 12 );
		$nm                = $total % 12 + 1;
		$last              = (int) gmdate( 't', gmmktime( 0, 0, 0, $nm, 1, $ny ) );
		return sprintf( '%04d-%02d-%02d', $ny, $nm, min( $d, $last ) );
	}

	/**
	 * Adds days to a Y-m-d date.
	 *
	 * @param string $date Y-m-d.
	 * @param int    $days Days, may be negative.
	 * @return string
	 */
	public static function add_days( $date, $days ) {
		$t = gmmktime( 0, 0, 0, (int) substr( $date, 5, 2 ), (int) substr( $date, 8, 2 ) + (int) $days, (int) substr( $date, 0, 4 ) );
		return gmdate( 'Y-m-d', $t );
	}

	/**
	 * Whole days from $a to $b.
	 *
	 * @param string $a Y-m-d.
	 * @param string $b Y-m-d.
	 * @return int
	 */
	public static function days_between( $a, $b ) {
		$ta = gmmktime( 0, 0, 0, (int) substr( $a, 5, 2 ), (int) substr( $a, 8, 2 ), (int) substr( $a, 0, 4 ) );
		$tb = gmmktime( 0, 0, 0, (int) substr( $b, 5, 2 ), (int) substr( $b, 8, 2 ), (int) substr( $b, 0, 4 ) );
		return (int) round( ( $tb - $ta ) / DAY_IN_SECONDS );
	}

	/**
	 * Adds one billing period.
	 *
	 * @param string $date     Y-m-d.
	 * @param string $period   day, week, month or year.
	 * @param int    $interval Number of periods.
	 * @param int    $times    How many times to add it.
	 * @return string
	 */
	public static function add_period( $date, $period, $interval, $times = 1 ) {
		$n = max( 1, (int) $interval ) * (int) $times;
		switch ( $period ) {
			case 'day':
				return self::add_days( $date, $n );
			case 'week':
				return self::add_days( $date, 7 * $n );
			case 'year':
				return self::add_months( $date, 12 * $n );
			default:
				return self::add_months( $date, $n );
		}
	}

	/**
	 * Length of one billing period in months (0 for day and week plans shorter than a month).
	 *
	 * @param array $sub Normalised subscription.
	 * @return int
	 */
	public static function period_months( array $sub ) {
		$interval = max( 1, (int) $sub['interval'] );
		if ( 'year' === $sub['period'] ) {
			return 12 * $interval;
		}
		if ( 'month' === $sub['period'] ) {
			return $interval;
		}
		$days = 'week' === $sub['period'] ? 7 * $interval : $interval;
		return $days >= 365 ? 12 : 0;
	}

	/**
	 * Every payment from the start up to a date: index 0 is the first (sign-up) payment.
	 *
	 * Each payment is computed from its anchor rather than the previous date, so a
	 * plan started on 31 January pays on 28 or 29 February, then 31 March. When the
	 * source has moved the next payment, later payments follow the new date.
	 *
	 * @param array  $sub   Normalised subscription.
	 * @param string $until Last date to include, Y-m-d.
	 * @return array List of array( 'date', 'amount', 'index' ).
	 */
	public static function payments( array $sub, $until ) {
		$out      = array();
		$amount   = (float) $sub['recurring_amount'];
		$intro    = ! empty( $sub['intro'] ) ? $sub['intro'] : null;
		$trial    = ! empty( $sub['trial_end'] ) ? $sub['trial_end'] : '';
		$end      = ! empty( $sub['end_date'] ) ? $sub['end_date'] : '';
		$next     = ! empty( $sub['next_payment_date'] ) ? $sub['next_payment_date'] : '';
		$period   = $sub['period'];
		$interval = (int) $sub['interval'];

		$index = 0;
		if ( $trial ) {
			$out[]  = array(
				'date'   => $sub['start_date'],
				'amount' => 0.0,
				'index'  => 0,
			);
			$anchor = $trial;
			$index  = 1;
		} else {
			$anchor = $sub['start_date'];
		}
		$paid  = 0;
		$k     = 0;
		$moved = false;
		while ( true ) {
			$date = self::add_period( $anchor, $period, $interval, $k );
			if ( ! $moved && $next && $index > 0 ) {
				$following = self::add_period( $anchor, $period, $interval, $k + 1 );
				$replace   = $date > $next;
				if ( $date < $next && $following > $next ) {
					// The payment nearest to the source's next date is the one it moved.
					$replace = self::days_between( $date, $next ) <= self::days_between( $next, $following );
				}
				if ( $date === $next ) {
					$moved = true;
				} elseif ( $replace ) {
					$anchor = $next;
					$k      = 0;
					$moved  = true;
					$date   = $next;
				}
			}
			if ( $date > $until || ( $end && $date >= $end ) ) {
				break;
			}
			$price = $amount;
			if ( $intro && $paid < (int) $intro['count'] ) {
				$price = (float) $intro['amount'];
			}
			$out[] = array(
				'date'   => $date,
				'amount' => $price,
				'index'  => $index,
			);
			++$paid;
			++$index;
			++$k;
			if ( $index > 5000 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * The renewal payment that ends the concession (free trial or lower intro price), or null.
	 *
	 * @param array $sub      Normalised subscription.
	 * @param array $payments From payments().
	 * @return array|null
	 */
	public static function concession_trigger( array $sub, array $payments ) {
		$has_trial = ! empty( $sub['trial_end'] );
		$has_intro = ! empty( $sub['intro'] ) && (float) $sub['intro']['amount'] < (float) $sub['recurring_amount'];
		if ( ! $has_trial && ! $has_intro ) {
			return null;
		}
		foreach ( $payments as $p ) {
			if ( $p['index'] >= 1 && (float) $p['amount'] >= (float) $sub['recurring_amount'] ) {
				return $p;
			}
		}
		return null;
	}

	/**
	 * Payments that need a reminder, in date order (s.258 relevant 6-month periods).
	 *
	 * @param array  $sub   Normalised subscription.
	 * @param string $until Generate up to this date.
	 * @return array List of payments, each with 'previous_amount' added.
	 */
	public static function reminder_targets( array $sub, $until ) {
		$payments = self::payments( $sub, self::add_months( $until, self::period_months( $sub ) + 7 ) );
		$renewals = array();
		foreach ( $payments as $p ) {
			if ( $p['index'] >= 1 ) {
				$renewals[] = $p;
			}
		}
		if ( ! $renewals ) {
			return array();
		}

		$targets = array();
		$trigger = self::concession_trigger( $sub, $payments );
		if ( $trigger ) {
			$targets[ $trigger['date'] ] = $trigger;
			$start                       = self::add_days( $trigger['date'], 1 );
		} else {
			$start = $sub['start_date'];
		}

		$guard = 0;
		while ( $start <= $until && $guard++ < 200 ) {
			$end   = self::six_month_end( $start );
			$found = null;
			foreach ( $renewals as $p ) {
				if ( $p['date'] >= $start && $p['date'] <= $end ) {
					$found = $p;
				} elseif ( $p['date'] > $end ) {
					if ( ! $found ) {
						$found = $p;
					}
					break;
				}
			}
			if ( ! $found ) {
				break;
			}
			$targets[ $found['date'] ] = $found;
			$start                     = self::add_days( $end, 1 );
		}

		ksort( $targets );
		$out = array();
		foreach ( $targets as $t ) {
			$t['previous_amount'] = self::previous_amount( $payments, $t['date'] );
			$out[]                = $t;
		}
		return $out;
	}

	/**
	 * Last day of the 6-month period that starts on $start. If the matching day does
	 * not exist (31 August + 6 months), the period ends on the last day of that month.
	 *
	 * @param string $start Y-m-d.
	 * @return string
	 */
	public static function six_month_end( $start ) {
		$day    = (int) substr( $start, 8, 2 );
		$target = self::add_months( $start, 6 );
		if ( (int) substr( $target, 8, 2 ) < $day ) {
			return $target;
		}
		return self::add_days( $target, -1 );
	}

	/**
	 * The amount of the payment before $date, or null for the first.
	 *
	 * @param array  $payments From payments().
	 * @param string $date     Y-m-d.
	 * @return float|null
	 */
	private static function previous_amount( array $payments, $date ) {
		$prev = null;
		foreach ( $payments as $p ) {
			if ( $p['date'] >= $date ) {
				break;
			}
			$prev = (float) $p['amount'];
		}
		return $prev;
	}

	/**
	 * Last cancellation date and reminder window for a payment.
	 *
	 * @param string $payment_date Y-m-d.
	 * @param array  $settings     Timing settings.
	 * @param string $type         reminder or additional.
	 * @return array array( 'last_cancel', 'window_start', 'window_end' ).
	 */
	public static function window( $payment_date, array $settings, $type = 'reminder' ) {
		$s    = array_merge( self::defaults(), $settings );
		$last = self::add_days( $payment_date, -1 * (int) $s['notice_days'] - 1 );
		$max  = 'additional' === $type ? (int) $s['additional_max'] : (int) $s['window_max'];
		$min  = 'additional' === $type ? (int) $s['additional_min'] : (int) $s['window_min'];
		return array(
			'last_cancel'  => $last,
			'window_start' => self::add_days( $last, -$max ),
			'window_end'   => self::add_days( $last, -$min ),
		);
	}

	/**
	 * Every scheduled notice for a subscription, due or not, up to a date.
	 *
	 * @param array  $sub      Normalised subscription.
	 * @param array  $settings Timing settings.
	 * @param string $until    Y-m-d.
	 * @return array
	 */
	public static function notices( array $sub, array $settings, $until ) {
		$out    = array();
		$annual = self::period_months( $sub ) >= 12;
		foreach ( self::reminder_targets( $sub, $until ) as $t ) {
			$types = $annual ? array( 'additional', 'reminder' ) : array( 'reminder' );
			foreach ( $types as $type ) {
				$w     = self::window( $t['date'], $settings, $type );
				$out[] = array_merge(
					$w,
					array(
						'key'             => $sub['id'] . '|' . $type . '|' . $t['date'],
						'type'            => $type,
						'payment_date'    => $t['date'],
						'amount'          => (float) $t['amount'],
						'previous_amount' => $t['previous_amount'],
						'increase'        => null !== $t['previous_amount'] && (float) $t['amount'] > (float) $t['previous_amount'],
					)
				);
			}
		}
		return $out;
	}

	/**
	 * Notices due today: inside their window, or past it while the payment is still to come
	 * (those are sent at once and marked late).
	 *
	 * @param array  $sub      Normalised subscription.
	 * @param array  $settings Timing settings.
	 * @param string $today    Y-m-d, site time.
	 * @param array  $sent     Keys already sent.
	 * @return array
	 */
	public static function due_notices( array $sub, array $settings, $today, array $sent = array() ) {
		if ( '' !== self::validate( $settings ) ) {
			return array();
		}
		$due = array();
		foreach ( self::notices( $sub, $settings, self::add_days( $today, 120 ) ) as $n ) {
			if ( isset( $sent[ $n['key'] ] ) || in_array( $n['key'], $sent, true ) ) {
				continue;
			}
			if ( $n['payment_date'] <= $today || $n['window_start'] > $today ) {
				continue;
			}
			$n['late'] = $today > $n['window_end'] && $n['payment_date'] >= self::LATE_FROM;
			$due[]     = $n;
		}
		return $due;
	}

	/**
	 * Cooling-off periods (s.265): the initial one and each relevant renewal.
	 *
	 * @param array  $sub   Normalised subscription.
	 * @param string $until Y-m-d.
	 * @return array List of array( 'kind', 'starts', 'ends', 'payment_date' ).
	 */
	public static function cooling_off_periods( array $sub, $until ) {
		$out      = array(
			array(
				'kind'         => 'initial',
				'starts'       => $sub['start_date'],
				'ends'         => self::add_days( $sub['start_date'], 14 ),
				'payment_date' => $sub['start_date'],
			),
		);
		$payments = self::payments( $sub, self::add_months( $until, self::period_months( $sub ) + 13 ) );
		$trigger  = self::concession_trigger( $sub, $payments );
		$count    = count( $payments );
		for ( $i = 0; $i < $count; $i++ ) {
			$p = $payments[ $i ];
			if ( $p['index'] < 1 || $p['date'] > $until ) {
				continue;
			}
			$relevant = $trigger && $trigger['date'] === $p['date'];
			if ( ! $relevant ) {
				$following = self::add_period( $p['date'], $sub['period'], (int) $sub['interval'] );
				$relevant  = self::add_months( $p['date'], 12 ) <= $following;
			}
			if ( $relevant ) {
				$out[] = array(
					'kind'         => 'renewal',
					'starts'       => $p['date'],
					'ends'         => self::add_days( $p['date'], 14 ),
					'payment_date' => $p['date'],
				);
			}
		}
		return $out;
	}
}

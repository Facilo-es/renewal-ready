<?php
/**
 * Records of every notice: a note on the subscription and keys in its meta.
 *
 * @package RenewalReady
 */

defined( 'ABSPATH' ) || exit;

/**
 * Notice log (Free keeps it in subscription notes).
 */
class Renewal_Ready_Notice_Log {

	const SENT    = '_rr_sent';
	const PREVIEW = '_rr_previewed';

	/**
	 * Keys handled in this request, so two hooks firing for one change send one email.
	 *
	 * @var array
	 */
	private static $done = array();

	/**
	 * Keys stored on a subscription.
	 *
	 * @param Renewal_Ready_Source $src  Source.
	 * @param int       $id   Subscription id.
	 * @param string    $meta Meta key.
	 * @return array Key => time.
	 */
	public static function keys( Renewal_Ready_Source $src, $id, $meta = self::SENT ) {
		$v = $src->meta_get( $id, $meta );
		return is_array( $v ) ? $v : array();
	}

	/**
	 * Keys that count as done in the current mode.
	 *
	 * @param Renewal_Ready_Source $src Source.
	 * @param int       $id  Subscription id.
	 * @return array
	 */
	public static function seen( Renewal_Ready_Source $src, $id ) {
		$sent = self::keys( $src, $id, self::SENT );
		return Renewal_Ready_Plugin::is_live() ? $sent : $sent + self::keys( $src, $id, self::PREVIEW );
	}

	/**
	 * In-request guard key. It includes the mode, so a switch to Live in the
	 * same request still sends what Preview only recorded.
	 *
	 * @param Renewal_Ready_Source $src Source.
	 * @param string    $key Notice key.
	 * @return string
	 */
	private static function guard( Renewal_Ready_Source $src, $key ) {
		return $src->id() . $key . ( Renewal_Ready_Plugin::is_live() ? '|live' : '|preview' );
	}

	/**
	 * Remember a key.
	 *
	 * @param Renewal_Ready_Source $src  Source.
	 * @param int       $id   Subscription id.
	 * @param string    $meta Meta key.
	 * @param string    $key  Notice key.
	 */
	private static function mark( Renewal_Ready_Source $src, $id, $meta, $key ) {
		$v         = self::keys( $src, $id, $meta );
		$v[ $key ] = time();
		$src->meta_set( $id, $meta, $v );
	}

	/**
	 * Forget a key, so it can be sent again (after a reactivation).
	 *
	 * @param Renewal_Ready_Source $src Source.
	 * @param int       $id  Subscription id.
	 * @param string    $key Notice key.
	 */
	public static function forget( Renewal_Ready_Source $src, $id, $key ) {
		foreach ( array( self::SENT, self::PREVIEW ) as $meta ) {
			$v = self::keys( $src, $id, $meta );
			if ( isset( $v[ $key ] ) ) {
				unset( $v[ $key ] );
				$src->meta_set( $id, $meta, $v );
			}
		}
		unset( self::$done[ $src->id() . $key . '|live' ], self::$done[ $src->id() . $key . '|preview' ] );
	}

	/**
	 * Send (or, in preview, only record) a renewal reminder.
	 *
	 * @param Renewal_Ready_Source $src    Source.
	 * @param array     $sub    Subscription record.
	 * @param array     $notice Due notice from Renewal_Ready_Schedule.
	 * @return array|null Log entry.
	 */
	public static function notice( Renewal_Ready_Source $src, array $sub, array $notice ) {
		$key = (string) $notice['key'];
		if ( isset( self::seen( $src, $sub['id'] )[ $key ] ) || isset( self::$done[ self::guard( $src, $key ) ] ) ) {
			return null;
		}
		self::$done[ self::guard( $src, $key ) ] = 1;
		$type                                    = 'additional' === $notice['type'] ? 'additional' : 'reminder';
		$entry                                   = self::deliver( $src, $sub, $type, $notice );
		self::mark( $src, $sub['id'], 'preview' === $entry['mode'] ? self::PREVIEW : self::SENT, $key );
		return $entry;
	}

	/**
	 * Confirm an end, once.
	 *
	 * @param Renewal_Ready_Source $src Source.
	 * @param int       $id  Subscription id.
	 * @param array     $ctx ended_now (the source ended it at once), requested (Y-m-d).
	 * @return array|null Log entry.
	 */
	public static function ended( Renewal_Ready_Source $src, $id, array $ctx = array() ) {
		$key = $id . '|end';
		if ( isset( self::seen( $src, $id )[ $key ] ) || isset( self::$done[ self::guard( $src, $key ) ] ) ) {
			return null;
		}
		$sub = $src->get( $id );
		if ( ! $sub ) {
			return null;
		}
		self::$done[ self::guard( $src, $key ) ] = 1;
		$today                                   = Renewal_Ready_Plugin::today();
		$end_at                                  = (string) $src->meta_get( $id, '_rr_end_at' );
		if ( '' !== $end_at ) {
			$end = $end_at;
		} elseif ( ! empty( $ctx['ended_now'] ) ) {
			$end = $today;
		} else {
			$end = $sub['end_date'] ? $sub['end_date'] : $today;
		}
		$paid_until = '';
		if ( $end <= $today && ! empty( $sub['next_payment_date'] ) && $sub['next_payment_date'] > $today ) {
			$paid_until = $sub['next_payment_date'];
		}
		$data  = array(
			'key'          => $key,
			'type'         => 'end',
			'payment_date' => '',
			'request_date' => ! empty( $ctx['requested'] ) ? (string) $ctx['requested'] : $today,
			'end_date'     => $end,
			'continues'    => $end > $today,
			'paid_until'   => $paid_until,
			'late'         => false,
		);
		$entry = self::deliver( $src, $sub, 'end', $data );
		self::mark( $src, $id, 'preview' === $entry['mode'] ? self::PREVIEW : self::SENT, $key );
		$src->meta_set( $id, '_rr_watch', '1' );
		return $entry;
	}

	/**
	 * Email the notice if live, then write the note and the log entry.
	 *
	 * @param Renewal_Ready_Source $src  Source.
	 * @param array     $sub  Subscription record.
	 * @param string    $type reminder, additional or end.
	 * @param array     $data Notice data.
	 * @return array Log entry.
	 */
	private static function deliver( Renewal_Ready_Source $src, array $sub, $type, array $data ) {
		$email   = Renewal_Ready_Emails::get( $type );
		$live    = Renewal_Ready_Plugin::is_live();
		$subject = '';
		$body    = '';
		$ok      = false;
		if ( $email ) {
			$email->prepare( $sub, $data );
			$subject = $email->get_subject();
			$body    = $email->get_content_plain();
		}
		if ( ! $live ) {
			$mode = 'preview';
		} elseif ( ! $email || ! $email->is_enabled() || ! is_email( $sub['email'] ) ) {
			$mode = 'off';
		} else {
			$mode = 'live';
			$ok   = (bool) $email->trigger( $sub, $data );
		}
		$src->note( $sub['id'], self::note_text( $type, $sub, $data, $mode, $ok ) );
		$entry = array(
			'sub_id'       => (int) $sub['id'],
			'source'       => $src->id(),
			'type'         => $type,
			'payment_date' => isset( $data['payment_date'] ) ? (string) $data['payment_date'] : '',
			'window_start' => isset( $data['window_start'] ) ? (string) $data['window_start'] : '',
			'window_end'   => isset( $data['window_end'] ) ? (string) $data['window_end'] : '',
			'mode'         => $mode,
			'recipient'    => (string) $sub['email'],
			'subject'      => $subject,
			'body'         => $body,
			'mail_ok'      => $ok,
			'late'         => ! empty( $data['late'] ),
			'sent_at'      => gmdate( 'Y-m-d H:i:s' ),
		);
		/**
		 * A notice was sent or recorded.
		 *
		 * @param array $entry Log entry.
		 */
		do_action( 'renewal_ready_notice_logged', $entry );
		if ( ! empty( $data['late'] ) ) {
			Renewal_Ready_Plugin::set_state( 'last_late', time() );
		}
		return $entry;
	}

	/**
	 * The note written on the subscription.
	 *
	 * @param string $type Type.
	 * @param array  $sub  Record.
	 * @param array  $data Notice data.
	 * @param string $mode preview, off or live.
	 * @param bool   $ok   Mail accepted.
	 * @return string
	 */
	private static function note_text( $type, array $sub, array $data, $mode, $ok ) {
		if ( 'end' === $type ) {
			/* translators: %s: end date. */
			$what = sprintf( __( 'Renewal Ready: end confirmation (ends on %s)', 'renewal-ready' ), Renewal_Ready_Plugin::date_label( $data['end_date'] ) );
		} else {
			$what = sprintf(
				/* translators: 1: reminder kind, 2: payment date, 3: amount. */
				__( 'Renewal Ready: %1$s for the payment on %2$s (%3$s)', 'renewal-ready' ),
				'additional' === $type ? __( 'early yearly reminder', 'renewal-ready' ) : __( 'renewal reminder', 'renewal-ready' ),
				Renewal_Ready_Plugin::date_label( $data['payment_date'] ),
				Renewal_Ready_Plugin::money_text( $data['amount'], $sub['currency'] )
			);
		}
		if ( 'preview' === $mode ) {
			$how = __( 'Preview mode: written here only, not emailed.', 'renewal-ready' );
		} elseif ( 'off' === $mode ) {
			$how = __( 'Not emailed: this email is turned off or the customer has no email address.', 'renewal-ready' );
		} elseif ( $ok ) {
			/* translators: %s: email address. */
			$how = sprintf( __( 'Emailed to %s.', 'renewal-ready' ), $sub['email'] );
		} else {
			/* translators: %s: email address. */
			$how = sprintf( __( 'Sending to %s failed.', 'renewal-ready' ), $sub['email'] );
		}
		$late = ! empty( $data['late'] ) ? ' ' . __( 'Sent late: the reminder window had passed.', 'renewal-ready' ) : '';
		return $what . '. ' . $how . $late;
	}
}

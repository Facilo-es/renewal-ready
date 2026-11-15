<?php
/**
 * Key information and tick text for a subscription product.
 *
 * @package RenewalReady
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds the checkout copy from product terms.
 */
class Renewal_Ready_Terms {

	/**
	 * Terms for a product, or null if it isn't a subscription.
	 *
	 * @param WC_Product $product Product.
	 * @return array|null
	 */
	public static function for_product( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return null;
		}
		$t = Renewal_Ready_Sources::terms_for( $product );
		if ( ! $t ) {
			return null;
		}
		$t = array_merge(
			array(
				'period'     => 'month',
				'interval'   => 1,
				'price'      => 0.0,
				'trial_days' => 0,
				'signup_fee' => 0.0,
				'intro'      => null,
				'min_term'   => 0,
			),
			$t
		);
		if ( ! in_array( $t['period'], array( 'day', 'week', 'month', 'year' ), true ) ) {
			$t['period'] = 'month';
		}
		$t['interval'] = max( 1, (int) $t['interval'] );
		$t['name']     = $product->get_name();
		$t['goods']    = $product->needs_shipping();
		/**
		 * Adjust the terms used for the checkout box and tick.
		 *
		 * @param array      $t       Terms.
		 * @param WC_Product $product Product.
		 */
		return apply_filters( 'renewal_ready_product_terms', $t, $product );
	}

	/**
	 * Money as HTML.
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	private static function m( $amount ) {
		return Renewal_Ready_Plugin::money( $amount );
	}

	/**
	 * Amount due today for one unit.
	 *
	 * @param array $t Terms.
	 * @return float
	 */
	public static function today_amount( array $t ) {
		if ( $t['trial_days'] > 0 ) {
			$first = 0.0;
		} elseif ( is_array( $t['intro'] ) && isset( $t['intro']['amount'] ) ) {
			$first = (float) $t['intro']['amount'];
		} else {
			$first = (float) $t['price'];
		}
		return $first + (float) $t['signup_fee'];
	}

	/**
	 * Key-information lines (HTML with strong and links only).
	 *
	 * @param array $t Terms.
	 * @return array
	 */
	public static function lines( array $t ) {
		$s     = Renewal_Ready_Plugin::timing();
		$freq  = esc_html( Renewal_Ready_Plugin::frequency( $t['period'], $t['interval'] ) );
		$price = self::m( $t['price'] );
		$today = Renewal_Ready_Plugin::today();
		$out   = array();

		/* translators: %s: product name. */
		$out[] = sprintf( esc_html__( 'This is a subscription for %s. It renews automatically until you end it.', 'renewal-ready' ), '<strong>' . esc_html( $t['name'] ) . '</strong>' );

		if ( $t['trial_days'] > 0 ) {
			$first = Renewal_Ready_Schedule::add_days( $today, (int) $t['trial_days'] );
			$out[] = sprintf(
				/* translators: 1: trial length, 2: amount, 3: frequency, 4: date. */
				esc_html__( 'It\'s free for %1$s. Then you\'ll pay %2$s every %3$s, starting on %4$s.', 'renewal-ready' ),
				esc_html( Renewal_Ready_Plugin::length( (int) $t['trial_days'], 'day' ) ),
				$price,
				$freq,
				esc_html( Renewal_Ready_Plugin::date_label( $first ) )
			);
		} elseif ( is_array( $t['intro'] ) && ! empty( $t['intro']['count'] ) ) {
			$count = (int) $t['intro']['count'];
			$from  = Renewal_Ready_Schedule::add_period( $today, $t['period'], $t['interval'], $count );
			$out[] = sprintf(
				/* translators: 1: intro amount, 2: frequency, 3: intro length, 4: amount, 5: frequency, 6: date. */
				esc_html__( 'You\'ll pay %1$s every %2$s for %3$s, then %4$s every %5$s from %6$s.', 'renewal-ready' ),
				self::m( (float) $t['intro']['amount'] ),
				$freq,
				esc_html( Renewal_Ready_Plugin::length( $count * $t['interval'], $t['period'] ) ),
				$price,
				$freq,
				esc_html( Renewal_Ready_Plugin::date_label( $from ) )
			);
		} else {
			$out[] = sprintf(
				/* translators: 1: amount today, 2: amount, 3: frequency. */
				esc_html__( 'You\'ll pay %1$s today, then %2$s every %3$s.', 'renewal-ready' ),
				self::m( self::today_amount( $t ) ),
				$price,
				$freq
			);
		}

		if ( (int) $t['min_term'] > 1 ) {
			$out[] = sprintf(
				/* translators: 1: minimum term, 2: total. */
				esc_html__( 'There\'s a minimum term of %1$s. The least you\'ll pay in total is %2$s.', 'renewal-ready' ),
				esc_html( Renewal_Ready_Plugin::length( (int) $t['min_term'] * $t['interval'], $t['period'] ) ),
				self::m( (float) $t['price'] * (int) $t['min_term'] + (float) $t['signup_fee'] )
			);
		} else {
			$out[] = esc_html__( 'There\'s no minimum term. You can end it before any renewal.', 'renewal-ready' );
		}

		$remind = sprintf(
			/* translators: 1: most days, 2: fewest days. */
			esc_html__( 'We\'ll email you a reminder %1$d to %2$d days before the last day you can end it to avoid the next payment.', 'renewal-ready' ),
			(int) $s['window_max'],
			(int) $s['window_min']
		);
		if ( 'year' === $t['period'] || ( 'month' === $t['period'] && $t['interval'] >= 12 ) ) {
			$remind .= ' ' . sprintf(
				/* translators: 1: most days, 2: fewest days. */
				esc_html__( 'For yearly renewals, we\'ll also send an earlier reminder %1$d to %2$d days before.', 'renewal-ready' ),
				(int) $s['additional_max'],
				(int) $s['additional_min']
			);
		}
		$out[] = $remind;

		$end  = esc_html__( 'You can end it at any time online in My Account › Subscriptions. It stops at the end of the period you\'ve paid for.', 'renewal-ready' );
		$exit = Renewal_Ready_Plugin::exit_url();
		if ( $exit ) {
			$end .= ' <a href="' . esc_url( $exit ) . '" target="_blank" rel="noopener">' . esc_html__( 'How to end your subscription', 'renewal-ready' ) . '</a>';
		}
		$out[] = $end;

		$cancel = $t['goods']
			? esc_html__( 'You can cancel within 14 days of receiving your first delivery and get a refund.', 'renewal-ready' )
			: esc_html__( 'You can cancel within 14 days of today and get a refund.', 'renewal-ready' );
		$terms  = function_exists( 'wc_terms_and_conditions_page_id' ) ? (int) wc_terms_and_conditions_page_id() : 0;
		if ( $terms ) {
			$cancel .= ' <a href="' . esc_url( get_permalink( $terms ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Full terms', 'renewal-ready' ) . '</a>';
		}
		$out[] = $cancel;

		/**
		 * Change the key-information lines.
		 *
		 * @param array $out Lines (HTML).
		 * @param array $t   Terms.
		 */
		return apply_filters( 'renewal_ready_key_info_lines', $out, $t );
	}

	/**
	 * Tick text (plain).
	 *
	 * @param array $t Terms.
	 * @return string
	 */
	public static function tick_text( array $t ) {
		$amount = Renewal_Ready_Plugin::money_text( $t['price'] );
		$freq   = Renewal_Ready_Plugin::frequency( $t['period'], $t['interval'] );
		if ( $t['trial_days'] > 0 ) {
			/* translators: 1: trial length, 2: amount, 3: frequency. */
			return sprintf( __( 'I understand this is a subscription. After the free %1$s, I agree to pay %2$s every %3$s until I end it.', 'renewal-ready' ), Renewal_Ready_Plugin::length( (int) $t['trial_days'], 'day' ), $amount, $freq );
		}
		/* translators: 1: amount, 2: frequency. */
		return sprintf( __( 'I understand this is a subscription and I agree to pay %1$s every %2$s until I end it.', 'renewal-ready' ), $amount, $freq );
	}

	/**
	 * Allowed HTML in lines.
	 *
	 * @return array
	 */
	public static function allowed() {
		return array(
			'strong' => array(),
			'span'   => array( 'class' => true ),
			'bdi'    => array(),
			'a'      => array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
			),
		);
	}
}

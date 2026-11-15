<?php
/**
 * Ending a subscription online: My Account screens, exit page, shortcode and block.
 *
 * @package RenewalReady
 */

defined( 'ABSPATH' ) || exit;

/**
 * My Account "End subscription", confirm screen with two equal buttons, done screen.
 */
class Renewal_Ready_Exit {

	const ENDPOINT = 'end-subscription';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'endpoint' ) );
		add_filter( 'woocommerce_get_query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'menu' ), 40 );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'render' ) );
		add_filter( 'woocommerce_endpoint_' . self::ENDPOINT . '_title', array( __CLASS__, 'title' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_shortcode( 'renewal_ready_exit', array( __CLASS__, 'shortcode' ) );
		add_action( 'init', array( __CLASS__, 'register_block' ) );
	}

	/**
	 * Register the endpoint, flushing rules once after activation.
	 */
	public static function endpoint() {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
		if ( Renewal_Ready_Plugin::state( 'flush' ) ) {
			Renewal_Ready_Plugin::set_state( 'flush', null );
			flush_rewrite_rules( false );
		}
	}

	/**
	 * WooCommerce query vars.
	 *
	 * @param array $vars Vars.
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[ self::ENDPOINT ] = self::ENDPOINT;
		return $vars;
	}

	/**
	 * Endpoint title.
	 *
	 * @return string
	 */
	public static function title() {
		return __( 'End subscription', 'renewal-ready' );
	}

	/**
	 * Menu item, just before Log out, for customers with a subscription.
	 *
	 * @param array $items Items.
	 * @return array
	 */
	public static function menu( $items ) {
		if ( ! is_user_logged_in() || ! self::customer_subs( get_current_user_id() ) ) {
			return $items;
		}
		$out = array();
		foreach ( $items as $key => $label ) {
			if ( 'customer-logout' === $key ) {
				$out[ self::ENDPOINT ] = __( 'End subscription', 'renewal-ready' );
			}
			$out[ $key ] = $label;
		}
		if ( ! isset( $out[ self::ENDPOINT ] ) ) {
			$out[ self::ENDPOINT ] = __( 'End subscription', 'renewal-ready' );
		}
		return $out;
	}

	/**
	 * Styles on account pages and pages with the exit block or shortcode.
	 */
	public static function assets() {
		wp_register_style( 'renewal-ready-account', Renewal_Ready_Plugin::asset( 'css/account.css' ), array(), RENEWAL_READY_VERSION );
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			wp_enqueue_style( 'renewal-ready-account' );
		}
	}

	/**
	 * Register the exit block.
	 */
	public static function register_block() {
		wp_register_script( 'renewal-ready-exit-editor', Renewal_Ready_Plugin::asset( 'js/exit-editor.js' ), array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-i18n' ), RENEWAL_READY_VERSION, true );
		wp_set_script_translations( 'renewal-ready-exit-editor', 'renewal-ready' );
		wp_register_style( 'renewal-ready-account', Renewal_Ready_Plugin::asset( 'css/account.css' ), array(), RENEWAL_READY_VERSION );
		register_block_type(
			RENEWAL_READY_DIR . 'blocks/exit',
			array( 'render_callback' => array( __CLASS__, 'shortcode' ) )
		);
	}

	/**
	 * Active subscriptions of a customer that can still be ended.
	 *
	 * @param int $user_id User.
	 * @return array Records.
	 */
	public static function customer_subs( $user_id ) {
		static $cache = array();
		$src          = Renewal_Ready_Sources::active();
		if ( ! $src || ! $user_id ) {
			return array();
		}
		if ( ! isset( $cache[ $user_id ] ) ) {
			$cache[ $user_id ] = array();
			foreach ( $src->ids_for_customer( $user_id ) as $id ) {
				$sub = $src->get( $id );
				if ( $sub && in_array( $sub['status'], array( 'active', 'ending' ), true ) ) {
					$cache[ $user_id ][ (int) $id ] = $sub;
				}
			}
		}
		return $cache[ $user_id ];
	}

	/**
	 * URL of the end screen.
	 *
	 * @param int $id Subscription id, 0 for the list.
	 * @return string
	 */
	public static function url( $id = 0 ) {
		return wc_get_endpoint_url( self::ENDPOINT, $id ? (string) (int) $id : '', wc_get_page_permalink( 'myaccount' ) );
	}

	/**
	 * Reasons offered on the confirm screen.
	 *
	 * @return array
	 */
	public static function reasons() {
		return (array) apply_filters(
			'renewal_ready_exit_reasons',
			array(
				'price'   => __( 'It costs too much', 'renewal-ready' ),
				'use'     => __( 'I don\'t use it enough', 'renewal-ready' ),
				'switch'  => __( 'I\'m moving to another shop', 'renewal-ready' ),
				'problem' => __( 'Something went wrong', 'renewal-ready' ),
				'other'   => __( 'Something else', 'renewal-ready' ),
			)
		);
	}

	/**
	 * Handle the confirm form.
	 */
	public static function handle() {
		if ( ! isset( $_POST['renewal_ready_end'], $_POST['_rr_nonce'] ) || ! is_user_logged_in() ) {
			return;
		}
		$id = absint( wp_unslash( $_POST['renewal_ready_end'] ) );
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_rr_nonce'] ) ), 'renewal_ready_end_' . $id ) ) {
			wc_add_notice( __( 'That link has expired. Please try again.', 'renewal-ready' ), 'error' );
			return;
		}
		$subs = self::customer_subs( get_current_user_id() );
		if ( ! isset( $subs[ $id ] ) ) {
			wc_add_notice( __( 'We couldn\'t find that subscription in your account.', 'renewal-ready' ), 'error' );
			return;
		}
		$reasons = self::reasons();
		$reason  = isset( $_POST['rr_reason'] ) ? sanitize_key( wp_unslash( $_POST['rr_reason'] ) ) : '';
		$done    = self::end_subscription(
			$id,
			array(
				'via'    => 'account',
				'reason' => isset( $reasons[ $reason ] ) ? $reasons[ $reason ] : '',
			)
		);
		if ( is_wp_error( $done ) ) {
			wc_add_notice( $done->get_error_message(), 'error' );
			return;
		}
		wp_safe_redirect( add_query_arg( 'rr-done', '1', self::url( $id ) ) );
		exit;
	}

	/**
	 * End a subscription at the end of its paid time, note it and confirm it.
	 *
	 * @param int   $id   Subscription id.
	 * @param array $args via (account, admin, pro), reason.
	 * @return string|WP_Error End date.
	 */
	public static function end_subscription( $id, array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'via'    => 'account',
				'reason' => '',
			)
		);
		$src  = Renewal_Ready_Sources::active();
		if ( ! $src ) {
			return new WP_Error( 'rr_no_source', __( 'No subscription plugin is active.', 'renewal-ready' ) );
		}
		$sub = $src->get( $id );
		if ( ! $sub ) {
			return new WP_Error( 'rr_not_found', __( 'We couldn\'t find that subscription.', 'renewal-ready' ) );
		}
		if ( 'ending' === $sub['status'] ) {
			$end = (string) $src->meta_get( $id, '_rr_end_at' );
			return '' !== $end ? $end : (string) $sub['end_date'];
		}
		$today = Renewal_Ready_Plugin::today();
		$end   = $src->end( $id, 'admin' === $args['via'] ? 'admin' : 'customer' );
		if ( is_wp_error( $end ) ) {
			return $end;
		}
		if ( '' !== $args['reason'] ) {
			$src->meta_set( $id, '_rr_reason', $args['reason'] );
		}
		$note = 'account' === $args['via']
			/* translators: %s: end date. */
			? __( 'Renewal Ready: the customer ended this subscription in My Account. It ends on %s.', 'renewal-ready' )
			/* translators: %s: end date. */
			: __( 'Renewal Ready: this subscription was ended. It ends on %s.', 'renewal-ready' );
		$text = sprintf( $note, Renewal_Ready_Plugin::date_label( $end ) );
		if ( '' !== $args['reason'] ) {
			/* translators: %s: reason. */
			$text .= ' ' . sprintf( __( 'Reason given: %s.', 'renewal-ready' ), $args['reason'] );
		}
		$src->note( $id, $text );
		Renewal_Ready_Notice_Log::ended( $src, $id, array( 'requested' => $today ) );
		/**
		 * A subscription was ended through Renewal Ready.
		 *
		 * @param int   $id   Subscription id.
		 * @param array $args via, reason, end (Y-m-d).
		 */
		do_action( 'renewal_ready_ended', $id, array_merge( $args, array( 'end' => $end ) ) );
		return $end;
	}

	/**
	 * Alias of end_subscription(), for add-ons.
	 *
	 * @param int   $id   Subscription id.
	 * @param array $args Arguments.
	 * @return string|WP_Error
	 */
	public static function end( $id, array $args = array() ) {
		return self::end_subscription( $id, $args );
	}

	/**
	 * My Account endpoint.
	 *
	 * @param string $value Subscription id or empty.
	 */
	public static function render( $value = '' ) {
		$subs = self::customer_subs( get_current_user_id() );
		$id   = absint( $value );
		echo '<div class="rr-x">';
		if ( $id && isset( $subs[ $id ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display flag only.
			if ( isset( $_GET['rr-done'] ) ) {
				self::done( $subs[ $id ] );
			} else {
				self::confirm( $subs[ $id ] );
			}
		} else {
			self::listing( $subs );
		}
		echo '</div>';
	}

	/**
	 * The date a subscription ends if ended now, or already ends.
	 *
	 * @param array $sub Record.
	 * @return string Y-m-d or ''.
	 */
	private static function end_date( array $sub ) {
		$src = Renewal_Ready_Sources::active();
		$at  = $src ? (string) $src->meta_get( $sub['id'], '_rr_end_at' ) : '';
		if ( '' !== $at ) {
			return $at;
		}
		if ( 'ending' === $sub['status'] && $sub['end_date'] ) {
			return (string) $sub['end_date'];
		}
		return (string) $sub['next_payment_date'];
	}

	/**
	 * The list of subscriptions.
	 *
	 * @param array $subs Records.
	 */
	private static function listing( array $subs ) {
		if ( ! $subs ) {
			echo '<p>' . esc_html__( 'You have no subscriptions to end.', 'renewal-ready' ) . '</p>';
			return;
		}
		echo '<p class="rr-x-lead">' . esc_html__( 'Choose the subscription you want to end. You can keep using it until the end of the time you\'ve paid for.', 'renewal-ready' ) . '</p><ul class="rr-x-list">';
		foreach ( $subs as $sub ) {
			/* translators: 1: amount, 2: period such as month. */
			$price = sprintf( __( '%1$s every %2$s', 'renewal-ready' ), Renewal_Ready_Plugin::money( $sub['recurring_amount'], $sub['currency'] ), esc_html( Renewal_Ready_Plugin::frequency( $sub['period'], $sub['interval'] ) ) );
			echo '<li><div><strong>' . esc_html( $sub['product_name'] ) . '</strong><span>' . wp_kses_post( $price ) . '</span>';
			if ( 'ending' === $sub['status'] ) {
				/* translators: %s: date. */
				echo '<em>' . esc_html( sprintf( __( 'Ends on %s', 'renewal-ready' ), Renewal_Ready_Plugin::date_label( self::end_date( $sub ) ) ) ) . '</em></div>';
			} else {
				echo '</div><a class="rr-x-b" href="' . esc_url( self::url( $sub['id'] ) ) . '">' . esc_html__( 'End subscription', 'renewal-ready' ) . '</a>';
			}
			echo '</li>';
		}
		echo '</ul>';
	}

	/**
	 * Confirm screen.
	 *
	 * @param array $sub Record.
	 */
	private static function confirm( array $sub ) {
		if ( 'ending' === $sub['status'] ) {
			self::done( $sub );
			return;
		}
		$end = self::end_date( $sub );
		echo '<a class="rr-x-back" href="' . esc_url( self::url() ) . '">&lsaquo; ' . esc_html__( 'Back to your subscriptions', 'renewal-ready' ) . '</a>';
		/* translators: %s: product name. */
		echo '<h2 class="rr-x-h">' . esc_html( sprintf( __( 'End your %s subscription?', 'renewal-ready' ), $sub['product_name'] ) ) . '</h2>';
		echo '<p class="rr-x-lead">' . esc_html__( 'It will stop renewing and you won\'t be charged again.', 'renewal-ready' );
		if ( $end && $end > Renewal_Ready_Plugin::today() ) {
			/* translators: %s: date. */
			echo ' ' . esc_html( sprintf( __( 'You can keep using it until %s.', 'renewal-ready' ), Renewal_Ready_Plugin::date_label( $end ) ) );
		}
		echo '</p><dl class="rr-x-sum">';
		/* translators: 1: amount, 2: period such as month. */
		$price = sprintf( __( '%1$s every %2$s', 'renewal-ready' ), Renewal_Ready_Plugin::money( $sub['recurring_amount'], $sub['currency'] ), esc_html( Renewal_Ready_Plugin::frequency( $sub['period'], $sub['interval'] ) ) );
		echo '<div><dt>' . esc_html__( 'Subscription', 'renewal-ready' ) . '</dt><dd>' . esc_html( $sub['product_name'] ) . ', ' . wp_kses_post( $price ) . '</dd></div>';
		if ( $end ) {
			echo '<div><dt>' . esc_html__( 'Paid until', 'renewal-ready' ) . '</dt><dd>' . esc_html( Renewal_Ready_Plugin::date_label( $end ) ) . '</dd></div>';
		}
		echo '<div><dt>' . esc_html__( 'For', 'renewal-ready' ) . '</dt><dd>' . esc_html( $sub['email'] ) . '</dd></div></dl>';
		echo '<form method="post" class="rr-x-form">';
		wp_nonce_field( 'renewal_ready_end_' . $sub['id'], '_rr_nonce' );
		echo '<label class="rr-x-lab" for="rr-reason">' . esc_html__( 'Tell us why (optional)', 'renewal-ready' ) . '</label><span class="rr-x-hint">' . esc_html__( 'You can skip this.', 'renewal-ready' ) . '</span>';
		echo '<select class="rr-x-sel" name="rr_reason" id="rr-reason"><option value="">' . esc_html__( 'Choose a reason', 'renewal-ready' ) . '</option>';
		foreach ( self::reasons() as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select><div class="rr-x-btns"><button type="submit" class="rr-x-b" name="renewal_ready_end" value="' . esc_attr( $sub['id'] ) . '">' . esc_html__( 'End subscription', 'renewal-ready' ) . '</button>';
		echo '<a class="rr-x-b" href="' . esc_url( $sub['view_url'] ? $sub['view_url'] : self::url() ) . '">' . esc_html__( 'Keep my subscription', 'renewal-ready' ) . '</a></div></form>';
	}

	/**
	 * Done screen.
	 *
	 * @param array $sub Record.
	 */
	private static function done( array $sub ) {
		$end = self::end_date( $sub );
		echo '<div class="rr-x-panel" role="status"><svg viewBox="0 0 48 48" aria-hidden="true"><circle cx="24" cy="24" r="22" fill="#ffbf47"/><path d="m14 24.5 7 7 13-14" fill="none" stroke="#0b0c0c" stroke-width="4" stroke-linecap="square"/></svg>';
		echo '<h2>' . esc_html__( 'Done. Your subscription has ended.', 'renewal-ready' ) . '</h2><p>';
		/* translators: 1: email address, 2: date. */
		$text = esc_html__( 'We\'ve emailed a confirmation to %1$s. It ends on %2$s.', 'renewal-ready' );
		printf( $text, esc_html( $sub['email'] ), '<mark>' . esc_html( Renewal_Ready_Plugin::date_label( $end ? $end : Renewal_Ready_Plugin::today() ) ) . '</mark>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		echo '</p></div><section class="rr-x-next"><h3>' . esc_html__( 'What happens now', 'renewal-ready' ) . '</h3><ul>';
		echo '<li>' . esc_html__( 'We won\'t take any more payments.', 'renewal-ready' ) . '</li>';
		if ( $end && $end > Renewal_Ready_Plugin::today() ) {
			echo '<li>' . esc_html__( 'You keep what you\'ve paid for until the end date.', 'renewal-ready' ) . '</li>';
		}
		echo '<li>' . esc_html__( 'Keep the email as your proof.', 'renewal-ready' ) . '</li></ul></section>';
	}

	/**
	 * Exit page content: shortcode and block.
	 *
	 * @return string
	 */
	public static function shortcode() {
		wp_enqueue_style( 'renewal-ready-account' );
		$account = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );
		$email   = Renewal_Ready_Plugin::contact_email();
		$out     = '<section class="rr-exit"><h2>' . esc_html__( 'How to end your subscription', 'renewal-ready' ) . '</h2><ol>';
		/* translators: %s: My Account link. */
		$out .= '<li>' . sprintf( esc_html__( 'Log in to %s.', 'renewal-ready' ), '<a href="' . esc_url( $account ) . '">' . esc_html__( 'My Account', 'renewal-ready' ) . '</a>' ) . '</li>';
		$out .= '<li>' . esc_html__( 'Open Subscriptions and choose the one you want to end.', 'renewal-ready' ) . '</li>';
		$out .= '<li>' . esc_html__( 'Click End subscription and confirm. We\'ll email your confirmation straight away.', 'renewal-ready' ) . '</li></ol>';
		$out .= '<a class="rr-x-b dark rr-exit-go" href="' . esc_url( self::url() ) . '">' . esc_html__( 'End a subscription', 'renewal-ready' ) . '</a>';
		$out .= '<p class="rr-exit-help">' . sprintf(
			/* translators: 1: reset password link, 2: email link. */
			esc_html__( 'Can\'t log in? %1$s, or email us at %2$s and say clearly that you want to end your subscription.', 'renewal-ready' ),
			'<a href="' . esc_url( wp_lostpassword_url() ) . '">' . esc_html__( 'Reset your password', 'renewal-ready' ) . '</a>',
			'<a href="' . esc_url( 'mailto:' . $email ) . '">' . esc_html( $email ) . '</a>'
		) . '</p>';
		$phone = (string) Renewal_Ready_Plugin::get( 'contact_phone' );
		if ( '' !== $phone ) {
			/* translators: %s: phone number. */
			$out .= '<p class="rr-exit-help">' . esc_html( sprintf( __( 'You can also call us on %s.', 'renewal-ready' ), $phone ) ) . '</p>';
		}
		return $out . '</section>';
	}

	/**
	 * Create and publish the exit page.
	 *
	 * @return int Page id or 0.
	 */
	public static function create_page() {
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'How to end your subscription', 'renewal-ready' ),
				'post_name'    => 'cancel-subscription',
				'post_content' => '<!-- wp:renewal-ready/exit /-->',
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return 0;
		}
		Renewal_Ready_Plugin::update( array( 'exit_page' => (int) $id ) );
		return (int) $id;
	}
}

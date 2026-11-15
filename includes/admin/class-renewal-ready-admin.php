<?php
/**
 * Admin screen: WooCommerce › Renewal Ready.
 *
 * @package RenewalReady
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tabs, banners, settings and the one-click fixes from the readiness check.
 */
class Renewal_Ready_Admin {

	const PAGE = 'renewal-ready';
	const CAP  = 'manage_woocommerce';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_renewal_ready', array( __CLASS__, 'action' ) );
		add_action( 'admin_post_renewal_ready_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 100 );
		add_filter( 'plugin_action_links_' . plugin_basename( RENEWAL_READY_FILE ), array( __CLASS__, 'links' ) );
	}

	/**
	 * Link to a tab.
	 *
	 * @param string $tab  Tab.
	 * @param array  $args More query args.
	 * @return string
	 */
	public static function url( $tab = '', array $args = array() ) {
		$q = array( 'page' => self::PAGE );
		if ( '' !== $tab ) {
			$q['tab'] = $tab;
		}
		return add_query_arg( array_merge( $q, $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Settings link on the plugins list.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url( 'settings' ) ) . '">' . esc_html__( 'Settings', 'renewal-ready' ) . '</a>' );
		return $links;
	}

	/**
	 * Menu item.
	 */
	public static function menu() {
		add_submenu_page( 'woocommerce', __( 'Renewal Ready', 'renewal-ready' ), __( 'Renewal Ready', 'renewal-ready' ), self::CAP, self::PAGE, array( __CLASS__, 'page' ) );
	}

	/**
	 * A small reminder in the admin bar while in Preview mode, for shop managers only.
	 *
	 * @param WP_Admin_Bar $bar Bar.
	 */
	public static function admin_bar( $bar ) {
		if ( Renewal_Ready_Plugin::is_live() || ! is_admin() || ! current_user_can( self::CAP ) ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'renewal-ready-preview',
				'title' => esc_html__( 'Renewal Ready: Preview mode', 'renewal-ready' ),
				'href'  => self::url(),
			)
		);
	}

	/**
	 * Tabs. Pro replaces the locked ones through the filter.
	 *
	 * @return array slug => array( label, render, locked ).
	 */
	public static function tabs() {
		$lock = array( __CLASS__, 'locked' );
		$tabs = array(
			'overview'    => array(
				'label'  => __( 'Overview', 'renewal-ready' ),
				'render' => array( __CLASS__, 'tab_readiness' ),
			),
			'readiness'   => array(
				'label'  => __( 'Readiness', 'renewal-ready' ),
				'render' => array( __CLASS__, 'tab_readiness' ),
			),
			'settings'    => array(
				'label'  => __( 'Settings', 'renewal-ready' ),
				'render' => array( __CLASS__, 'tab_settings' ),
			),
			'emails'      => array(
				'label'  => __( 'Emails', 'renewal-ready' ),
				'render' => array( __CLASS__, 'tab_emails' ),
			),
			'cooling-off' => array(
				'label'  => __( 'Cooling-off', 'renewal-ready' ),
				'render' => $lock,
				'locked' => true,
			),
			'evidence'    => array(
				'label'  => __( 'Evidence', 'renewal-ready' ),
				'render' => $lock,
				'locked' => true,
			),
			'templates'   => array(
				'label'  => __( 'Templates', 'renewal-ready' ),
				'render' => $lock,
				'locked' => true,
			),
		);
		/**
		 * Add or replace tabs on the Renewal Ready screen.
		 *
		 * @param array $tabs slug => array( 'label' => string, 'render' => callable, 'locked' => bool ).
		 */
		$tabs = (array) apply_filters( 'renewal_ready_admin_tabs', $tabs );
		foreach ( $tabs as $slug => $t ) {
			if ( ! is_array( $t ) || empty( $t['label'] ) ) {
				unset( $tabs[ $slug ] );
				continue;
			}
			if ( empty( $t['render'] ) && ! empty( $t['callback'] ) ) {
				$tabs[ $slug ]['render'] = $t['callback'];
			}
		}
		return $tabs;
	}

	/**
	 * Current tab.
	 *
	 * @param array $tabs Tabs.
	 * @return string
	 */
	private static function current( array $tabs ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
		return isset( $tabs[ $tab ] ) ? $tab : (string) array_key_first( $tabs );
	}

	/**
	 * Is this our screen?
	 *
	 * @return bool
	 */
	private static function ours() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		return is_admin() && isset( $_GET['page'] ) && self::PAGE === sanitize_key( wp_unslash( $_GET['page'] ) );
	}

	/**
	 * Styles and script, only on our screen.
	 */
	public static function assets() {
		if ( ! self::ours() ) {
			return;
		}
		wp_enqueue_style( 'renewal-ready-admin', Renewal_Ready_Plugin::asset( 'css/admin.css' ), array(), RENEWAL_READY_VERSION );
		wp_enqueue_script( 'renewal-ready-admin', Renewal_Ready_Plugin::asset( 'js/admin.js' ), array(), RENEWAL_READY_VERSION, true );
	}

	/**
	 * Icon SVGs.
	 *
	 * @param string $s ok, warn or bad.
	 * @return string
	 */
	public static function icon( $s ) {
		$paths = array(
			'ok'   => '<circle cx="10" cy="10" r="9" fill="#00a32a"/><path d="M6 10.3 8.6 13 14 7.5" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
			'warn' => '<circle cx="10" cy="10" r="9" fill="#dba617"/><path d="M10 5.5v5.5" stroke="#fff" stroke-width="2" stroke-linecap="round"/><circle cx="10" cy="14.2" r="1.2" fill="#fff"/>',
			'bad'  => '<circle cx="10" cy="10" r="9" fill="#d63638"/><path d="m7 7 6 6m0-6-6 6" stroke="#fff" stroke-width="2" stroke-linecap="round"/>',
		);
		$p     = isset( $paths[ $s ] ) ? $paths[ $s ] : $paths['warn'];
		return '<svg viewBox="0 0 20 20" aria-hidden="true" focusable="false">' . $p . '</svg>';
	}

	/**
	 * SVG tags allowed in our output.
	 *
	 * @return array
	 */
	public static function svg_allowed() {
		return array(
			'svg'    => array(
				'viewbox'     => true,
				'aria-hidden' => true,
				'focusable'   => true,
				'class'       => true,
			),
			'path'   => array(
				'd'               => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
			),
			'circle' => array(
				'cx'           => true,
				'cy'           => true,
				'r'            => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
				'class'        => true,
			),
		);
	}

	/**
	 * Print an icon.
	 *
	 * @param string $s Status.
	 */
	private static function print_icon( $s ) {
		echo wp_kses( self::icon( $s ), self::svg_allowed() );
	}

	/**
	 * The page.
	 */
	public static function page() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$tabs    = self::tabs();
		$current = self::current( $tabs );
		$pro     = class_exists( 'RRP_Free' ) || defined( 'RENEWAL_READY_PRO_VERSION' );
		echo '<div class="wrap rr-admin">';
		echo '<div class="rr-tit"><h1>' . esc_html__( 'Renewal Ready', 'renewal-ready' ) . '</h1>';
		echo '<span class="a-pill">' . ( $pro ? esc_html__( 'Pro', 'renewal-ready' ) : esc_html__( 'Free', 'renewal-ready' ) ) . '</span></div>';
		echo '<nav class="nav-tab-wrapper rr-tabs" aria-label="' . esc_attr__( 'Renewal Ready', 'renewal-ready' ) . '">';
		foreach ( $tabs as $slug => $t ) {
			$cls = 'nav-tab' . ( $slug === $current ? ' nav-tab-active' : '' ) . ( ! empty( $t['locked'] ) ? ' a-pro' : '' );
			echo '<a class="' . esc_attr( $cls ) . '" href="' . esc_url( self::url( $slug ) ) . '">' . esc_html( $t['label'] );
			if ( ! empty( $t['locked'] ) ) {
				echo ' <em>' . esc_html__( 'Pro', 'renewal-ready' ) . '</em>';
			}
			echo '</a>';
		}
		echo '</nav>';
		self::messages();
		self::banner();
		if ( ! Renewal_Ready_Sources::active() ) {
			echo '<div class="a-banner a-err"><span class="a-bi">';
			self::print_icon( 'bad' );
			echo '</span><div><b>' . esc_html__( 'No subscription plugin found.', 'renewal-ready' ) . '</b> ' . esc_html__( 'Renewal Ready works with WP Swings Subscriptions, YITH WooCommerce Subscription and WooCommerce Subscriptions. Install one, and reminders start on the next daily run.', 'renewal-ready' ) . '</div></div>';
		}
		echo '<div class="rr-tab">';
		if ( is_callable( $tabs[ $current ]['render'] ) ) {
			call_user_func( $tabs[ $current ]['render'] );
		}
		echo '</div></div>';
	}

	/**
	 * Result messages after an action.
	 */
	private static function messages() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		$msg = isset( $_GET['rr-msg'] ) ? sanitize_key( wp_unslash( $_GET['rr-msg'] ) ) : '';
		$all = array(
			'saved'       => array( 'ok', __( 'Settings saved.', 'renewal-ready' ) ),
			'window'      => array( 'bad', __( 'The reminder dates weren\'t saved. Use 1 to 60 days, with the "from" day further out than the "to" day.', 'renewal-ready' ) ),
			'test'        => array( 'ok', __( 'Test sent. Check your inbox.', 'renewal-ready' ) ),
			'test_fail'   => array( 'bad', __( 'The test couldn\'t be sent. Check that WordPress can send email.', 'renewal-ready' ) ),
			'page'        => array( 'ok', __( 'Your "How to end your subscription" page is published.', 'renewal-ready' ) ),
			'page_fail'   => array( 'bad', __( 'The page couldn\'t be created.', 'renewal-ready' ) ),
			'placed'      => array( 'ok', __( 'The box now sits just above the checkout button.', 'renewal-ready' ) ),
			'place_fail'  => array( 'bad', __( 'We couldn\'t place the box. Open your checkout page in the editor and add the "Subscription key information" block above the button.', 'renewal-ready' ) ),
			'ran'         => array( 'ok', __( 'Daily run finished.', 'renewal-ready' ) ),
			'tick'        => array( 'ok', __( 'The payment tick is on.', 'renewal-ready' ) ),
			'preview'     => array( 'ok', __( 'Back in Preview mode. Customers get nothing until you go live.', 'renewal-ready' ) ),
			'email_bad'   => array( 'bad', __( 'That contact email doesn\'t look right, so it wasn\'t saved.', 'renewal-ready' ) ),
			'unavailable' => array( 'bad', __( 'That isn\'t available.', 'renewal-ready' ) ),
		);
		if ( ! isset( $all[ $msg ] ) ) {
			return;
		}
		echo '<div class="a-banner' . ( 'ok' === $all[ $msg ][0] ? ' a-live' : ' a-err' ) . '" role="status"><span class="a-bi">';
		self::print_icon( $all[ $msg ][0] );
		echo '</span><div>' . esc_html( $all[ $msg ][1] ) . '</div></div>';
	}

	/**
	 * Preview banner, or the live banner once.
	 */
	private static function banner() {
		if ( ! Renewal_Ready_Plugin::is_live() ) {
			echo '<div class="a-banner" id="rr-banner"><span class="a-bi">';
			self::print_icon( 'warn' );
			echo '</span><div><b>' . esc_html__( 'Preview mode.', 'renewal-ready' ) . '</b> ' . esc_html__( 'Notices are written to each subscription\'s notes but not emailed. Check a few, then go live.', 'renewal-ready' ) . '</div>';
			self::button( 'golive', __( 'Go live', 'renewal-ready' ), true );
			echo '</div>';
			return;
		}
		if ( Renewal_Ready_Plugin::state( 'live_banner' ) ) {
			Renewal_Ready_Plugin::set_state( 'live_banner', null );
			echo '<div class="a-banner a-live" role="status"><span class="a-bi">';
			self::print_icon( 'ok' );
			echo '</span><div>' . esc_html__( 'Renewal Ready is live. Reminders and confirmations now go to your customers.', 'renewal-ready' ) . '</div></div>';
		}
	}

	/**
	 * A one-click action button (a small form posting to admin-post.php).
	 *
	 * @param string $act     Action.
	 * @param string $label   Label.
	 * @param bool   $primary Primary style.
	 * @param array  $extra   Extra fields.
	 */
	public static function button( $act, $label, $primary = false, array $extra = array() ) {
		echo '<form class="rr-act" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="renewal_ready">';
		echo '<input type="hidden" name="do" value="' . esc_attr( $act ) . '">';
		foreach ( $extra as $k => $v ) {
			echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
		wp_nonce_field( 'renewal_ready_' . $act, '_rr_nonce' );
		echo '<button type="submit" class="button' . ( $primary ? ' button-primary' : '' ) . '">' . esc_html( $label ) . '</button></form>';
	}

	/**
	 * Locked Pro tab.
	 */
	public static function locked() {
		echo '<div class="a-card a-pad a-lock"><p>' . esc_html__( 'Cooling-off refunds, one-click exits and an evidence pack. Renewal Ready Pro, from £29 a year.', 'renewal-ready' ) . '</p>';
		/**
		 * Where "See Pro" points.
		 *
		 * @param string $url URL.
		 */
		$url = apply_filters( 'renewal_ready_pro_url', 'https://renewalready.facilo.es/?utm_source=plugin&utm_medium=locked-tab' );
		echo '<a class="button button-primary" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'See Pro', 'renewal-ready' ) . '</a></div>';
	}

	/**
	 * Disclaimer line.
	 */
	private static function disclaimer() {
		echo '<p class="a-disc">' . esc_html__( 'Renewal Ready is a tool to help you follow the UK\'s subscription rules. It isn\'t legal advice.', 'renewal-ready' ) . '</p>';
	}

	/**
	 * One readiness row.
	 *
	 * @param array $i Item.
	 */
	private static function row( array $i ) {
		$s = in_array( $i['status'], array( 'ok', 'warn', 'bad' ), true ) ? $i['status'] : 'warn';
		echo '<li class="a-row a-' . esc_attr( $s ) . '"><span class="a-ic">';
		self::print_icon( $s );
		echo '</span><div><b>' . esc_html( $i['title'] ) . '</b>';
		if ( '' !== $i['detail'] ) {
			echo '<p>' . esc_html( $i['detail'] ) . '</p>';
		}
		echo '</div>';
		if ( 'ok' !== $s && '' !== $i['fix'] ) {
			self::button( $i['fix'], '' !== $i['fix_label'] ? $i['fix_label'] : __( 'Fix it', 'renewal-ready' ), 'bad' === $s );
		} elseif ( 'ok' !== $s && '' !== $i['url'] ) {
			echo '<a class="button" href="' . esc_url( $i['url'] ) . '">' . esc_html( '' !== $i['fix_label'] ? $i['fix_label'] : __( 'Open', 'renewal-ready' ) ) . '</a>';
		}
		echo '</li>';
	}

	/**
	 * Readiness tab (also Free's overview).
	 */
	public static function tab_readiness() {
		$items = Renewal_Ready_Readiness::items();
		$total = count( $items );
		$ok    = Renewal_Ready_Readiness::ok_count( $items );
		$todo  = $total - $ok;
		$sum   = $todo
			/* translators: 1: ready, 2: total, 3: number to fix. */
			? sprintf( _n( '%1$d of %2$d ready. Fix the %3$d below and you\'re set.', '%1$d of %2$d ready. Fix the %3$d below and you\'re set.', $todo, 'renewal-ready' ), $ok, $total, $todo )
			/* translators: 1: ready, 2: total. */
			: sprintf( __( '%1$d of %2$d ready. All set.', 'renewal-ready' ), $ok, $total );
		$dash = $total ? round( 119.4 * $ok / $total, 1 ) : 0;
		echo '<div class="a-grid"><section class="a-card"><header class="a-card-h">';
		echo '<div class="a-ring" data-dash="' . esc_attr( $dash ) . '"><svg viewBox="0 0 46 46" aria-hidden="true"><circle class="a-ring-bg" cx="23" cy="23" r="19"/><circle class="a-ring-fg" cx="23" cy="23" r="19"/></svg><b>' . esc_html( $ok ) . '</b></div>';
		echo '<div><h2>' . esc_html__( 'Readiness check', 'renewal-ready' ) . '</h2><p>' . esc_html( $sum ) . '</p></div></header>';
		echo '<ol class="a-list">';
		foreach ( $items as $i ) {
			if ( 'ok' !== $i['status'] ) {
				self::row( $i );
			}
		}
		echo '</ol>';
		if ( $ok ) {
			/* translators: %d: number ready. */
			echo '<button type="button" class="a-more" aria-expanded="' . ( $todo ? 'false' : 'true' ) . '" aria-controls="rr-done"><span>' . esc_html( sprintf( __( '%d ready', 'renewal-ready' ), $ok ) ) . '</span><svg viewBox="0 0 14 14" aria-hidden="true"><path d="m3 5 4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></button>';
			echo '<ol class="a-list a-done" id="rr-done"' . ( $todo ? ' hidden' : '' ) . '>';
			foreach ( $items as $i ) {
				if ( 'ok' === $i['status'] ) {
					self::row( $i );
				}
			}
			echo '</ol>';
		}
		echo '</section><aside class="a-side">';
		self::side_run();
		self::side_sources();
		echo '</aside></div>';
		self::disclaimer();
	}

	/**
	 * Side card: the daily run.
	 */
	private static function side_run() {
		$last = (int) Renewal_Ready_Plugin::state( 'last_run', 0 );
		echo '<section class="a-card a-pad"><h3>' . esc_html__( 'What customers will get', 'renewal-ready' ) . '</h3><ul class="a-next">';
		echo '<li><span class="a-tag' . ( Renewal_Ready_Plugin::is_live() ? ' a-tlive' : '' ) . '">' . ( Renewal_Ready_Plugin::is_live() ? esc_html__( 'Will send', 'renewal-ready' ) : esc_html__( 'Would send', 'renewal-ready' ) ) . '</span>';
		echo '<b>' . esc_html__( 'Reminders due today', 'renewal-ready' ) . '</b><span>' . esc_html__( 'Next daily run', 'renewal-ready' ) . '</span><time>' . esc_html( wp_date( 'j M, H:i', Renewal_Ready_Runner::next_eight() ) ) . '</time></li>';
		echo '<li><b>' . esc_html__( 'Last run', 'renewal-ready' ) . '</b><span>' . ( $last ? esc_html( wp_date( 'j M, H:i', $last ) ) : esc_html__( 'Not yet', 'renewal-ready' ) ) . '</span></li></ul>';
		self::button( 'run_now', __( 'Run now', 'renewal-ready' ) );
		echo '</section>';
	}

	/**
	 * Side card: which subscription plugins were found.
	 */
	private static function side_sources() {
		echo '<section class="a-card a-pad"><h3>' . esc_html__( 'Works with', 'renewal-ready' ) . '</h3>';
		$using = Renewal_Ready_Sources::active();
		foreach ( Renewal_Ready_Sources::all() as $id => $src ) {
			$on = $src->is_active();
			echo '<p class="a-src' . ( $on ? '' : ' a-off' ) . '"><b>' . esc_html( $src->label() ) . '</b>';
			if ( 'wcs' === $id ) {
				echo ' <em>' . esc_html__( 'beta', 'renewal-ready' ) . '</em>';
			}
			if ( ! $on ) {
				echo '<span>' . esc_html__( 'Not installed', 'renewal-ready' ) . '</span>';
			} elseif ( $using && $using->id() === $src->id() ) {
				$n = count( (array) $src->ids( 'active', 0, 501 ) );
				/* translators: %s: count. */
				echo '<span>' . esc_html( sprintf( _n( '%s active subscription', '%s active subscriptions', $n, 'renewal-ready' ), $n > 500 ? '500+' : number_format_i18n( $n ) ) ) . '</span>';
			} else {
				echo '<span>' . esc_html__( 'Found, not in use', 'renewal-ready' ) . '</span>';
			}
			echo '</p>';
		}
		echo '</section>';
	}

	/**
	 * A yes/no checkbox row.
	 *
	 * @param string $key   Setting.
	 * @param string $label Label.
	 * @param string $help  Help text.
	 */
	private static function check( $key, $label, $help = '' ) {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td><label><input type="checkbox" name="rr[' . esc_attr( $key ) . ']" id="rr-' . esc_attr( $key ) . '" value="yes"' . checked( 'yes', Renewal_Ready_Plugin::get( $key ), false ) . '> ' . esc_html__( 'On', 'renewal-ready' ) . '</label>';
		if ( '' !== $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * A small number field.
	 *
	 * @param string $key Setting.
	 * @param int    $max Max.
	 * @return string
	 */
	private static function num( $key, $max = 60 ) {
		return '<input type="number" class="small-text" min="0" max="' . (int) $max . '" name="rr[' . esc_attr( $key ) . ']" value="' . esc_attr( (int) Renewal_Ready_Plugin::get( $key ) ) . '" aria-label="' . esc_attr( $key ) . '">';
	}

	/**
	 * Settings tab.
	 */
	public static function tab_settings() {
		$num_allowed = array(
			'input' => array(
				'type'       => true,
				'class'      => true,
				'min'        => true,
				'max'        => true,
				'name'       => true,
				'value'      => true,
				'aria-label' => true,
				'disabled'   => true,
			),
		);
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="a-card a-pad rr-settings">';
		echo '<input type="hidden" name="action" value="renewal_ready_save">';
		wp_nonce_field( 'renewal_ready_save', '_rr_nonce' );
		echo '<table class="form-table" role="presentation">';

		echo '<tr><th scope="row">' . esc_html__( 'Mode', 'renewal-ready' ) . '</th><td><fieldset>';
		foreach ( array(
			'preview' => __( 'Preview', 'renewal-ready' ),
			'live'    => __( 'Live', 'renewal-ready' ),
		) as $v => $l ) {
			echo '<label class="rr-radio"><input type="radio" name="rr[mode]" value="' . esc_attr( $v ) . '"' . checked( $v, Renewal_Ready_Plugin::get( 'mode' ), false ) . '> ' . esc_html( $l ) . '</label>';
		}
		echo '<p class="description">' . esc_html__( 'In Preview, notices are written to each subscription\'s notes but not emailed.', 'renewal-ready' ) . '</p></fieldset></td></tr>';

		echo '<tr><th scope="row"><label for="rr-customers">' . esc_html__( 'Customers', 'renewal-ready' ) . '</label></th><td><select name="rr[customers]" id="rr-customers">';
		foreach ( array(
			'all' => __( 'All customers', 'renewal-ready' ),
			'uk'  => __( 'Only customers with a UK billing address', 'renewal-ready' ),
		) as $v => $l ) {
			echo '<option value="' . esc_attr( $v ) . '"' . selected( $v, Renewal_Ready_Plugin::get( 'customers' ), false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></td></tr>';

		/* translators: 1: from field, 2: to field. */
		$line = __( 'from %1$s to %2$s days before the last day to end it', 'renewal-ready' );
		echo '<tr><th scope="row">' . esc_html__( 'Reminder window', 'renewal-ready' ) . '</th><td class="rr-inline">' . wp_kses( sprintf( esc_html( $line ), self::num( 'window_max' ), self::num( 'window_min' ) ), $num_allowed ) . '</td></tr>';
		/* translators: 1: from field, 2: to field. */
		$line = __( 'from %1$s to %2$s days before', 'renewal-ready' );
		echo '<tr><th scope="row">' . esc_html__( 'Early reminder for yearly plans', 'renewal-ready' ) . '</th><td class="rr-inline">' . wp_kses( sprintf( esc_html( $line ), self::num( 'additional_max', 120 ), self::num( 'additional_min', 120 ) ), $num_allowed ) . '</td></tr>';
		/* translators: %s: days field. */
		$line = __( 'Customers must end it at least %s days before a payment', 'renewal-ready' );
		echo '<tr><th scope="row">' . esc_html__( 'Notice period', 'renewal-ready' ) . '</th><td class="rr-inline">' . wp_kses( sprintf( esc_html( $line ), self::num( 'notice_days', 30 ) ), $num_allowed ) . '</td></tr>';

		echo '<tr><th scope="row"><label for="rr-exit_page">' . esc_html__( 'Exit page', 'renewal-ready' ) . '</label></th><td>';
		wp_dropdown_pages(
			array(
				'name'              => 'rr[exit_page]',
				'id'                => 'rr-exit_page',
				'selected'          => (int) Renewal_Ready_Plugin::get( 'exit_page' ),
				'show_option_none'  => esc_html__( '— Choose a page —', 'renewal-ready' ),
				'option_none_value' => '0',
			)
		);
		echo '<p class="description">' . esc_html__( 'Linked from every notice and from the checkout box.', 'renewal-ready' ) . '</p></td></tr>';

		echo '<tr><th scope="row"><label for="rr-contact_email">' . esc_html__( 'Contact email', 'renewal-ready' ) . '</label></th><td><input type="email" class="regular-text" name="rr[contact_email]" id="rr-contact_email" value="' . esc_attr( Renewal_Ready_Plugin::get( 'contact_email' ) ) . '" placeholder="' . esc_attr( get_option( 'admin_email' ) ) . '"></td></tr>';
		echo '<tr><th scope="row"><label for="rr-contact_phone">' . esc_html__( 'Contact phone', 'renewal-ready' ) . '</label></th><td><input type="tel" class="regular-text" name="rr[contact_phone]" id="rr-contact_phone" value="' . esc_attr( Renewal_Ready_Plugin::get( 'contact_phone' ) ) . '"></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Payment tick at checkout', 'renewal-ready' ) . '</th><td><label><input type="checkbox" name="rr[tick]" id="rr-tick" value="yes"' . checked( 'yes', Renewal_Ready_Plugin::get( 'tick' ), false ) . '> ' . esc_html__( 'On', 'renewal-ready' ) . '</label>';
		echo '<p class="rr-tick-warn"' . ( 'yes' === Renewal_Ready_Plugin::get( 'tick' ) ? ' hidden' : '' ) . '>' . esc_html__( 'Without it, customers may not be bound to pay.', 'renewal-ready' ) . '</p></td></tr>';
		self::check( 'button_label', __( '"Subscribe and pay" button label', 'renewal-ready' ) );
		self::check( 'product_box', __( 'Product page box', 'renewal-ready' ), __( 'Show the key information on subscription product pages too.', 'renewal-ready' ) );
		self::check( 'skip_company', __( 'Skip orders with a company name', 'renewal-ready' ), __( 'The rules protect consumers. Business buyers get no notices.', 'renewal-ready' ) );

		if ( ! class_exists( 'RRP_Free' ) ) {
			/* translators: %s: years field. */
			$line = __( 'Keep notice records for %s years', 'renewal-ready' );
			echo '<tr class="a-off"><th scope="row">' . esc_html__( 'Records', 'renewal-ready' ) . ' <em class="rr-pro">' . esc_html__( 'Pro', 'renewal-ready' ) . '</em></th><td class="rr-inline">' . wp_kses( sprintf( esc_html( $line ), '<input type="number" class="small-text" value="6" disabled aria-label="years">' ), $num_allowed ) . '</td></tr>';
		}
		self::check( 'delete_data', __( 'Delete data on uninstall', 'renewal-ready' ), __( 'Removes settings and notice records when you delete the plugin. Checkout agreements stay on their orders.', 'renewal-ready' ) );
		echo '</table>';
		/**
		 * Print more settings rows (inside the form).
		 */
		do_action( 'renewal_ready_settings_after' );
		submit_button( __( 'Save changes', 'renewal-ready' ) );
		echo '</form>';
		self::disclaimer();
	}

	/**
	 * Emails tab.
	 */
	public static function tab_emails() {
		$types = array(
			'reminder'   => __( 'Renewal reminder', 'renewal-ready' ),
			'additional' => __( 'Early yearly reminder', 'renewal-ready' ),
			'end'        => __( 'Ending confirmation', 'renewal-ready' ),
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		$type = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : 'reminder';
		if ( ! isset( $types[ $type ] ) ) {
			$type = 'reminder';
		}
		echo '<div class="a-grid"><section class="a-card"><header class="a-card-h rr-pick"><form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="' . esc_attr( self::PAGE ) . '"><input type="hidden" name="tab" value="emails">';
		echo '<label for="rr-notice">' . esc_html__( 'Notice', 'renewal-ready' ) . '</label> <select name="notice" id="rr-notice" class="rr-autosubmit">';
		foreach ( $types as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $k, $type, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select> <noscript><button class="button">' . esc_html__( 'Show', 'renewal-ready' ) . '</button></noscript></form>';
		self::button( 'test', __( 'Send me a test', 'renewal-ready' ), true, array( 'type' => $type ) );
		echo '</header>';
		$html = Renewal_Ready_Emails::preview_html( $type );
		echo '<iframe class="rr-preview" title="' . esc_attr( $types[ $type ] ) . '" sandbox="" srcdoc="' . esc_attr( $html ) . '"></iframe></section>';
		echo '<aside class="a-side"><section class="a-card a-pad"><h3>' . esc_html__( 'About these emails', 'renewal-ready' ) . '</h3>';
		echo '<p>' . esc_html__( 'They use your shop\'s WooCommerce email template. The key facts box comes first, as the rules ask.', 'renewal-ready' ) . '</p>';
		/* translators: %s: email address. */
		echo '<p>' . esc_html( sprintf( __( 'Tests go to %s.', 'renewal-ready' ), get_option( 'admin_email' ) ) ) . '</p>';
		$email = Renewal_Ready_Emails::get( $type );
		if ( $email ) {
			echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=email&section=' . strtolower( get_class( $email ) ) ) ) . '">' . esc_html__( 'Subject and wording', 'renewal-ready' ) . '</a>';
		}
		echo '</section></aside></div>';
	}

	/**
	 * Check the nonce and capability for a posted action.
	 *
	 * @param string $nonce_action Nonce action.
	 */
	private static function guard( $nonce_action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Sorry, you can\'t do that.', 'renewal-ready' ), 403 );
		}
		check_admin_referer( $nonce_action, '_rr_nonce' );
	}

	/**
	 * Go back to our screen with a message.
	 *
	 * @param string $msg Message key.
	 * @param string $tab Tab.
	 * @param array  $args More args.
	 */
	private static function back( $msg, $tab = '', array $args = array() ) {
		if ( '' === $tab ) {
			$ref = wp_get_referer();
			$tab = 'overview';
			if ( $ref ) {
				$q = array();
				wp_parse_str( (string) wp_parse_url( $ref, PHP_URL_QUERY ), $q );
				if ( ! empty( $q['tab'] ) ) {
					$tab = sanitize_key( $q['tab'] );
				}
				if ( ! empty( $q['notice'] ) ) {
					$args['notice'] = sanitize_key( $q['notice'] );
				}
			}
		}
		$args['rr-msg'] = $msg;
		wp_safe_redirect( self::url( $tab, $args ) );
		exit;
	}

	/**
	 * Run a posted one-click action.
	 */
	public static function action() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in guard().
		$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		self::guard( 'renewal_ready_' . $do );
		self::back( self::run_action( $do ) );
	}

	/**
	 * Do one action.
	 *
	 * @param string $action Action.
	 * @return string Message key.
	 */
	public static function run_action( $action ) {
		switch ( $action ) {
			case 'golive':
				Renewal_Ready_Plugin::update( array( 'mode' => 'live' ) );
				Renewal_Ready_Plugin::set_state( 'live_banner', 1 );
				return 'live';
			case 'test':
			case 'test_all':
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in guard().
				$type  = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
				$types = 'test' === $action && '' !== $type ? array( $type ) : array( 'reminder', 'additional', 'end' );
				$ok    = true;
				foreach ( $types as $t ) {
					$ok = Renewal_Ready_Emails::send_test( $t, (string) get_option( 'admin_email' ) ) && $ok;
				}
				return $ok ? 'test' : 'test_fail';
			case 'create_page':
				$id = Renewal_Ready_Exit::create_page();
				return $id ? 'page' : 'page_fail';
			case 'place_box':
				return Renewal_Ready_Checkout_Blocks::place() ? 'placed' : 'place_fail';
			case 'run_now':
				Renewal_Ready_Runner::run_now();
				return 'ran';
			case 'tick_on':
				Renewal_Ready_Plugin::update( array( 'tick' => 'yes' ) );
				return 'tick';
		}
		return 'unavailable';
	}

	/**
	 * Clean posted settings.
	 *
	 * @param array $in Raw values (unslashed).
	 * @return array array( values, message ).
	 */
	public static function clean( array $in ) {
		$out = array(
			'mode'      => isset( $in['mode'] ) && 'live' === $in['mode'] ? 'live' : 'preview',
			'customers' => isset( $in['customers'] ) && 'uk' === $in['customers'] ? 'uk' : 'all',
			'exit_page' => isset( $in['exit_page'] ) ? absint( $in['exit_page'] ) : 0,
		);
		foreach ( array( 'tick', 'button_label', 'product_box', 'skip_company', 'delete_data' ) as $k ) {
			$out[ $k ] = isset( $in[ $k ] ) && 'yes' === $in[ $k ] ? 'yes' : 'no';
		}
		$msg   = 'saved';
		$email = isset( $in['contact_email'] ) ? trim( sanitize_email( $in['contact_email'] ) ) : '';
		$raw   = isset( $in['contact_email'] ) ? trim( (string) $in['contact_email'] ) : '';
		if ( '' !== $raw && ! is_email( $email ) ) {
			$msg = 'email_bad';
		} else {
			$out['contact_email'] = $email;
		}
		$out['contact_phone'] = isset( $in['contact_phone'] ) ? sanitize_text_field( $in['contact_phone'] ) : '';
		$timing               = array();
		foreach ( array_keys( Renewal_Ready_Schedule::defaults() ) as $k ) {
			$timing[ $k ] = isset( $in[ $k ] ) ? absint( $in[ $k ] ) : (int) Renewal_Ready_Plugin::get( $k );
		}
		if ( '' === Renewal_Ready_Plugin::validate_timing( $timing ) ) {
			$out = array_merge( $out, $timing );
		} else {
			$msg = 'window';
		}
		return array( $out, $msg );
	}

	/**
	 * Save settings.
	 */
	public static function save() {
		self::guard( 'renewal_ready_save' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Checked in guard(); each value is cleaned in clean().
		$in   = isset( $_POST['rr'] ) && is_array( $_POST['rr'] ) ? wp_unslash( $_POST['rr'] ) : array();
		$was  = Renewal_Ready_Plugin::is_live();
		$res  = self::clean( $in );
		$vals = $res[0];
		Renewal_Ready_Plugin::update( $vals );
		if ( ! $was && Renewal_Ready_Plugin::is_live() ) {
			Renewal_Ready_Plugin::set_state( 'live_banner', 1 );
		}
		/**
		 * After Renewal Ready settings are saved.
		 *
		 * @param array $vals Saved values.
		 */
		do_action( 'renewal_ready_settings_saved', $vals );
		self::back( $res[1], 'settings' );
	}
}

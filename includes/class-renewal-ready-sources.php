<?php
/**
 * The subscription sources and their events.
 *
 * @package RenewalReady
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registry of source adapters.
 */
class Renewal_Ready_Sources {

	/**
	 * Adapters by id.
	 *
	 * @var Renewal_Ready_Source[]|null
	 */
	private static $all = null;

	/**
	 * Hook the active source.
	 */
	public static function init() {
		$src = self::active();
		if ( $src && method_exists( $src, 'hooks' ) ) {
			$src->hooks();
		}
	}

	/**
	 * Every adapter, active or not.
	 *
	 * @return Renewal_Ready_Source[]
	 */
	public static function all() {
		if ( null === self::$all ) {
			$list = array(
				'wps'  => new Renewal_Ready_Source_WPS(),
				'yith' => new Renewal_Ready_Source_YITH(),
				'wcs'  => new Renewal_Ready_Source_WCS(),
			);
			/**
			 * Add or replace subscription sources.
			 *
			 * @param Renewal_Ready_Source[] $list Adapters by id.
			 */
			$list      = apply_filters( 'renewal_ready_sources', $list );
			self::$all = array();
			foreach ( $list as $id => $src ) {
				if ( $src instanceof Renewal_Ready_Source ) {
					self::$all[ $id ] = $src;
				}
			}
		}
		return self::$all;
	}

	/**
	 * The source in use: the first one whose plugin is active.
	 *
	 * @return Renewal_Ready_Source|null
	 */
	public static function active() {
		foreach ( self::all() as $src ) {
			if ( $src->is_active() ) {
				return $src;
			}
		}
		return null;
	}

	/**
	 * Every source whose plugin is active.
	 *
	 * @return Renewal_Ready_Source[]
	 */
	public static function detected() {
		$out = array();
		foreach ( self::all() as $id => $src ) {
			if ( $src->is_active() ) {
				$out[ $id ] = $src;
			}
		}
		return $out;
	}

	/**
	 * Is any product in a list a subscription?
	 *
	 * @param WC_Product $product Product.
	 * @return array|null Terms from the source, or null.
	 */
	public static function terms_for( $product ) {
		$src = self::active();
		if ( ! $src || ! $product instanceof WC_Product ) {
			return null;
		}
		return $src->product_terms( $product );
	}

	/**
	 * A source reports a change to one subscription.
	 *
	 * @param Renewal_Ready_Source $src   Source.
	 * @param int       $id    Subscription id.
	 * @param string    $event ended, expired, renewed or reactivated.
	 * @param array     $ctx   Extra details (ended_now).
	 */
	public static function changed( Renewal_Ready_Source $src, $id, $event, array $ctx = array() ) {
		$id = (int) $id;
		if ( ! $id ) {
			return;
		}
		switch ( $event ) {
			case 'ended':
				Renewal_Ready_Notice_Log::ended( $src, $id, $ctx );
				break;
			case 'reactivated':
				$src->meta_set( $id, '_rr_end_at', null );
				$src->meta_set( $id, '_rr_end_by', null );
				Renewal_Ready_Notice_Log::forget( $src, $id, $id . '|end' );
				Renewal_Ready_Runner::process( $src, $id );
				break;
			case 'renewed':
				Renewal_Ready_Runner::process( $src, $id );
				break;
		}
		/**
		 * A subscription changed in its source.
		 *
		 * @param int    $id     Subscription id.
		 * @param string $event  Event.
		 * @param string $source Source id.
		 */
		do_action( 'renewal_ready_subscription_changed', $id, $event, $src->id() );
	}
}

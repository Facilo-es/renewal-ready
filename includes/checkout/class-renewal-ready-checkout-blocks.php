<?php
/**
 * Block (Store API) checkout.
 *
 * @package RenewalReady
 */

defined( 'ABSPATH' ) || exit;

/**
 * Inner block renewal-ready/key-info, Store API data, server-side refusal.
 */
class Renewal_Ready_Checkout_Blocks {

	const NS = 'renewal-ready';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'store_api' ) );
		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			self::store_api();
		}
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'validate' ), 10, 2 );
		add_filter( '__experimental_woocommerce_blocks_add_data_attributes_to_namespace', array( __CLASS__, 'data_namespace' ) );
		add_filter( 'render_block_data', array( __CLASS__, 'place_box' ) );
	}

	/**
	 * Let WooCommerce mark our inner block for the checkout's front end.
	 *
	 * @param array $items Namespaces.
	 * @return array
	 */
	public static function data_namespace( $items ) {
		$items[] = self::NS;
		return $items;
	}

	/**
	 * Put the box just before the place-order button on checkout pages saved without it.
	 * WooCommerce would otherwise add it at the end of the column, under the button.
	 *
	 * @param array $block Parsed block.
	 * @return array
	 */
	public static function place_box( $block ) {
		if ( 'woocommerce/checkout-fields-block' !== $block['blockName'] || empty( $block['innerBlocks'] ) ) {
			return $block;
		}
		$at = null;
		foreach ( $block['innerBlocks'] as $i => $inner ) {
			if ( 'renewal-ready/key-info' === $inner['blockName'] ) {
				return $block;
			}
			if ( 'woocommerce/checkout-actions-block' === $inner['blockName'] ) {
				$at = $i;
			}
		}
		if ( null === $at ) {
			return $block;
		}
		$box = array(
			'blockName'    => 'renewal-ready/key-info',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => '<div class="wp-block-renewal-ready-key-info"></div>',
			'innerContent' => array( '<div class="wp-block-renewal-ready-key-info"></div>' ),
		);
		array_splice( $block['innerBlocks'], $at, 0, array( $box ) );
		// Each null in innerContent stands for the next inner block, in order.
		$seen = 0;
		foreach ( $block['innerContent'] as $j => $part ) {
			if ( null === $part && $seen++ === $at ) {
				array_splice( $block['innerContent'], $j, 0, array( null ) );
				break;
			}
		}
		return $block;
	}

	/**
	 * Register the editor side of the inner block.
	 */
	public static function register_block() {
		wp_register_script( 'renewal-ready-key-info-editor', Renewal_Ready_Plugin::asset( 'js/key-info-editor.js' ), array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-i18n' ), RENEWAL_READY_VERSION, true );
		wp_set_script_translations( 'renewal-ready-key-info-editor', 'renewal-ready' );
		register_block_type( RENEWAL_READY_DIR . 'blocks/key-info' );
	}

	/**
	 * Store API: cart data for the box, and the consent value on checkout.
	 */
	public static function store_api() {
		static $done = false;
		if ( $done || ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}
		$done = true;
		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => 'cart',
				'namespace'       => self::NS,
				'data_callback'   => array( __CLASS__, 'cart_data' ),
				'schema_callback' => array( __CLASS__, 'cart_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);
		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => 'checkout',
				'namespace'       => self::NS,
				'schema_callback' => array( __CLASS__, 'checkout_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);
	}

	/**
	 * Cart data.
	 *
	 * @return array
	 */
	public static function cart_data() {
		$list = Renewal_Ready_Checkout::cart_terms();
		if ( ! $list ) {
			return array(
				'has_sub'   => false,
				'box'       => '',
				'tick'      => false,
				'tick_text' => '',
				'error'     => '',
				'button'    => '',
			);
		}
		return array(
			'has_sub'   => true,
			'box'       => wp_kses( Renewal_Ready_Checkout::box_html( $list ), Renewal_Ready_Checkout::box_allowed() ),
			'tick'      => Renewal_Ready_Checkout::tick_on(),
			'tick_text' => Renewal_Ready_Checkout::tick_text( $list ),
			'error'     => Renewal_Ready_Checkout_Classic::error_text(),
			'button'    => Renewal_Ready_Checkout::button_label(),
		);
	}

	/**
	 * Cart schema.
	 *
	 * @return array
	 */
	public static function cart_schema() {
		$str  = array(
			'type'     => 'string',
			'context'  => array( 'view', 'edit' ),
			'readonly' => true,
		);
		$bool = array(
			'type'     => 'boolean',
			'context'  => array( 'view', 'edit' ),
			'readonly' => true,
		);
		return array(
			'has_sub'   => $bool,
			'box'       => $str,
			'tick'      => $bool,
			'tick_text' => $str,
			'error'     => $str,
			'button'    => $str,
		);
	}

	/**
	 * Checkout schema.
	 *
	 * @return array
	 */
	public static function checkout_schema() {
		return array(
			'consent' => array(
				'description' => __( 'Whether the customer ticked the subscription payment box.', 'renewal-ready' ),
				'type'        => array( 'boolean', 'null' ),
				'context'     => array( 'view', 'edit' ),
			),
		);
	}

	/**
	 * Refuse the order without the tick, then store the record.
	 *
	 * @param WC_Order        $order   Order.
	 * @param WP_REST_Request $request Request.
	 * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException Without the tick.
	 */
	public static function validate( $order, $request ) {
		$list = Renewal_Ready_Checkout::order_terms( $order );
		if ( ! $list ) {
			return;
		}
		$ext     = $request->get_param( 'extensions' );
		$ticked  = is_array( $ext ) && ! empty( $ext[ self::NS ]['consent'] );
		$refused = Renewal_Ready_Checkout::tick_on() && ! $ticked;
		if ( $refused ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'renewal_ready_consent', esc_html( Renewal_Ready_Checkout_Classic::error_text() ), 400 );
		}
		Renewal_Ready_Checkout::record( $order, $list, $ticked, 'blocks' );
	}

	/**
	 * Front-end assets when the page holds the checkout block.
	 */
	public static function assets() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() || ! has_block( 'woocommerce/checkout' ) ) {
			return;
		}
		Renewal_Ready_Checkout::enqueue_style();
		wp_enqueue_script( 'renewal-ready-checkout-blocks', Renewal_Ready_Plugin::asset( 'js/checkout-blocks.js' ), array( 'wc-blocks-checkout', 'wp-element', 'wp-data', 'wp-plugins' ), RENEWAL_READY_VERSION, true );
	}

	/**
	 * Block markup for the inner block.
	 *
	 * @return string
	 */
	public static function markup() {
		return '<!-- wp:renewal-ready/key-info --><div class="wp-block-renewal-ready-key-info"></div><!-- /wp:renewal-ready/key-info -->';
	}

	/**
	 * Is the box placed on the shop's checkout page?
	 *
	 * @return string yes, classic, forced or none.
	 */
	public static function placement() {
		$id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'checkout' ) : 0;
		$p  = $id > 0 ? get_post( $id ) : null;
		if ( ! $p ) {
			return 'none';
		}
		if ( ! has_block( 'woocommerce/checkout', $p ) ) {
			return has_shortcode( $p->post_content, 'woocommerce_checkout' ) ? 'classic' : 'none';
		}
		// Without the block saved, place_box() still shows it above the button when the button block is there.
		$saved = false !== strpos( $p->post_content, 'wp:renewal-ready/key-info' );
		return $saved || false !== strpos( $p->post_content, 'wp:woocommerce/checkout-actions-block' ) ? 'yes' : 'forced';
	}

	/**
	 * Put the inner block just above the place-order button.
	 *
	 * @return bool
	 */
	public static function place() {
		$id = (int) wc_get_page_id( 'checkout' );
		$p  = $id > 0 ? get_post( $id ) : null;
		if ( ! $p || 'forced' !== self::placement() ) {
			return false;
		}
		$at = strpos( $p->post_content, '<!-- wp:woocommerce/checkout-actions-block' );
		if ( false === $at ) {
			return false;
		}
		$content = substr( $p->post_content, 0, $at ) . self::markup() . "\n\n" . substr( $p->post_content, $at );
		return ! is_wp_error(
			wp_update_post(
				array(
					'ID'           => $id,
					'post_content' => wp_slash( $content ),
				),
				true
			)
		);
	}
}

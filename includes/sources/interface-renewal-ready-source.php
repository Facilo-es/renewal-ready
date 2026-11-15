<?php
/**
 * What every subscription source adapter provides.
 *
 * @package RenewalReady
 */

defined( 'ABSPATH' ) || exit;

/**
 * A subscription plugin underneath Renewal Ready.
 *
 * Records are arrays with: id, source, customer_id, email, first_name, country,
 * company, status (active, ending, ended or other), start_date, next_payment_date,
 * end_date, period, interval, recurring_amount, currency, trial_end, intro,
 * previous_payment, payments_count, paid_total, product_ids, product_name,
 * payment_method_title, can_end_at_term, view_url. Dates are Y-m-d in site time.
 */
interface Renewal_Ready_Source {

	/**
	 * Short id: wps, yith or wcs.
	 *
	 * @return string
	 */
	public function id();

	/**
	 * Name shown to the merchant.
	 *
	 * @return string
	 */
	public function label();

	/**
	 * Whether the source plugin is active.
	 *
	 * @return bool
	 */
	public function is_active();

	/**
	 * Normalised record, or null.
	 *
	 * @param int $id Subscription id.
	 * @return array|null
	 */
	public function get( $id );

	/**
	 * Ids of subscriptions in a state, a page at a time.
	 *
	 * @param string $state  active or ended.
	 * @param int    $offset Offset.
	 * @param int    $limit  Page size.
	 * @return int[]
	 */
	public function ids( $state, $offset, $limit );

	/**
	 * Ids of a customer's subscriptions.
	 *
	 * @param int $user_id User id.
	 * @return int[]
	 */
	public function ids_for_customer( $user_id );

	/**
	 * End a subscription at the end of the paid period.
	 *
	 * @param int    $id  Subscription id.
	 * @param string $who customer or admin.
	 * @return string|WP_Error End date (Y-m-d) or an error.
	 */
	public function end( $id, $who = 'customer' );

	/**
	 * Add a note where the merchant will see it.
	 *
	 * @param int    $id   Subscription id.
	 * @param string $text Note.
	 */
	public function note( $id, $text );

	/**
	 * Read our own meta.
	 *
	 * @param int    $id  Subscription id.
	 * @param string $key Key.
	 * @return mixed
	 */
	public function meta_get( $id, $key );

	/**
	 * Write our own meta (null deletes it).
	 *
	 * @param int    $id    Subscription id.
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 */
	public function meta_set( $id, $key, $value );

	/**
	 * Whether a product is a subscription in this source.
	 *
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	public function is_subscription_product( $product );

	/**
	 * Billing terms of a subscription product: period, interval, price, trial_days,
	 * signup_fee, intro (array or null), min_term (count of payments or 0).
	 *
	 * @param WC_Product $product Product.
	 * @return array|null
	 */
	public function product_terms( $product );
}

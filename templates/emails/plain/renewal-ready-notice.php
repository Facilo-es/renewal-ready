<?php
/**
 * Renewal Ready email (plain text).
 *
 * @package RenewalReady
 * @var string $email_heading
 * @var string $box_heading
 * @var array  $rows
 * @var string $button
 * @var string $button_url
 * @var string $or_line
 * @var string $message
 * @var string $exit_url
 * @var string $additional_content
 */

defined( 'ABSPATH' ) || exit;

echo esc_html( wp_strip_all_tags( $email_heading ) ) . "\n\n";
echo "==========\n";
echo esc_html( strtoupper( $box_heading ) ) . "\n\n";
foreach ( $rows as $renewal_ready_row ) {
	$renewal_ready_text = html_entity_decode( wp_strip_all_tags( $renewal_ready_row[1] ), ENT_QUOTES, 'UTF-8' );
	echo ( ! empty( $renewal_ready_row[2] ) ? '>> ' : '- ' ) . esc_html( $renewal_ready_text ) . "\n";
}
if ( $button ) {
	echo "\n" . esc_html( $button ) . ': ' . esc_url_raw( $button_url ) . "\n";
	echo esc_html( $or_line ) . "\n";
}
echo "==========\n\n";
echo esc_html( $message ) . "\n\n";
if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}
if ( $exit_url ) {
	echo esc_html__( 'How to end your subscription:', 'renewal-ready' ) . ' ' . esc_url_raw( $exit_url ) . "\n\n";
}
echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce email footer text.

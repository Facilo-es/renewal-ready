<?php
/**
 * Renewal Ready email (HTML). The statutory box comes first.
 *
 * Override by copying to yourtheme/woocommerce/emails/renewal-ready-notice.php.
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
 * @var WC_Email $email
 */

defined( 'ABSPATH' ) || exit;

$renewal_ready_allowed = array( 'strong' => array() );

do_action( 'woocommerce_email_header', $email_heading, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce email header.
?>
<table class="rr-box" role="presentation" width="100%" cellpadding="0" cellspacing="0">
	<tr><td class="rr-box-in">
		<h2 class="rr-box-h"><?php echo esc_html( $box_heading ); ?></h2>
		<table class="rr-rows" role="presentation" width="100%" cellpadding="0" cellspacing="0">
			<?php foreach ( $rows as $renewal_ready_row ) : ?>
				<tr class="<?php echo ! empty( $renewal_ready_row[2] ) ? 'rr-key' : 'rr-row'; ?>">
					<th scope="row" class="rr-dt"><?php echo esc_html( $renewal_ready_row[0] ); ?></th>
					<td class="rr-dd"><?php echo wp_kses( $renewal_ready_row[1], $renewal_ready_allowed ); ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php if ( $button ) : ?>
			<p class="rr-btn-p"><a class="rr-btn" href="<?php echo esc_url( $button_url ); ?>"><?php echo esc_html( $button ); ?></a></p>
			<p class="rr-or"><?php echo esc_html( $or_line ); ?></p>
		<?php endif; ?>
	</td></tr>
</table>

<p class="rr-p"><?php echo esc_html( $message ); ?></p>

<?php if ( $additional_content ) : ?>
	<p class="rr-p"><?php echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) ); ?></p>
<?php endif; ?>

<?php if ( $exit_url ) : ?>
	<p class="rr-foot"><?php esc_html_e( 'How to end your subscription:', 'renewal-ready' ); ?> <a href="<?php echo esc_url( $exit_url ); ?>"><?php echo esc_html( $exit_url ); ?></a></p>
<?php endif; ?>
<?php
do_action( 'woocommerce_email_footer', $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce email footer.

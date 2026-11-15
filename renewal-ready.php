<?php
/**
 * Plugin Name:          Renewal Ready – UK Subscription Rules for WooCommerce
 * Plugin URI:           https://renewalready.facilo.es
 * Description:          Renewal reminders, a clear subscription checkout and cancelling online, for the UK's January 2027 subscription rules. Works with the subscription plugin you already use.
 * Version:              1.0.0
 * Requires at least:    6.5
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * Author:               Facilo
 * Author URI:           https://facilo.es
 * License:              GPLv2 or later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          renewal-ready
 * Domain Path:          /languages
 * WC requires at least: 8.9
 * WC tested up to:      11.2
 *
 * @package RenewalReady
 */

defined( 'ABSPATH' ) || exit;

define( 'RENEWAL_READY_VERSION', '1.0.0' );
define( 'RENEWAL_READY_FILE', __FILE__ );
define( 'RENEWAL_READY_DIR', plugin_dir_path( __FILE__ ) );
define( 'RENEWAL_READY_URL', plugin_dir_url( __FILE__ ) );

require_once RENEWAL_READY_DIR . 'includes/class-renewal-ready-schedule.php';
require_once RENEWAL_READY_DIR . 'includes/class-renewal-ready-plugin.php';

Renewal_Ready_Plugin::boot();

register_activation_hook( __FILE__, array( 'Renewal_Ready_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Renewal_Ready_Plugin', 'deactivate' ) );

<?php
/**
 * Plugin Name: Hellio Messaging for WooCommerce
 * Plugin URI: https://helliomessaging.com
 * Description: Send order-status SMS, admin new-order alerts, checkout phone OTP verification, and bulk marketing SMS through the Hellio Messaging API.
 * Version: 1.0.0
 * Author: Hellio Solutions
 * Author URI: https://helliomessaging.com
 * Text Domain: hellio-sms
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 5.8
 * WC requires at least: 6.0
 * WC tested up to: 9.3
 * License: GPL-2.0-or-later
 *
 * @package Hellio_SMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HELLIO_SMS_VERSION', '1.0.0' );
define( 'HELLIO_SMS_FILE', __FILE__ );
define( 'HELLIO_SMS_PATH', plugin_dir_path( __FILE__ ) );
define( 'HELLIO_SMS_URL', plugin_dir_url( __FILE__ ) );
define( 'HELLIO_SMS_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS).
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				HELLIO_SMS_FILE,
				true
			);
		}
	}
);

/**
 * Show an admin notice and bail if WooCommerce is not active.
 *
 * @return bool True when WooCommerce is available.
 */
function hellio_sms_requires_woocommerce() {
	if ( class_exists( 'WooCommerce' ) ) {
		return true;
	}

	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Hellio Messaging for WooCommerce requires WooCommerce to be installed and active.', 'hellio-sms' );
			echo '</p></div>';
		}
	);

	return false;
}

/**
 * Load the plugin once all plugins are available.
 */
function hellio_sms_bootstrap() {
	if ( ! hellio_sms_requires_woocommerce() ) {
		return;
	}

	require_once HELLIO_SMS_PATH . 'includes/class-hellio-client.php';
	require_once HELLIO_SMS_PATH . 'includes/class-hellio-settings.php';
	require_once HELLIO_SMS_PATH . 'includes/class-hellio-order-sms.php';
	require_once HELLIO_SMS_PATH . 'includes/class-hellio-admin-alert.php';
	require_once HELLIO_SMS_PATH . 'includes/class-hellio-otp.php';
	require_once HELLIO_SMS_PATH . 'includes/class-hellio-bulk.php';

	Hellio_SMS_Settings::instance();
	Hellio_SMS_Order_SMS::instance();
	Hellio_SMS_Admin_Alert::instance();
	Hellio_SMS_OTP::instance();
	Hellio_SMS_Bulk::instance();
}
add_action( 'plugins_loaded', 'hellio_sms_bootstrap' );

/**
 * Add a Settings link on the plugins list row.
 *
 * @param array $links Existing action links.
 * @return array
 */
function hellio_sms_action_links( $links ) {
	$settings_url = admin_url( 'admin.php?page=hellio-sms-settings' );
	$settings     = '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'hellio-sms' ) . '</a>';
	array_unshift( $links, $settings );

	return $links;
}
add_filter( 'plugin_action_links_' . HELLIO_SMS_BASENAME, 'hellio_sms_action_links' );

/**
 * Load translations.
 */
add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'hellio-sms', false, dirname( HELLIO_SMS_BASENAME ) . '/languages' );
	}
);

<?php
/**
 * Uninstall cleanup for Hellio Messaging for WooCommerce.
 *
 * Removes plugin options. Order notes and order meta left on orders are
 * historical records and are intentionally not deleted.
 *
 * @package Hellio_SMS
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'hellio_sms_settings' );

// Clean up on multisite installs too.
if ( is_multisite() ) {
	global $wpdb;

	$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	foreach ( (array) $blog_ids as $blog_id ) {
		switch_to_blog( (int) $blog_id );
		delete_option( 'hellio_sms_settings' );
		restore_current_blog();
	}
}

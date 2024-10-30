<?php
/**
 * Uninstall file, which would delete all user metadata and configuration settings
 *
 * @since 1.0
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

global $wpdb;

$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name='learndash_salesforce_settings'" );

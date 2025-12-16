<?php
/**
 * Uninstall script for the Restrictly plugin.
 *
 * Removes all stored data, including:
 * - Plugin options from `wp_options`
 * - Post meta from `wp_postmeta`
 * - Transients and cache
 * - Safety pattern-based cleanup for any "restrictly_*" keys
 *
 * @package Restrictly
 * @since 0.1.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// -----------------------------------------------------------------------------
// OPTION CLEANUP
// -----------------------------------------------------------------------------
$restrictly_option_keys = array(
	// Core options.
	'restrictly_options',
	'restrictly_content_types',
	'restrictly_default_action',
	'restrictly_default_message',
	'restrictly_default_forward_url',
	'restrictly_enable_menu_flags',
	'restrictly_always_allow_admins',

	// Redirects / login logic (future-proof).
	'restrictly_login_redirect',
	'restrictly_logout_redirect',
	'restrictly_role_login_redirects',
	'restrictly_role_logout_redirects',

	// FSE + Editor settings.
	'restrictly_show_nav_pills', // newly added toggle for editor indicators.
);

foreach ( $restrictly_option_keys as $restrictly_option_name ) {
	delete_option( $restrictly_option_name );
}

// Transient cleanup.
delete_transient( 'restrictly_mismatch_flag' );

// -----------------------------------------------------------------------------
// POST META CLEANUP
// -----------------------------------------------------------------------------
$restrictly_meta_keys = array(
	'restrictly_page_access_by_login_status',
	'restrictly_page_access_by_role',
	'restrictly_enforcement_action',
	'restrictly_custom_message',
	'restrictly_custom_forward_url',
	'restrictly_menu_visibility',
	'restrictly_menu_roles',
);

foreach ( $restrictly_meta_keys as $restrictly_meta_key ) {
	delete_metadata( 'post', 0, $restrictly_meta_key, '', true );
}

// -----------------------------------------------------------------------------
// DATABASE CLEANUP (catch-all safety)
// -----------------------------------------------------------------------------
global $wpdb;
$restrictly_prefix_like = $wpdb->esc_like( 'restrictly_' ) . '%';

// Delete all Restrictly-related postmeta.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
		$restrictly_prefix_like
	)
);

// Delete all Restrictly-related options.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$restrictly_prefix_like
	)
);

// -----------------------------------------------------------------------------
// FINAL CACHE FLUSH
// -----------------------------------------------------------------------------
wp_cache_flush();

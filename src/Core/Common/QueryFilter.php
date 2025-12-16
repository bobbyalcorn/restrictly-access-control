<?php
/**
 * Handles front-end WP_Query filtering for restricted content.
 *
 * Ensures that restricted posts, pages, or custom post types are excluded
 * from public-facing search and archive queries based on Restrictly™
 * access rules (login status and roles).
 *
 * @package Restrictly
 *
 * @since 0.1.0
 */

namespace Restrictly\Core\Common;

use WP_Query;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filters WP_Query results to hide restricted content from unauthorized users.
 *
 * @since 0.1.0
 */
class QueryFilter {

	/**
	 * Initializes the Restrictly™ query filter.
	 *
	 * Hooks into WordPress' query lifecycle to ensure that restricted
	 * content is excluded from search results and archive listings.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function init(): void {
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_restricted_content' ) );
	}

	/**
	 * Filters restricted content out of search and archive queries for unauthorized users.
	 *
	 * Adds meta query constraints to hide posts and pages that the current user
	 * should not have access to based on Restrictly™ visibility rules.
	 *
	 * @param \WP_Query $query The current query object.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function filter_restricted_content( WP_Query $query ): void {
		// Only affect front-end, main queries.
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// Restrict search results and optionally archives.
		if ( $query->is_search() || $query->is_archive() || $query->is_home() ) {
			$user_roles = is_user_logged_in() ? wp_get_current_user()->roles : array();

			$meta_query = array(
				'relation' => 'OR',

				// Allow content with no restriction meta.
				array(
					'key'     => 'restrictly_page_access_by_login_status',
					'compare' => 'NOT EXISTS',
				),

				// Allow content visible to everyone.
				array(
					'key'     => 'restrictly_page_access_by_login_status',
					'value'   => 'everyone',
					'compare' => '=',
				),

				// Allow content visible to logged-out users.
				array(
					'key'     => 'restrictly_page_access_by_login_status',
					'value'   => 'logged_out_users',
					'compare' => '=',
				),
			);

			// If logged in, include content visible to logged-in users or by role.
			if ( is_user_logged_in() ) {
				$meta_query[] = array(
					'key'     => 'restrictly_page_access_by_login_status',
					'value'   => 'logged_in_users',
					'compare' => '=',
				);

				$meta_query[] = array(
					'key'     => 'restrictly_page_access_by_role',
					'value'   => $user_roles,
					'compare' => 'IN',
				);
			}

			// Merge with any existing meta_query safely.
			$existing_meta_query = $query->get( 'meta_query' );
			if ( ! is_array( $existing_meta_query ) ) {
				$existing_meta_query = array();
			}

			// Keep all relations consistent (outer AND with inner OR).
			$merged_meta_query = array_merge( $existing_meta_query, array( $meta_query ) );

			$query->set( 'meta_query', $merged_meta_query );
		}
	}
}

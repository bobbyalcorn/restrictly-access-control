<?php
/**
 * Abstract base class for content type management.
 *
 * @package Restrictly
 * @since   0.1.0
 */

namespace Restrictly\Core\Admin;

use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract base class for content type management.
 *
 * @since 0.1.0
 */
abstract class ContentTypeBase {

	/**
	 * Initialize content type functionality.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function init(): void {
		// Only run in the admin.
		if ( ! is_admin() ) {
			return;
		}

		// Get the selected content types.
		$content_types = get_option( 'restrictly_content_types', array( 'page' ) );

		// Add filters and actions for each selected content type.
		foreach ( $content_types as $post_type ) {
			add_filter( "manage_edit-{$post_type}_sortable_columns", array( __CLASS__, 'make_restrictly_columns_sortable' ) );
		}

		// Sort columns.
		add_action( 'pre_get_posts', array( __CLASS__, 'sort_restrictly_columns' ) );
	}

	/**
	 * Make columns sortable.
	 *
	 * @param array<string,string> $columns Existing sortable columns.
	 * @return array<string,string> Modified sortable columns.
	 *
	 * @since 0.1.0
	 */
	public static function make_restrictly_columns_sortable( array $columns ): array {
		$columns['restrictly_login_status']       = 'restrictly_page_access_by_login_status';
		$columns['restrictly_user_role']          = 'restrictly_page_access_by_role';
		$columns['restrictly_enforcement_action'] = 'restrictly_enforcement_action';
		return $columns;
	}

	/**
	 * Handle column sorting.
	 *
	 * @param WP_Query $query Query object.
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function sort_restrictly_columns( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$orderby = $query->get( 'orderby' );

		switch ( $orderby ) {
			case 'restrictly_page_access_by_login_status':
			case 'restrictly_page_access_by_role':
			case 'restrictly_enforcement_action':
				$query->set( 'meta_key', $orderby );
				$query->set( 'orderby', 'meta_value' );
				break;
		}
	}

	/**
	 * Get a human-readable version of the login status label.
	 *
	 * @param string $status Raw login status slug.
	 * @return string Human-readable login status label.
	 *
	 * @since 0.1.0
	 */
	protected static function get_readable_login_status( string $status ): string {
		switch ( $status ) {
			case 'logged_in_users':
				return __( 'Logged In Users', 'restrictly-access-control' );
			case 'logged_out_users':
				return __( 'Logged Out Users', 'restrictly-access-control' );
			default:
				return __( 'Everyone', 'restrictly-access-control' );
		}
	}

	/**
	 * Get a human-readable version of the enforcement action label.
	 *
	 * @param string $action Raw enforcement action slug.
	 * @return string Human-readable enforcement action label.
	 *
	 * @since 0.1.0
	 */
	protected static function get_readable_enforcement_action( string $action ): string {
		switch ( $action ) {
			case 'custom_message':
				return __( 'Custom Message', 'restrictly-access-control' );
			case 'custom_url':
				return __( 'Custom URL', 'restrictly-access-control' );
			case 'not_applicable':
				return __( '-', 'restrictly-access-control' );
			default:
				return __( 'Use Default', 'restrictly-access-control' );
		}
	}

	/**
	 * Add custom columns to the list table.
	 *
	 * @param array<string,string> $columns Existing columns.
	 * @return array<string,string> Modified columns including Restrictly™ columns.
	 *
	 * @since 0.1.0
	 */
	public static function add_restrictly_columns( array $columns ): array {
		$new_columns = array();
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( 'title' === $key ) {
				$new_columns['restrictly_login_status']       = esc_html__( 'Logged In Status', 'restrictly-access-control' );
				$new_columns['restrictly_user_role']          = esc_html__( 'User Role', 'restrictly-access-control' );
				$new_columns['restrictly_enforcement_action'] = esc_html__( 'Enforcement Action', 'restrictly-access-control' );
			}
		}
		return $new_columns;
	}

	/**
	 * Render custom column content.
	 *
	 * @param string $column Column name.
	 * @param int    $post_id Post ID.
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function render_restrictly_columns( string $column, int $post_id ): void {
		// Get the login status.
		$login_status = get_post_meta( $post_id, 'restrictly_page_access_by_login_status', true );
		$login_status = empty( $login_status ) ? 'everyone' : esc_html( $login_status );

		// Get the user role.
		$role = get_post_meta( $post_id, 'restrictly_page_access_by_role', true );
		$role = empty( $role ) ? __( 'Any', 'restrictly-access-control' ) : esc_html( implode( ', ', (array) $role ) );

		// Get the enforcement action.
		$enforcement_action = get_post_meta( $post_id, 'restrictly_enforcement_action', true );
		$enforcement_action = empty( $enforcement_action ) ? 'default' : esc_html( $enforcement_action );

		// Get the custom message.
		$custom_message = get_post_meta( $post_id, 'restrictly_custom_message', true );
		$custom_message = esc_textarea( $custom_message );

		// Get the custom forward URL.
		$custom_forward_url = get_post_meta( $post_id, 'restrictly_custom_forward_url', true );
		$custom_forward_url = esc_url_raw( wp_unslash( $custom_forward_url ) );

		// Set the role and enforcement action to $not_applicable if login status is everyone.
		if ( 'everyone' === $login_status ) {
			$role               = '-';
			$enforcement_action = 'not_applicable';
		}

		// Set the role to $not_applicable if login status is logged_out_users.
		if ( 'Logged Out Users' === self::get_readable_login_status( $login_status ) ) {
			$role = '-';
		}

		switch ( $column ) {
			case 'restrictly_login_status':
				printf(
					'<div class="restrictly_hidden_login_status restrictly-show-none" data-restrictly-login-status="%s"></div>%s',
					esc_attr( $login_status ),
					esc_html( self::get_readable_login_status( $login_status ) )
				);
				break;
			case 'restrictly_user_role':
				printf(
					'<div class="restrictly_hidden_user_role restrictly-show-none" data-restrictly-user-role="%s"></div>%s',
					esc_attr( $role ),
					esc_html( $role )
				);
				break;
			case 'restrictly_enforcement_action':
				printf(
					'<div class="restrictly_hidden_enforcement_action restrictly-show-none" data-restrictly-enforcement-action="%s" data-restrictly-custom-message="%s" data-restrictly-custom-forward-url="%s"></div>%s',
					esc_attr( $enforcement_action ),
					esc_attr( $custom_message ),
					esc_attr( esc_url( $custom_forward_url ) ),
					esc_html( self::get_readable_enforcement_action( $enforcement_action ) )
				);
				break;
		}
	}
}

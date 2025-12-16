<?php
/**
 * Enforces content access restrictions in the Restrictly plugin.
 *
 * This file contains the Enforcement class, which ensures that users can only access
 * content based on their login status and assigned roles. If access is restricted,
 * users are either redirected or shown a custom message.
 *
 * @package Restrictly
 * @since   0.1.0
 */

namespace Restrictly\Core\Common;

defined( 'ABSPATH' ) || exit;

/**
 * Handles page access enforcement based on user roles and login status.
 *
 * This class is responsible for:
 * - Enforcing login-based access restrictions.
 * - Enforcing role-based access restrictions.
 * - Handling enforcement actions (showing a message or redirecting).
 *
 * @since 0.1.0
 */
class Enforcement {

	/**
	 * Initializes the enforcement logic.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function init(): void {
		// Hook into the template_redirect action.
		add_action( 'template_redirect', array( __CLASS__, 'restrictly_enforce_page_access' ) );

		// Filter menu items before rendering.
		add_filter( 'wp_nav_menu_objects', array( __CLASS__, 'restrictly_filter_menu_items' ), 10, 2 );

		// Enforce visibility on individual Gutenberg blocks (FSE or classic).
		add_filter( 'render_block', array( __CLASS__, 'restrictly_enforce_block_visibility' ), 10, 2 );

		// Add default REST redaction message.
		add_filter(
			'restrictly_rest_redact_response',
			array( __CLASS__, 'add_rest_redaction_message' ),
			10,
			3
		);
	}

	/**
	 * Enforces page access restrictions based on login status and user roles.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function restrictly_enforce_page_access(): void {
		// Skip enforcement for admin and AJAX requests.
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}

		// Get the current post object.
		global $post;

		// Only restrict single pages, posts, or CPTs.
		if ( ! is_singular() || null === $post->ID || 0 === $post->ID ) {
			return;
		}

		// Get the restriction settings for this content.
		$post_id            = $post->ID;
		$login_status       = get_post_meta( $post_id, 'restrictly_page_access_by_login_status', true );
		$allowed_roles      = get_post_meta( $post_id, 'restrictly_page_access_by_role', true );
		$enforcement_action = get_post_meta( $post_id, 'restrictly_enforcement_action', true );
		$custom_message     = get_post_meta( $post_id, 'restrictly_custom_message', true );
		$custom_forward_url = get_post_meta( $post_id, 'restrictly_custom_forward_url', true );

		// Ensure roles are an array.
		$allowed_roles = is_array( $allowed_roles ) ? $allowed_roles : array();

		// Fallback to Global Defaults When "Use Default" is Selected.
		if ( empty( $enforcement_action ) || 'default' === $enforcement_action ) {
			$enforcement_action = get_option( 'restrictly_default_action', 'custom_message' );

			// Ignore any saved page-specific values if "Use Default" is selected.
			$custom_message     = '';
			$custom_forward_url = '';
		}

		// Use Global Defaults if Custom Message or URL is Empty.
		if ( 'custom_message' === $enforcement_action && empty( $custom_message ) ) {
			$custom_message = get_option(
				'restrictly_default_message',
				__( 'You do not have permission to view this content.', 'restrictly-access-control' )
			);
		}

		// Use Global Defaults if Custom URL is Empty.
		if ( 'custom_url' === $enforcement_action && empty( $custom_forward_url ) ) {
			$custom_forward_url = get_option( 'restrictly_default_forward_url', '' );
		}

		// Check user permissions.
		$user         = wp_get_current_user();
		$user_roles   = $user->roles;
		$is_logged_in = is_user_logged_in();

		// Enforce login status restrictions.
		if (
			( 'logged_in_users' === $login_status && ! $is_logged_in ) ||
			( 'logged_out_users' === $login_status && $is_logged_in )
		) {
			self::restrictly_handle_enforcement( $enforcement_action, $custom_message, $custom_forward_url );
		}

		// Enforce role-based restrictions.
		if ( ! empty( $allowed_roles ) ) {
			if ( empty( $user_roles ) || empty( array_intersect( $allowed_roles, $user_roles ) ) ) {
				// User is either not logged in or doesn't match allowed roles.
				self::restrictly_handle_enforcement( $enforcement_action, $custom_message, $custom_forward_url );
			}
		}
	}

	/**
	 * Handles enforcement actions: show a message or redirect.
	 *
	 * @param string      $action        Enforcement action (`custom_message` or `custom_url`).
	 * @param string|null $message       Custom message to display (if applicable).
	 * @param string|null $redirect_url  Custom redirect URL (if applicable).
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function restrictly_handle_enforcement( string $action, ?string $message, ?string $redirect_url ): void {
		// Always allow users with admin capabilities full access if enabled.
		if ( (int) get_option( 'restrictly_always_allow_admins', 1 ) === 1 && current_user_can( 'manage_options' ) ) {
			return; // Skip enforcement completely.
		}

		// If the enforcement action is a custom URL but no URL is set, redirect logged-out users to the login page.
		if ( 'custom_url' === $action && empty( $redirect_url ) ) {
			$redirect_url = wp_login_url( (string) get_permalink() );
		}

		// Handle custom URL redirection.
		if ( 'custom_url' === $action && ! empty( $redirect_url ) ) {
			$redirect_url = esc_url_raw( $redirect_url );

			// Prevent infinite redirect loop if already on destination.
			if ( isset( $_SERVER['REQUEST_URI'] ) ) {
				$request_uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );

				// Prevent infinite redirect loop if already on destination.
				if ( home_url( $request_uri ) === $redirect_url ) {
					wp_die(
						esc_html__( 'Redirect Loop Detected: You cannot access this content.', 'restrictly-access-control' ),
						esc_html__( 'Access Denied', 'restrictly-access-control' ),
						array( 'response' => 403 )
					);
				}
			}

			// Redirect to the custom URL.
			wp_safe_redirect( $redirect_url );
			exit;
		}

		// Handle custom message enforcement.
		wp_die(
			! empty( $message ) ? esc_html( $message ) : esc_html__( 'You do not have permission to view this content.', 'restrictly-access-control' ),
			esc_html__( 'Access Denied', 'restrictly-access-control' ),
			array( 'response' => 403 )
		);
	}

	/**
	 * Filters menu items before they are displayed, based on login status and roles.
	 *
	 * @param \WP_Post[] $items Array of WP_Post menu item objects.
	 * @return \WP_Post[] Filtered menu items.
	 *
	 * @since 0.1.0
	 */
	public static function restrictly_filter_menu_items( array $items ): array {
		// Global Administrator Override (Always Allow Administrators).
		$admin_override = get_option( 'restrictly_always_allow_admins', false );
		if ( $admin_override ) {
			$user = wp_get_current_user();

			if ( $user && in_array( 'administrator', (array) $user->roles, true ) ) {
				// Return all menu items unfiltered for admins when override is enabled.
				return $items;
			}
		}

		// Get the current user and their roles.
		$user         = wp_get_current_user();
		$user_roles   = $user->roles;
		$is_logged_in = is_user_logged_in();

		foreach ( $items as $key => $item ) {
			// Check if item should be removed based on restrictions.
			if ( self::should_remove_menu_item( $item, $is_logged_in, $user_roles ) ) {
				unset( $items[ $key ] );
			}
		}

		return array_values( $items );
	}

	/**
	 * Determines if a menu item should be removed based on restrictions.
	 *
	 * @param \WP_Post $item Menu item object.
	 * @param bool     $is_logged_in Whether user is logged in.
	 * @param string[] $user_roles Current user's roles.
	 * @return bool True if item should be removed.
	 *
	 * @since 0.1.0
	 */
	private static function should_remove_menu_item( \WP_Post $item, bool $is_logged_in, array $user_roles ): bool {
		// Get the menu item's visibility and allowed roles.
		$visibility = get_post_meta( $item->ID, 'restrictly_menu_visibility', true );

		$visibility    = ( '' !== $visibility && false !== $visibility ) ? $visibility : 'everyone';
		$allowed_roles = get_post_meta( $item->ID, 'restrictly_menu_roles', true );
		$allowed_roles = is_array( $allowed_roles ) ? $allowed_roles : array();

		// Check menu item's own restrictions.
		if ( 'logged_in_users' === $visibility && ! $is_logged_in ) {
			return true;
		}

		if ( 'logged_out_users' === $visibility && $is_logged_in ) {
			return true;
		}

		if ( ! empty( $allowed_roles ) && ( empty( $user_roles ) || empty( array_intersect( $allowed_roles, $user_roles ) ) ) ) {
			return true;
		}

		// Check if the linked page has restrictions (only if menu item itself is not restricted).
		if ( ! empty( $item->object_id ) && 'everyone' === $visibility && empty( $allowed_roles ) ) {
			$page_login_status  = get_post_meta( $item->object_id, 'restrictly_page_access_by_login_status', true );
			$page_allowed_roles = get_post_meta( $item->object_id, 'restrictly_page_access_by_role', true );

			if ( 'logged_in_users' === $page_login_status && ! $is_logged_in ) {
				return true;
			}

			if ( 'logged_out_users' === $page_login_status && $is_logged_in ) {
				return true;
			}

			if ( is_array( $page_allowed_roles ) && ! empty( $page_allowed_roles ) && empty( array_intersect( $page_allowed_roles, $user_roles ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine whether the current user can access a given post.
	 *
	 * Used by frontend enforcement, REST redaction, and menu filtering.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return bool True if accessible, false otherwise.
	 *
	 * @since 0.1.0
	 */
	public static function can_access( int $post_id ): bool {
		if ( empty( $post_id ) ) {
			return true;
		}

		$login_status  = get_post_meta( $post_id, 'restrictly_page_access_by_login_status', true );
		$allowed_roles = get_post_meta( $post_id, 'restrictly_page_access_by_role', true );

		// Normalize role meta.
		if ( ! is_array( $allowed_roles ) ) {
			if ( is_string( $allowed_roles ) && ! empty( $allowed_roles ) ) {
				$maybe_unserialized = maybe_unserialize( $allowed_roles );
				if ( is_array( $maybe_unserialized ) ) {
					$allowed_roles = $maybe_unserialized;
				} elseif ( strpos( $allowed_roles, ',' ) !== false ) {
					$allowed_roles = array_map( 'trim', explode( ',', $allowed_roles ) );
				} else {
					$allowed_roles = array( trim( $allowed_roles ) );
				}
			} else {
				$allowed_roles = array();
			}
		}

		$user         = wp_get_current_user();
		$user_roles   = $user->roles;
		$is_logged_in = is_user_logged_in();

		// Always allow users with admin capabilities full access if enabled.
		if ( (int) get_option( 'restrictly_always_allow_admins', 1 ) === 1 && current_user_can( 'manage_options' ) ) {
			return true;
		}

		// Role-based restrictions take priority.
		if ( ! empty( $allowed_roles ) ) {
			$allowed_roles = array_map( 'strtolower', $allowed_roles );
			$user_roles    = array_map( 'strtolower', $user_roles );

			if ( empty( $user_roles ) || empty( array_intersect( $allowed_roles, $user_roles ) ) ) {
				return false;
			}

			// Role passes → no need to test login restrictions separately.
			return true;
		}

		// Only check login restrictions if no roles are defined.
		if (
			( 'logged_in_users' === $login_status && ! $is_logged_in ) ||
			( 'logged_out_users' === $login_status && $is_logged_in )
		) {
			return false;
		}

		// Allow public by default.
		return true;
	}

	/**
	 * Enforces Restrictly™ visibility rules during block rendering.
	 *
	 * Evaluates a block's attributes and determines whether the content
	 * should be displayed based on Restrictly™ visibility settings.
	 *
	 * @param string              $block_content The rendered block content.
	 * @param array<string,mixed> $block         The full block data array, including 'blockName' and 'attrs' keys.
	 *
	 * @return string The filtered (or empty) block content.
	 *
	 * @since 0.1.0
	 */
	public static function restrictly_enforce_block_visibility( string $block_content, array $block ): string {
		// Skip visibility enforcement in the admin or editor context.
		if ( is_admin() ) {
			return $block_content;
		}

		// Extract attributes safely.
		$attrs      = $block['attrs'] ?? array();
		$visibility = isset( $attrs['restrictlyVisibility'] ) ? (string) $attrs['restrictlyVisibility'] : 'everyone';
		$roles      = isset( $attrs['restrictlyRoles'] ) && is_array( $attrs['restrictlyRoles'] )
			? $attrs['restrictlyRoles']
			: array();

		// Ask Enforcement if the block should be visible.
		if ( ! self::can_view_by_visibility( $visibility, $roles ) ) {
			return '';
		}

		return $block_content;
	}

	/**
	 * Determines whether the current user can view content based on Restrictly™ visibility settings.
	 *
	 * Provides a unified visibility check for blocks, menus, or FSE components
	 * using simple `$visibility` and `$roles` parameters.
	 *
	 * @param string            $visibility One of: 'everyone', 'logged_in', 'logged_out', 'roles', or 'role_*'.
	 * @param array<int,string> $roles      Optional. Array of role slugs (when restricting by role).
	 *
	 * @return bool True if the user can view the content, false if restricted.
	 *
	 * @since 0.1.0
	 */
	public static function can_view_by_visibility( string $visibility, array $roles = array() ): bool {
		// Always allow administrators if configured to do so.
		if ( (int) get_option( 'restrictly_always_allow_admins', 1 ) === 1 && current_user_can( 'manage_options' ) ) {
			return true;
		}

		$is_logged_in = is_user_logged_in();
		$user_roles   = $is_logged_in ? (array) wp_get_current_user()->roles : array();

		// Normalize for comparisons.
		$roles      = array_map( 'strtolower', $roles );
		$user_roles = array_map( 'strtolower', $user_roles );

		switch ( $visibility ) {
			case '':
			case null:
			case 'everyone':
				// Public content.
				return true;

			case 'logged_in':
				// Must be logged in.
				if ( ! $is_logged_in ) {
					return false;
				}

				// If no roles passed, any logged-in user can see it.
				if ( empty( $roles ) ) {
					return true;
				}

				// If roles passed, user must match at least one.
				return (bool) array_intersect( $roles, $user_roles );

			case 'logged_out':
				return ! $is_logged_in;

			case 'roles':
				// Explicit "roles" mode (kept for BC if anything uses it).
				if ( ! $is_logged_in || empty( $roles ) ) {
					return false;
				}

				return (bool) array_intersect( $roles, $user_roles );

			default:
				// Support old-style "role_editor", "role_subscriber", etc.
				if ( 0 === strpos( $visibility, 'role_' ) ) {
					$role = strtolower( substr( $visibility, 5 ) );

					if ( ! $is_logged_in ) {
						return false;
					}

					return in_array( $role, $user_roles, true );
				}

				// Unknown or invalid visibility key → safest to hide.
				return false;
		}
	}

	/**
	 * Adds a generic message or extra metadata when REST content is redacted.
	 *
	 * @param array<string, mixed> $response The REST response after redaction.
	 * @param \WP_Post             $post     The post being processed. (Unused).
	 * @param array<string, mixed> $request  The REST request. (Unused).
	 *
	 * @return array<string, mixed> Modified response.
	 */
	public static function add_rest_redaction_message( array $response, \WP_Post $post, array $request ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		// Add a "restricted" flag if you want.
		$response['restrictly_restricted'] = true;

		return $response;
	}
}

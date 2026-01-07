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

use WP_Post;

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
	 * Prevents emitting duplicate admin override debug events within a single request.
	 *
	 * @var bool
	 *
	 * @since 0.1.1
	 */
	private static bool $admin_override_emitted = false;

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
	 * Check whether Restrictly debug mode is enabled.
	 *
	 * This is a passive gate used only for emitting debug events.
	 * It MUST NOT affect enforcement behavior.
	 *
	 * @return bool
	 *
	 *  @since 0.1.1
	 */
	private static function is_debug_enabled(): bool {
		/**
		 * Filter: restrictly/debug/is_enabled
		 *
		 * Allows Restrictly Pro to enable debug event emission.
		 *
		 * @param bool $enabled Whether debug mode is enabled.
		 *
		 * @since 0.1.1
		 */
		// phpcs:ignore WordPress.NamingConventions.ValidHookName
		$enabled = apply_filters( 'restrictly/debug/is_enabled', false );

		return (bool) $enabled;
	}

	/**
	 * Emit a debug event for Restrictly observability.
	 *
	 * This method is fully gated and MUST NOT affect enforcement behavior.
	 *
	 * @param string              $event   Event name.
	 * @param array<string,mixed> $context Optional contextual data.
	 *
	 * @return void
	 *
	 * @since 0.1.1
	 */
	private static function emit_debug_event( string $event, array $context = array() ): void {
		if ( ! self::is_debug_enabled() ) {
			return;
		}
		// phpcs:ignore WordPress.NamingConventions.ValidHookName
		do_action( 'restrictly/debug/event', $event, $context );
	}

	/**
	 * Build enforcement context for the current request.
	 *
	 * This context is read-only and MUST NOT affect enforcement behavior.
	 * It exists solely to expose structured request data to observers
	 * and Pro modules via emitters.
	 *
	 * @param int $post_id Post ID being evaluated.
	 *
	 * @return array<string,mixed>
	 *
	 * @since 0.1.1
	 */
	private static function build_enforcement_context( int $post_id ): array {

		return array(
			'post_id'      => $post_id,
			'post_type'    => get_post_type( $post_id ),
			'is_logged_in' => is_user_logged_in(),
			'user_id'      => get_current_user_id(),
			'roles'        => is_user_logged_in() ? wp_get_current_user()->roles : array(),
			'is_admin'     => is_admin(),
			'is_ajax'      => wp_doing_ajax(),
			'is_rest'      => defined( 'REST_REQUEST' ) && REST_REQUEST,
			'request_uri'  => isset( $_SERVER['REQUEST_URI'] )
				? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) )
				: '',
		);
	}

	/**
	 * Determine whether any enforcement signal denies access.
	 *
	 * Signals are deny-only. A single failed signal results in denial.
	 *
	 * @param array<string,mixed> $signals Enforcement signals.
	 *
	 * @return bool True if denied by signals, false otherwise.
	 *
	 * @since 0.1.1
	 */
	private static function signals_deny_access( array $signals ): bool {

		foreach ( $signals as $signal ) {
			if (
				is_array( $signal )
				&& array_key_exists( 'passed', $signal )
				&& false === $signal['passed']
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Emit denial events and execute enforcement.
	 *
	 * Fires internal hooks, emits debug information, and executes the
	 * configured enforcement action when access is denied.
	 *
	 * @param array<string,mixed> $context       Request and content context data.
	 * @param array<string,mixed> $signals       Evaluated restriction signals.
	 * @param string              $action        Enforcement action to execute.
	 * @param string|null         $message       Optional denial message.
	 * @param string|null         $redirect_url  Optional redirect destination.
	 * @param string              $reason        Internal denial reason identifier.
	 *
	 * @return void
	 *
	 * @since 0.1.1
	 */
	private static function emit_enforcement_denial(
		array $context,
		array $signals,
		string $action,
		?string $message,
		?string $redirect_url,
		string $reason = 'rule_denied'
	): void {

		do_action(
            // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			'restrictly/enforcement/decision',
			false,
			$context,
			$signals
		);

		$deny_payload = array();

		$deny_payload = apply_filters(
            // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			'restrictly/enforcement/deny_payload',
			$deny_payload,
			$context,
			$signals
		);

		do_action(
            // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			'restrictly/enforcement/denied',
			$context,
			$signals,
			$deny_payload
		);

		self::emit_debug_event(
			'final_decision',
			array(
				'decision' => 'deny',
				'reason'   => $reason,
				'post_id'  => $context['post_id'] ?? null,
			)
		);

		self::restrictly_handle_enforcement(
			$action,
			$message,
			$redirect_url
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
		if ( ! is_singular() || ! isset( $post->ID ) || 0 === (int) $post->ID ) {
			return;
		}

		$post_id = (int) $post->ID;

		// ---------------------------------------------------------------------
		// Build + emit enforcement context (read-only)
		// ---------------------------------------------------------------------
		$context = self::build_enforcement_context( $post_id );

		$context = apply_filters(
            // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			'restrictly/enforcement/context',
			$context
		);

		// Emit debug event.
		self::emit_debug_event(
			'page_enforcement_checked',
			array(
				'post_id'      => get_queried_object_id(),
				'is_logged_in' => is_user_logged_in(),
			)
		);

		// Get the restriction settings for this content.
		$login_status       = get_post_meta( $post_id, 'restrictly_page_access_by_login_status', true );
		$allowed_roles      = get_post_meta( $post_id, 'restrictly_page_access_by_role', true );
		$enforcement_action = get_post_meta( $post_id, 'restrictly_enforcement_action', true );
		$custom_message     = get_post_meta( $post_id, 'restrictly_custom_message', true );
		$custom_forward_url = get_post_meta( $post_id, 'restrictly_custom_forward_url', true );

		// Emit debug event.
		self::emit_debug_event(
			'evaluation_start',
			array(
				'context'            => 'page_access',
				'post_id'            => get_queried_object_id(),
				'is_logged_in'       => is_user_logged_in(),
				'enforcement_action' => $enforcement_action ?? null,
			)
		);

		// Ensure roles are an array.
		$allowed_roles = is_array( $allowed_roles ) ? $allowed_roles : array();

		// Fallback to Global Defaults When "Use Default" is Selected.
		if ( empty( $enforcement_action ) || 'default' === $enforcement_action ) {
			$enforcement_action = get_option( 'restrictly_default_action', 'custom_message' );
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

		// ---------------------------------------------------------------------
		// Collect enforcement signals (passive)
		// ---------------------------------------------------------------------
		$signals = array();

		$signals = apply_filters(
            // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			'restrictly/enforcement/signals',
			$signals,
			$context
		);

		// ---------------------------------------------------------------------
		// Enforce login status restrictions.
		// ---------------------------------------------------------------------
		if (
			( 'logged_in_users' === $login_status && ! $is_logged_in ) ||
			( 'logged_out_users' === $login_status && $is_logged_in )
		) {

			self::emit_enforcement_denial(
				$context,
				$signals,
				$enforcement_action,
				$custom_message,
				$custom_forward_url
			);
			return;
		}

		// ---------------------------------------------------------------------
		// Enforce role-based restrictions.
		// ---------------------------------------------------------------------
		if ( ! empty( $allowed_roles ) ) {
			if ( empty( $user_roles ) || empty( array_intersect( $allowed_roles, $user_roles ) ) ) {

				self::emit_enforcement_denial(
					$context,
					$signals,
					$enforcement_action,
					$custom_message,
					$custom_forward_url
				);
				return;
			}
		}

		// ---------------------------------------------------------------------
		// APPLY SIGNALS (DENY-ONLY)
		// ---------------------------------------------------------------------
		if ( self::signals_deny_access( $signals ) ) {

			self::emit_enforcement_denial(
				$context,
				$signals,
				$enforcement_action,
				$custom_message,
				$custom_forward_url,
				'signal_denied'
			);
			return;
		}

		// ---------------------------------------------------------------------
		// ALLOW
		// ---------------------------------------------------------------------
		do_action(
            // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			'restrictly/enforcement/decision',
			true,
			$context,
			$signals
		);

		self::emit_debug_event(
			'evaluation_end',
			array(
				'decision' => 'allow',
				'post_id'  => get_queried_object_id(),
			)
		);
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
			// Emit debug event.
			self::emit_debug_event(
				'admin_override_applied',
				array(
					'context'  => 'page_enforcement',
					'decision' => 'allow',
					'reason'   => 'always_allow_admins',
				)
			);
			return;
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
	 * @param WP_Post[] $items Array of WP_Post menu item objects.
	 * @return WP_Post[] Filtered menu items.
	 *
	 * @since 0.1.0
	 */
	public static function restrictly_filter_menu_items( array $items ): array {
		// Global Administrator Override (Always Allow Administrators).
		$admin_override = get_option( 'restrictly_always_allow_admins', false );
		if ( $admin_override ) {
			// Emit debug event.
			self::emit_debug_event(
				'admin_override_applied',
				array(
					'context'  => 'menu_visibility',
					'decision' => 'allow',
					'reason'   => 'always_allow_admins',
				)
			);

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
	 * @param WP_Post  $item Menu item object.
	 * @param bool     $is_logged_in Whether user is logged in.
	 * @param string[] $user_roles Current user's roles.
	 * @return bool True if item should be removed.
	 *
	 * @since 0.1.0
	 */
	private static function should_remove_menu_item( WP_Post $item, bool $is_logged_in, array $user_roles ): bool {
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

		// ---------------------------------------------------------------------
		// Apply enforcement signals (deny-only)
		// ---------------------------------------------------------------------
		$context = array(
			'post_id'      => $post_id,
			'is_logged_in' => $is_logged_in,
			'user_roles'   => $user_roles,
		);

		$context = apply_filters(
            // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			'restrictly/enforcement/context',
			$context
		);

		$signals = array();

		$signals = apply_filters(
            // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			'restrictly/enforcement/signals',
			$signals,
			$context
		);

		if ( self::signals_deny_access( $signals ) ) {
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
			// Emit debug event.
			self::emit_debug_event(
				'fse_visibility_denied',
				array(
					'type'       => 'block',
					'block_name' => $block['blockName'] ?? null,
					'visibility' => $visibility,
					'roles'      => $roles,
					'decision'   => 'deny',
				)
			);
			return '';
		}

		// Emit debug event.
		self::emit_debug_event(
			'fse_visibility_allowed',
			array(
				'type'       => 'block',
				'block_name' => $block['blockName'] ?? null,
				'visibility' => $visibility,
				'roles'      => $roles,
				'decision'   => 'allow',
			)
		);

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
		if (
			(int) get_option( 'restrictly_always_allow_admins', 1 ) === 1
			&& current_user_can( 'manage_options' )
		) {
			if ( ! self::$admin_override_emitted ) {
				// Emit debug event.
				self::emit_debug_event(
					'admin_override_applied',
					array(
						'context'  => 'global_override',
						'decision' => 'allow',
						'reason'   => 'always_allow_admins',
					)
				);

				self::$admin_override_emitted = true;
			}

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
	 * @param WP_Post              $post     The post being processed. (Unused).
	 * @param array<string, mixed> $request  The REST request. (Unused).
	 *
	 * @return array<string, mixed> Modified response.
	 */
	public static function add_rest_redaction_message( array $response, WP_Post $post, array $request ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		// Add a "restricted" flag if you want.
		$response['restrictly_restricted'] = true;

		return $response;
	}
}

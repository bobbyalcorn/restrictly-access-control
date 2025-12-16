<?php
/**
 * Enforces Restrictly™ access rules on REST API responses.
 *
 * Prevents unauthorized users from viewing restricted content
 * by redacting post data before it leaves the REST API.
 *
 * @package Restrictly
 *
 * @since 0.1.0
 */

namespace Restrictly\Core\Rest;

use Restrictly\Core\Common\Enforcement;
use WP_REST_Request;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles REST API content redaction for restricted posts.
 *
 * Hooks into the WordPress REST API and ensures that restricted
 * content is removed or sanitized for unauthorized users.
 *
 * @since 0.1.0
 */
class RestHandler {

	/**
	 * Initializes Restrictly™ REST enforcement hooks.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_rest_filters' ) );

		// Also restrict REST list endpoints at query level.
		add_filter( 'rest_post_query', array( __CLASS__, 'filter_rest_post_query' ), 10, 2 );
	}

	/**
	 * Registers rest_prepare_* filters for all public post types.
	 *
	 * Applies Restrictly™ access checks to REST API responses
	 * across all registered public post types.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function register_rest_filters(): void {
		$post_types = get_post_types( array( 'public' => true ), 'names' );

		foreach ( $post_types as $type ) {
			add_filter( "rest_prepare_{$type}", array( __CLASS__, 'enforce_access' ), 10, 3 );
		}
	}

	/**
	 * Enforces Restrictly™ access rules on REST API responses.
	 *
	 * If a post is restricted, its content and excerpt are redacted
	 * before the REST API returns the data to the client.
	 *
	 * @param \WP_REST_Response $response REST response object.
	 * @param \WP_Post          $post     Post object.
	 * @param \WP_REST_Request  $request  REST request object.
	 *
	 * @return \WP_REST_Response Filtered REST response.
	 *
	 * @since 0.1.0
	 */
	public static function enforce_access( $response, $post, $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! $post instanceof \WP_Post ) {
			return $response;
		}

		// =====================================================
		// 1. Respect “Always Allow Administrators”
		// =====================================================
		$admin_override = get_option( 'restrictly_always_allow_admins', false );
		if ( $admin_override && current_user_can( 'manage_options' ) ) {
			return $response;
		}

		// =====================================================
		// 2. Skip inside Full Site Editor (FSE) for editors
		// =====================================================
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST && current_user_can( 'edit_theme_options' ) ) {
			$referer = isset( $_SERVER['HTTP_REFERER'] )
				? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) )
				: '';

			if ( strpos( $referer, 'site-editor.php' ) !== false ) {
				return $response;
			}
		}

		// =====================================================
		// 3. Apply core Restrictly™ Enforcement
		// =====================================================
		if ( class_exists( Enforcement::class ) && ! Enforcement::can_access( $post->ID ) ) {

			// Redact restricted content for unauthorized users.
			if ( isset( $response->data['content']['rendered'] ) ) {
				$response->data['content']['rendered'] = '';
			}
			if ( isset( $response->data['excerpt']['rendered'] ) ) {
				$response->data['excerpt']['rendered'] = '';
			}

			/**
			 * Allows developers to modify or override the redacted REST response.
			 *
			 * @param \WP_REST_Response       $response The redacted REST response.
			 * @param \WP_Post                $post     The related post object.
			 * @param \WP_REST_Request<mixed> $request  The original REST request.
			 *
			 * @return \WP_REST_Response
			 *
			 * @since 0.1.0
			 */
			$response = apply_filters( 'restrictly_rest_redact_response', $response, $post, $request );
		}

		return $response;
	}

	/**
	 * Filters REST post queries before fetching results.
	 *
	 * Removes restricted posts from REST API list endpoints
	 * for unauthenticated users, while allowing logged-in
	 * users and admins to see full lists (content may still
	 * be redacted by Enforcement::can_access()).
	 *
	 * @phpstan-ignore-next-line
	 *
	 * @param array<string, mixed> $args     WP_Query args used by REST.
	 * @param WP_REST_Request      $request The REST API request object.
	 *
	 * @return array<string, mixed> Filtered query args.
	 *
	 * @since 0.1.0
	 */
	public static function filter_rest_post_query( array $args, WP_REST_Request $request ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed,WordPress.Hooks.PreGetPostsArguments.InvalidArgType
		// =====================================================
		// 1. Admin override (Always Allow Administrators)
		// =====================================================
		if ( (int) get_option( 'restrictly_always_allow_admins', 1 ) === 1 && current_user_can( 'manage_options' ) ) {
			return $args;
		}

		// =====================================================
		// 2. Logged-in users:
		// Let them see full lists; Enforcement::can_access()
		// will determine per-post visibility / redaction.
		// =====================================================
		if ( is_user_logged_in() ) {
			return $args;
		}

		// =====================================================
		// 3. Logged-out users:
		// Only show posts that are:
		// - unrestricted (no meta)
		// - explicitly everyone
		// - explicitly logged_out_users
		// =====================================================

		$meta_query = array();

		if ( isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ) {
			$meta_query = $args['meta_query'];
		}

		$meta_query[] = array(
			'relation' => 'OR',
			array(
				'key'     => 'restrictly_page_access_by_login_status',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => 'restrictly_page_access_by_login_status',
				'value'   => 'everyone',
				'compare' => '=',
			),
			array(
				'key'     => 'restrictly_page_access_by_login_status',
				'value'   => 'logged_out_users',
				'compare' => '=',
			),
		);

		if ( ! isset( $meta_query['relation'] ) ) {
			$meta_query['relation'] = 'AND';
		}

		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$args['meta_query'] = $meta_query;

		return $args;
	}
}

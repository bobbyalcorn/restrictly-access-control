<?php
/**
 * Content type resolution service for Restrictly™.
 *
 * Responsible for determining which WordPress post types
 * are eligible for restriction within the plugin.
 *
 * In the Free version, this service intentionally limits
 * restrictable content to core post types only (post + page).
 * Pro modules may expand this list via provider filters,
 * but only when explicitly permitted.
 *
 * @package Restrictly
 *
 * @since   0.1.1
 */

namespace Restrictly\Core\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Content type resolution service.
 *
 * Centralizes the logic for determining which WordPress post types
 * are eligible for restriction within Restrictly™.
 *
 * This service enforces strict boundaries between Free and Pro:
 * core post types are always supported, while expansion to
 * custom post types is only permitted when explicitly enabled
 * by Pro-level providers.
 *
 * @since 0.1.1
 */
class ContentTypeService {

	/**
	 * Get available content types for Restrictly.
	 *
	 * Default behavior:
	 * - Free: Only 'post' and 'page' are allowed.
	 * - Pro: Additional post types may be injected via providers,
	 *        but only when expansion is explicitly enabled.
	 *
	 * This method is intentionally defensive to prevent unrestricted
	 * custom post type exposure in the Free version.
	 *
	 * @return array<string,string> Associative array of post_type => label.
	 *
	 * @since   0.1.1
	 */
	public static function get_available_content_types(): array {

		/**
		 * Core post types always available in Free.
		 */
		$core_types = array(
			'post',
			'page',
		);

		$available = array();

		foreach ( $core_types as $type ) {
			$obj = get_post_type_object( $type );

			if ( $obj ) {
				$available[ $type ] = $obj->labels->singular_name ?? $type;
			}
		}

		/**
		 * Provider hook.
		 *
		 * Pro modules may register additional content types
		 * through this filter. Expansion is not guaranteed
		 * and is subject to an explicit allow flag.
		 */
		$filtered = apply_filters(
            // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			'restrictly/providers/content_types',
			$available
		);

		/**
		 * Explicit expansion permission flag.
		 *
		 * Pro must opt-in to content type expansion.
		 * Defaults to false in order to enforce Free limitations.
		 */
		$allow_expansion = apply_filters(
            // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			'restrictly/providers/content_types/allow_expansion',
			false
		);

		/**
		 * Free safety net.
		 *
		 * Even if providers attempt to inject additional
		 * post types, only core types are returned unless
		 * expansion has been explicitly enabled.
		 */
		if ( ! $allow_expansion ) {
			return array_intersect_key(
				$filtered,
				array_flip( $core_types )
			);
		}

		return $filtered;
	}

	/**
	 * Sanitize and validate selected content types.
	 *
	 * Ensures submitted values are valid, allowed post types
	 * based on the current Restrictly configuration.
	 *
	 * @param mixed $input Raw input value.
	 *
	 * @return array<int,string> Sanitized list of allowed post types.
	 *
	 * @since   0.1.1
	 */
	public static function sanitize_content_types( mixed $input ): array {

		$allowed = array_keys( self::get_available_content_types() );
		$output  = array();

		foreach ( (array) $input as $type ) {
			$type = sanitize_text_field( $type );

			if ( in_array( $type, $allowed, true ) ) {
				$output[] = $type;
			}
		}

		return $output;
	}
}

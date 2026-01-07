<?php
/**
 * Role resolution service for Restrictly™.
 *
 * Responsible for determining which WordPress user roles
 * are available for use in Restrictly rules.
 *
 * In the Free version, this service intentionally limits
 * role availability to WordPress core roles only.
 * Pro modules may expand the available role set via
 * provider filters when explicitly permitted.
 *
 * @package Restrictly
 *
 * @since   0.1.1
 */

namespace Restrictly\Core\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Role resolution service.
 *
 * Centralizes the logic for determining which WordPress user roles
 * are available for use in Restrictly™ access rules.
 *
 * This service intentionally limits role availability in the
 * Free version to WordPress core roles, while allowing Pro
 * modules to expand the role set through explicit provider
 * opt-in mechanisms.
 *
 * @since 0.1.1
 */
class RoleService {

	/**
	 * Get available roles for Restrictly.
	 *
	 * Default behavior:
	 * - Free: Core WordPress roles only.
	 * - Pro: Additional roles may be injected via providers,
	 *        but only when expansion is explicitly enabled.
	 *
	 * This method is intentionally defensive to prevent
	 * unrestricted custom role exposure in the Free version.
	 *
	 * @return array<string,string> Associative array of role => label.
	 *
	 * @since   0.1.1
	 */
	public static function get_available_roles(): array {

		global $wp_roles;

		/**
		 * Core WordPress roles always available in Free.
		 */
		$core_roles = array(
			'administrator',
			'editor',
			'author',
			'contributor',
			'subscriber',
		);

		$available = array();

		foreach ( $core_roles as $key ) {
			if ( isset( $wp_roles->roles[ $key ] ) ) {
				$available[ $key ] = $wp_roles->roles[ $key ]['name'];
			}
		}

		/**
		 * Provider hook.
		 *
		 * Pro modules may register additional roles through
		 * this filter. Expansion is not guaranteed and is
		 * subject to an explicit allow flag.
		 */
		$filtered = apply_filters(
            // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			'restrictly/providers/roles',
			$available
		);

		/**
		 * Explicit expansion permission flag.
		 *
		 * Pro must opt in to role expansion.
		 * Defaults to false in order to enforce Free limitations.
		 */
		$allow_expansion = apply_filters(
            // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			'restrictly/providers/roles/allow_expansion',
			false
		);

		/**
		 * Free safety net.
		 *
		 * Even if providers attempt to inject additional
		 * roles, only core roles are returned unless
		 * expansion has been explicitly enabled.
		 */
		if ( ! $allow_expansion ) {
			return array_intersect_key(
				$filtered,
				array_flip( $core_roles )
			);
		}

		return $filtered;
	}
}

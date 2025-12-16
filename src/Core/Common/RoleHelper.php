<?php
/**
 * Handles role lookups, normalization, and visibility evaluation for Restrictly™.
 *
 * @package Restrictly
 * @since 0.1.0
 */

namespace Restrictly\Core\Common;

defined( 'ABSPATH' ) || exit;

/**
 * Handles role lookups, normalization, and visibility evaluation for Restrictly™.
 *
 * @since 0.1.0
 */
class RoleHelper {

	/**
	 * Retrieves the list of available WordPress roles.
	 *
	 * In the free version, only core roles are returned.
	 * In Restrictly™ Pro, all registered roles are made available.
	 *
	 * @return array<string,string> Array of available roles as key => name.
	 *
	 * @since 0.1.0
	 */
	public static function get_available_roles(): array {
		global $wp_roles;

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
		 * Allows developers to modify the available role list.
		 *
		 * @param array<string,string> $available Role key => name.
		 *
		 * @since 0.1.0
		 */
		return apply_filters( 'restrictly_available_roles', $available );
	}
}

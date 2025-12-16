<?php
/**
 * Handles Restrictly™ block-level visibility within the WordPress Block Editor.
 *
 * Registers Restrictly's custom visibility attributes for all blocks
 * and ensures block visibility rules (e.g., logged-in, logged-out, or role-based)
 * are respected during frontend rendering.
 *
 * @package Restrictly
 * @since   0.1.0
 */

namespace Restrictly\Core\Admin;

use Restrictly\Core\Common\Enforcement;

defined( 'ABSPATH' ) || exit;

/**
 * Manages Restrictly™ block-level visibility controls in Gutenberg.
 *
 * @since 0.1.0
 */
class BlockVisibility {

	/**
	 * Initializes Restrictly™ block visibility support.
	 *
	 * Hooks into the block registration process and frontend rendering.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_visibility_attribute' ) );
		add_filter( 'render_block', array( __CLASS__, 'apply_visibility_rules' ), 10, 2 );
	}

	/**
	 * Registers Restrictly's custom visibility attributes for all block types.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function register_visibility_attribute(): void {
		add_filter(
			'blocks.registerBlockType',
			function ( $settings ) {
				$settings['attributes'] = $settings['attributes'] ?? array();

				if ( ! isset( $settings['attributes']['restrictlyVisibility'] ) ) {
					$settings['attributes']['restrictlyVisibility'] = array(
						'type'    => 'string',
						'default' => 'everyone',
					);
				}

				if ( ! isset( $settings['attributes']['restrictlyRoles'] ) ) {
					$settings['attributes']['restrictlyRoles'] = array(
						'type'    => 'array',
						'default' => array(),
						'items'   => array( 'type' => 'string' ),
					);
				}

				return $settings;
			},
			10,
			1
		);
	}

	/**
	 * Applies Restrictly™ visibility rules to blocks during frontend rendering.
	 *
	 * Evaluates block attributes and determines whether the current user
	 * should see the block content based on Restrictly visibility settings.
	 *
	 * @param string              $block_content The rendered block content.
	 * @param array<string,mixed> $block         The full block data array, including 'blockName' and 'attrs' keys.
	 *
	 * @return string The filtered (or empty) block content.
	 *
	 * @since 0.1.0
	 */
	public static function apply_visibility_rules( string $block_content, array $block ): string {
		// Always show blocks in admin/editor contexts.
		if ( is_admin() ) {
			return $block_content;
		}

		$attrs      = $block['attrs'] ?? array();
		$visibility = isset( $attrs['restrictlyVisibility'] ) ? (string) $attrs['restrictlyVisibility'] : 'everyone';
		$roles      = $attrs['restrictlyRoles'] ?? array();

		if ( ! is_array( $roles ) ) {
			$roles = (array) $roles;
		}

		// Ask Enforcement class for the visibility decision.
		if ( ! Enforcement::can_view_by_visibility( $visibility, $roles ) ) {
			return '';
		}

		return $block_content;
	}
}

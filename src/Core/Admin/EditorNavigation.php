<?php
/**
 * Adds Restrictly™ visibility controls for Full Site Editing (FSE) Navigation blocks.
 *
 * Handles Restrictly's navigation-level integration within the WordPress Block Editor.
 * Registers visibility logic for Navigation-related blocks and enforces restrictions
 * during both editor preview and frontend rendering.
 *
 * @package Restrictly
 * @since   0.1.0
 */

namespace Restrictly\Core\Admin;

use Restrictly\Core\Common\Enforcement;
use Restrictly\Core\Common\Base;

defined( 'ABSPATH' ) || exit;

/**
 * Handles Restrictly™ visibility integration for Navigation blocks.
 *
 * Provides initialization hooks, script enqueues, and rendering filters
 * that apply Restrictly's visibility rules to Navigation blocks
 * within both the Site Editor and the frontend.
 *
 * @since 0.1.0
 */
class EditorNavigation {

	/**
	 * Initializes Restrictly™ hooks for editor integration and frontend enforcement.
	 *
	 * Registers key WordPress actions and filters that enable Restrictly's
	 * visibility logic within the block editor and during frontend rendering.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function init(): void {
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_assets' ) );
		add_filter( 'render_block', array( __CLASS__, 'filter_rendered_block' ), 10, 2 );
	}

	/**
	 * Enqueues Restrictly™’s Navigation block editor script and localization data.
	 *
	 * Loads the JavaScript responsible for handling visibility controls
	 * inside the Gutenberg editor and passes available role data to it.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function enqueue_editor_assets(): void {
		// Only load in the Site Editor or when editing Navigation posts.
		$screen = get_current_screen();
		if ( ! $screen || ( 'site-editor' !== $screen->base && 'edit-wp_navigation' !== $screen->id ) ) {
			return;
		}

		$handle    = 'restrictly-editor';
		$asset_url = Base::plugin_url() . 'assets/js/editor.js';

		wp_enqueue_script(
			$handle,
			$asset_url,
			array(
				'wp-blocks',
				'wp-hooks',
				'wp-element',
				'wp-i18n',
				'wp-components',
				'wp-edit-post',
				'wp-compose',
				'wp-data',
				'wp-block-editor',
			),
			'0.1.0',
			true
		);

		if ( function_exists( 'wp_roles' ) ) {
			wp_localize_script(
				$handle,
				'RestrictlyBlockData',
				array(
					'roles' => array_map(
						static fn( $key, $label ) => array(
							'value' => $key,
							'label' => $label['name'],
						),
						array_keys( wp_roles()->roles ),
						wp_roles()->roles
					),
				)
			);
		}
	}

	/**
	 * Enforces Restrictly™ visibility rules on navigation-related Gutenberg blocks.
	 *
	 * Filters rendered navigation blocks and removes their output
	 * if the current user does not meet the block’s Restrictly visibility conditions.
	 *
	 * @param string              $content The rendered block HTML content.
	 * @param array<string,mixed> $block   The block data array, including 'blockName' and 'attrs' keys.
	 *
	 * @return string The filtered block content (empty string if hidden by Restrictly rules).
	 *
	 * @since 0.1.0
	 */
	public static function filter_rendered_block( string $content, array $block ): string {
		if ( empty( $block['blockName'] ) ) {
			return $content;
		}

		$supported = array(
			'core/navigation',
			'core/navigation-link',
			'core/navigation-submenu',
			'core/page-list',
			'core/page-list-item',
		);

		if ( ! in_array( $block['blockName'], $supported, true ) ) {
			return $content;
		}

		$attrs      = $block['attrs'] ?? array();
		$visibility = $attrs['restrictlyVisibility'] ?? 'everyone';
		$roles      = $attrs['restrictlyRoles'] ?? array();

		if ( ! Enforcement::can_view_by_visibility( $visibility, $roles ) ) {
			return '';
		}

		return $content;
	}
}

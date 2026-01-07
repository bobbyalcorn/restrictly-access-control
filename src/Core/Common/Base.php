<?php
/**
 * Core class for initializing the Restrictly plugin.
 *
 * @package Restrictly
 * @since 0.1.0
 */

namespace Restrictly\Core\Common;

use Restrictly\Core\Services\RoleService;

defined( 'ABSPATH' ) || exit;

/**
 * Core class for initializing the Restrictly plugin.
 *
 * This class manages the plugin's core functionality, including:
 * - Enqueuing admin styles and scripts.
 * - Adding a settings link to the plugins list.
 * - Ensuring essential hooks are registered.
 *
 * @since 0.1.0
 */
class Base {

	/**
	 * Absolute path to the main plugin file.
	 *
	 * Used to resolve plugin-relative paths and URLs.
	 *
	 * @var string
	 * @since 0.1.0
	 */
	protected static string $plugin_base_path;

	/**
	 * Base URL for the plugin directory.
	 *
	 * Used when enqueuing scripts, styles, and other assets.
	 *
	 * @var string
	 * @since 0.1.0
	 */
	protected static string $plugin_base_url;

	/**
	 * Initializes the core plugin functionality.
	 *
	 * @param string $plugin_file The main plugin file path.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function init( string $plugin_file ): void {

		self::$plugin_base_path = plugin_dir_path( $plugin_file );
		self::$plugin_base_url  = plugin_dir_url( $plugin_file );

		// Enqueue admin styles.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'restrictly_enqueue_admin_styles' ) );

		// Enqueue admin scripts.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'restrictly_enqueue_admin_scripts' ) );

		// Gutenberg visibility panel JS.
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'restrictly_enqueue_block_editor_assets' ) );

		// Ensure Restrictly visibility script also loads in the Site Editor (FSE).
		add_action( 'enqueue_block_assets', array( __CLASS__, 'restrictly_enqueue_block_editor_assets' ) );

		// Add a settings link in the plugins list.
		add_action( 'plugin_action_links_' . plugin_basename( $plugin_file ), array( __CLASS__, 'restrictly_settings_link' ) );
	}

	/**
	 * Retrieve the base URL for the plugin directory.
	 *
	 * @return string The plugin base URL.
	 *
	 * @since 0.1.0
	 */
	public static function plugin_url(): string {
		return self::$plugin_base_url;
	}

	/**
	 * Retrieve the absolute path to the main plugin file.
	 *
	 * @return string The plugin base path.
	 *
	 * @since 0.1.0
	 */
	public static function plugin_path(): string {
		return self::$plugin_base_path;
	}

	/**
	 * Enqueues admin-specific styles.
	 *
	 * @param string $hook The current admin page hook.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function restrictly_enqueue_admin_styles( $hook ): void {

		// Bail immediately if not in admin area.
		if ( ! is_admin() ) {
			return;
		}

		// Only allow users who can manage Restrictly settings.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Reference plugin directories.
		$plugin_base_path = self::$plugin_base_path;
		$plugin_base_url  = self::$plugin_base_url;

		// --- Core Styles -------------------------------------------------------
		$core_path = $plugin_base_path . 'assets/css/admin-core.css';
		$core_ver  = file_exists( $core_path ) ? filemtime( $core_path ) : time();

		wp_enqueue_style(
			'restrictly-admin-core-style',
			$plugin_base_url . 'assets/css/admin-core.css',
			array(),
			(string) $core_ver
		);

		// Inline icon styles.
		$icon_url = esc_url( $plugin_base_url . 'assets/images/restrictly-icon.png' );
		$inline   = "
		.restrictly-icon-16, .restrictly-icon-24 {
			background-image: url('$icon_url');
			background-size: contain;
			background-repeat: no-repeat;
			background-position: center;
		}";
		wp_add_inline_style( 'restrictly-admin-core-style', $inline );

		// --- Settings Page Styles ---------------------------------------------
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'settings_page_restrictly-settings' === $screen->id ) {
			$settings_path = $plugin_base_path . 'assets/css/admin-settings.css';
			$settings_ver  = file_exists( $settings_path ) ? filemtime( $settings_path ) : time();

			wp_enqueue_style(
				'restrictly-admin-settings-style',
				$plugin_base_url . 'assets/css/admin-settings.css',
				array(),
				(string) $settings_ver
			);
		}

		// --- Menus Page Styles ------------------------------------------------
		if ( ( ! empty( get_registered_nav_menus() ) || ! empty( wp_get_nav_menus() ) )
			&& 'nav-menus.php' === $hook
		) {
			$menus_path = $plugin_base_path . 'assets/css/admin-menus.css';
			$menus_ver  = file_exists( $menus_path ) ? filemtime( $menus_path ) : time();

			wp_enqueue_style(
				'restrictly-admin-menus-style',
				$plugin_base_url . 'assets/css/admin-menus.css',
				array(),
				(string) $menus_ver
			);
		}

		// --- Standard Edit Screen --------------------------------------------
		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			$standard_path = $plugin_base_path . 'assets/css/admin-standard-edit-content-type.css';
			$standard_ver  = file_exists( $standard_path ) ? filemtime( $standard_path ) : time();

			wp_enqueue_style(
				'restrictly-admin-standard-edit-content-type-style',
				$plugin_base_url . 'assets/css/admin-standard-edit-content-type.css',
				array(),
				(string) $standard_ver
			);
		}

		// --- Quick & Bulk Edit ------------------------------------------------
		if ( 'edit.php' === $hook ) {

			$quick_path = $plugin_base_path . 'assets/css/admin-quick-edit-content-type.css';
			$quick_ver  = file_exists( $quick_path ) ? filemtime( $quick_path ) : time();

			wp_enqueue_style(
				'restrictly-admin-quick-edit-content-type-style',
				$plugin_base_url . 'assets/css/admin-quick-edit-content-type.css',
				array(),
				(string) $quick_ver
			);

			$bulk_path = $plugin_base_path . 'assets/css/admin-bulk-edit-content-type.css';
			$bulk_ver  = file_exists( $bulk_path ) ? filemtime( $bulk_path ) : time();

			wp_enqueue_style(
				'restrictly-admin-bulk-edit-content-type-style',
				$plugin_base_url . 'assets/css/admin-bulk-edit-content-type.css',
				array(),
				(string) $bulk_ver
			);
		}
	}

	/**
	 * Enqueues admin-specific scripts.
	 *
	 * @param string $hook The current admin page hook.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function restrictly_enqueue_admin_scripts( $hook ): void {
		// Only allow users with permission to manage settings.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Reference the main plugin directory.
		$plugin_base_path = self::$plugin_base_path;
		$plugin_base_url  = self::$plugin_base_url;

		// Only enqueue the settings page styles on the settings page.
		$screen = get_current_screen();
		if ( $screen && 'settings_page_restrictly-settings' === $screen->id ) {
			// Define file path and check if it exists.
			$file_path = $plugin_base_path . 'assets/js/settings.js';
			$version   = file_exists( $file_path ) ? filemtime( $file_path ) : time();

			// Enqueue the admin settings script.
			wp_enqueue_script(
				'restrictly-settings-script',
				$plugin_base_url . 'assets/js/settings.js',
				array( 'jquery' ),
				(string) $version,
				true
			);
		}

		// Enqueue standard edit script only on the post editing screen.
		if ( 'post.php' === $hook || 'post-new.php' === $hook ) {
			// Correct file path and versioning.
			$file_path = $plugin_base_path . 'assets/js/standard-edit-content-type.js';
			$version   = file_exists( $file_path ) ? filemtime( $file_path ) : time();

			// Enqueue the standard edit script.
			wp_enqueue_script(
				'restrictly-standard-edit-content-type-script',
				$plugin_base_url . 'assets/js/standard-edit-content-type.js',
				array( 'jquery' ),
				(string) $version,
				true
			);
		}

		// Enqueue quick & bulk edit scripts on the posts listing page.
		if ( 'edit.php' === $hook ) {
			// Correct file path and versioning.
			$file_path = $plugin_base_path . 'assets/js/quick-edit-content-type.js';
			$version   = file_exists( $file_path ) ? filemtime( $file_path ) : time();

			// Enqueue the quick edit scripts.
			wp_enqueue_script(
				'restrictly-quick-edit-content-type-script',
				$plugin_base_url . 'assets/js/quick-edit-content-type.js',
				array( 'jquery' ),
				(string) $version,
				true
			);

			// Correct file path and versioning.
			$file_path = $plugin_base_path . 'assets/js/bulk-edit-content-type.js';
			$version   = file_exists( $file_path ) ? filemtime( $file_path ) : time();

			// Enqueue the bulk edit scripts.
			wp_enqueue_script(
				'restrictly-bulk-edit-content-type-script',
				$plugin_base_url . 'assets/js/bulk-edit-content-type.js',
				array( 'jquery' ),
				(string) $version,
				true
			);

			// Ensure localized script is properly enqueued.
			wp_localize_script(
				'restrictly-bulk-edit-content-type-script',
				'restrictlyAdmin',
				array(
					'bulkEditNonce' => wp_create_nonce( 'restrictly_bulk_edit_nonce' ),
					'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				)
			);
		}
	}

	/**
	 * Adds a settings link to the plugin entry in the plugins list.
	 *
	 * @param string[] $links Existing plugin action links.
	 *
	 * @return string[] Modified plugin action links with the settings link.
	 *
	 * @since 0.1.0
	 */
	public static function restrictly_settings_link( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url_raw( admin_url( 'options-general.php?page=restrictly-settings' ) ),
			esc_html__( 'Settings', 'restrictly-access-control' )
		);

		$links[] = $settings_link;
		return $links;
	}

	/**
	 * Enqueues Restrictly™ block editor visibility controls.
	 *
	 * Loads the block-visibility.js script for Gutenberg (FSE) editor and
	 * passes available user roles to JavaScript for role-based visibility filtering.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	/**
	 * Enqueues Restrictly™ block editor visibility controls.
	 *
	 * Loads visibility logic for both Content Blocks and Navigation blocks in
	 * Gutenberg (FSE). Passes available user roles to JavaScript for role-based
	 * visibility filtering.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function restrictly_enqueue_block_editor_assets(): void {
		$plugin_base_path = self::$plugin_base_path;
		$plugin_base_url  = self::$plugin_base_url;

		// ---------------------------------------------------------------------
		// Core Restrictly™ Block Visibility (Content Blocks)
		// ---------------------------------------------------------------------
		$block_js_path = $plugin_base_path . 'assets/js/block-visibility.js';
		$block_js_ver  = file_exists( $block_js_path ) ? filemtime( $block_js_path ) : time();

		wp_enqueue_script(
			'restrictly-block-visibility',
			$plugin_base_url . 'assets/js/block-visibility.js',
			array( 'wp-blocks', 'wp-dom-ready', 'wp-edit-post', 'wp-components', 'wp-element' ),
			(string) $block_js_ver,
			true
		);

		// Styles for block badges / inspector UI.
		$block_css_path = $plugin_base_path . 'assets/css/admin-block-visibility.css';
		$block_css_ver  = file_exists( $block_css_path ) ? filemtime( $block_css_path ) : time();

		wp_enqueue_style(
			'restrictly-block-visibility',
			$plugin_base_url . 'assets/css/admin-block-visibility.css',
			array( 'wp-edit-blocks' ),
			(string) $block_css_ver
		);

		// ---------------------------------------------------------------------
		// Restrictly™ FSE Navigation Visibility (Navigation, Submenus, etc.)
		// ---------------------------------------------------------------------
		$nav_js_path = $plugin_base_path . 'assets/js/editor.js';
		$nav_js_ver  = file_exists( $nav_js_path ) ? filemtime( $nav_js_path ) : time();

		wp_enqueue_script(
			'restrictly-editor',
			$plugin_base_url . 'assets/js/editor.js',
			array( 'wp-blocks', 'wp-hooks', 'wp-element', 'wp-i18n', 'wp-components', 'wp-edit-post', 'wp-compose', 'wp-data', 'wp-block-editor' ),
			(string) $nav_js_ver,
			true
		);

		// ---------------------------------------------------------------------
		// Provide available roles to both systems (block + navigation)
		// ---------------------------------------------------------------------
		if ( class_exists( RoleService::class ) ) {
			$available_roles = RoleService::get_available_roles();
		} else {
			$wp_roles        = wp_roles();
			$available_roles = is_object( $wp_roles ) ? $wp_roles->get_names() : array();
		}

		$role_options = array();
		foreach ( $available_roles as $role_key => $role_name ) {
			$role_options[] = array(
				'label' => translate_user_role( $role_name ),
				'value' => sanitize_key( $role_key ),
			);
		}

		// Localize roles for both scripts.
		wp_localize_script(
			'restrictly-block-visibility',
			'RestrictlyBlockData',
			array( 'roles' => $role_options )
		);

		wp_localize_script(
			'restrictly-editor',
			'restrictlyNavigationData',
			array( 'roles' => array_values( wp_list_pluck( $role_options, 'value' ) ) )
		);

		wp_localize_script(
			'restrictly-block-visibility',
			'RestrictlySettings',
			array(
				'showNavPills' => (bool) get_option( 'restrictly_show_nav_pills', false ),
			)
		);
	}
}

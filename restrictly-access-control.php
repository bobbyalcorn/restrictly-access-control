<?php
/**
 * Plugin Name: Restrictly - Access Control
 * Plugin URI:  https://restrictlypro.com
 * Description: Lightweight, performance-focused access control for WordPress.
 * Version:     0.1.0
 * Requires at least: 5.2
 * Tested up to:      6.9
 * Requires PHP:     7.4
 * Author:           Bobby Alcorn
 * Author URI:       https://github.com/bobbyalcorn
 * License:          GPL-2.0+
 * License URI:      https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:      restrictly-access-control
 * Domain Path:      /languages
 *
 * @package Restrictly
 * @since   0.1.0
 */

defined( 'ABSPATH' ) || exit;

// Load Composer autoloader in dev, fallback to plugin autoloader in production.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	require_once __DIR__ . '/src/autoload.php';
}

/*
|--------------------------------------------------------------------------
| Use statements (autoloaded classes).
|--------------------------------------------------------------------------
*/

// Core.
use Restrictly\Core\Common\Base;
use Restrictly\Core\Common\Enforcement;
use Restrictly\Core\Common\QueryFilter;
use Restrictly\Core\Common\FSEHandler;

// REST.
use Restrictly\Core\Rest\RestHandler;

// Admin.
use Restrictly\Core\Admin\Settings;
use Restrictly\Core\Admin\Menus;
use Restrictly\Core\Admin\FSENavigation;
use Restrictly\Core\Admin\ContentTypeBase;
use Restrictly\Core\Admin\StandardEditContentType;
use Restrictly\Core\Admin\QuickEditContentType;
use Restrictly\Core\Admin\BulkEditContentType;
use Restrictly\Core\Admin\BlockVisibility;
use Restrictly\Core\Admin\EditorNavigation;

/**
 * Initialize Restrictly – Access Control.
 *
 * @return void
 *
 * @since 0.1.0
 */
function restrictly_access_control_init(): void {
	/*
	|--------------------------------------------------------------------------
	| Translations.
	|--------------------------------------------------------------------------
	*/
	if ( ! is_textdomain_loaded( 'restrictly-access-control' ) ) {
		load_textdomain(
			'restrictly-access-control',
			trailingslashit( plugin_dir_path( __FILE__ ) ) . 'languages/restrictly-' . determine_locale() . '.mo'
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Core services (runs everywhere).
	|--------------------------------------------------------------------------
	*/
	Enforcement::init();
	QueryFilter::init();
	FSEHandler::init();
	RestHandler::init();

	/*
	|--------------------------------------------------------------------------
	| Admin-only services.
	|--------------------------------------------------------------------------
	*/
	if ( is_admin() ) {

		// Base admin setup (scripts, links, and shared utilities).
		Base::init( __FILE__ );

		// Admin menus and navigation.
		Menus::init();
		FSENavigation::init();

		// Settings pages.
		Settings::init();

		// Content type editors.
		ContentTypeBase::init();
		StandardEditContentType::init();
		QuickEditContentType::init();
		BulkEditContentType::init();

		// Block and editor integrations.
		BlockVisibility::init();
		EditorNavigation::init();
	}
}

add_action( 'plugins_loaded', 'restrictly_access_control_init' );

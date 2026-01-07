<?php
/**
 * Handles the settings page and configuration for the Restrictly plugin manually.
 *
 * @package Restrictly
 *
 * @since 0.1.0
 */

namespace Restrictly\Core\Admin;

use Restrictly\Core\Services\ContentTypeService;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A class to handle the settings page and configuration for the Restrictly plugin.
 * This class is responsible for adding the settings page to the admin menu, rendering
 * the settings page, and saving the settings.
 *
 * @since 0.1.0
 */
class Settings {

	// Settings constants.
	private const PAGE_SLUG             = 'restrictly-settings';
	private const DEFAULT_CONTENT_TYPES = array();
	private const DEFAULT_ACTION        = 'custom_message';
	private const DEFAULT_MESSAGE       = 'You do not have permission to view this content.';
	private const DEFAULT_URL           = '';
	private const DEFAULT_MENU_FLAGS    = 1;

	/**
	 * Initializes the settings page by adding it to the admin menu.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function init(): void {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
	}

	/**
	 * Adds the settings page under the WordPress "Settings" menu.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function add_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		add_options_page(
			__( 'Restrictly Settings', 'restrictly-access-control' ),
			__( 'Restrictly', 'restrictly-access-control' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Renders the settings page and handles form submission.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function render_settings_page(): void {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'restrictly-access-control' ) );
		}

		// Handle form submission.
		if ( isset( $_POST['restrictly_save_settings'] ) ) {
			check_admin_referer( 'restrictly_settings_nonce' );
			self::save_restrictly_settings();
		}

		// Get current settings.
		$current_content_types     = (array) get_option( 'restrictly_content_types', self::DEFAULT_CONTENT_TYPES );
		$current_action            = get_option( 'restrictly_default_action', self::DEFAULT_ACTION );
		$current_message           = get_option( 'restrictly_default_message', self::DEFAULT_MESSAGE );
		$current_forward_url       = get_option( 'restrictly_default_forward_url', self::DEFAULT_URL );
		$current_enable_menu_flags = (int) get_option( 'restrictly_enable_menu_flags', self::DEFAULT_MENU_FLAGS );

		// Get available content types.
		$available_content_types = ContentTypeService::get_available_content_types();

		// Detect if using a Full Site Editing (block) theme.
		$is_block_theme = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();

		// Classic nav menu data.
		$wp_nav_menus         = wp_get_nav_menus();
		$registered_nav_menus = get_registered_nav_menus();

		// Determine if the Menu Restrictions tab should be disabled.
		$menu_disabled = ( empty( $wp_nav_menus ) && empty( $registered_nav_menus ) ) || $is_block_theme;
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">
				<span class="restrictly-icon-24"></span>
				<?php echo esc_html__( 'Restrictly Settings', 'restrictly-access-control' ); ?>
			</h1>
			<hr class="wp-header-end">

			<!-- Data cleanup info -->
			<div class="notice notice-info restrictly-cleanup-notice">
				<p>
					<strong><?php esc_html_e( 'Automatic Cleanup:', 'restrictly-access-control' ); ?></strong>
					<?php esc_html_e( 'When you uninstall Restrictly™, all saved options, visibility settings, and post meta are fully removed for a clean uninstall.', 'restrictly-access-control' ); ?>
				</p>
			</div>

			<div class="restrictly-tabs">

				<nav class="nav-tab-wrapper">
					<a href="#access-control" class="nav-tab nav-tab-active"><?php esc_html_e( 'Access Control', 'restrictly-access-control' ); ?></a>
					<a href="#menu-restrictions"
						class="nav-tab <?php echo $menu_disabled ? 'disabled restrictly-disabled-tab' : ''; ?>"
							<?php echo $menu_disabled ? 'aria-disabled="true" onclick="return false;"' : ''; ?>>
						<?php esc_html_e( 'Menu Restrictions', 'restrictly-access-control' ); ?>
					</a>
				</nav>

				<form method="post">

					<!-- Access Control Tab -->
					<div id="access-control" class="restrictly-tab-content active">
						<div class="postbox restrictly-settings-box">
							<div class="inside restrictly-m-t-0 restrictly-m-b-0">
								<?php wp_nonce_field( 'restrictly_settings_nonce' ); ?>

								<h2><?php esc_html_e( 'Access Control Settings', 'restrictly-access-control' ); ?></h2>

								<!-- Content Types -->
								<h3 class="restrictly-subsection-heading"><?php esc_html_e( 'Content Types', 'restrictly-access-control' ); ?></h3>
								<p class="restrictly-subsection-description"><?php esc_html_e( 'Select the content types that you want to restrict access to.', 'restrictly-access-control' ); ?></p>

								<div class="restrictly-checkbox-group restrictly-m-b-20">
									<?php foreach ( $available_content_types as $type => $label ) : ?>
										<label class="restrictly-label">
											<input type="checkbox" class="restrictly-checkbox" name="restrictly_content_types[]" value="<?php echo esc_attr( $type ); ?>"
												<?php checked( in_array( $type, $current_content_types, true ) ); ?>>
											<?php echo esc_html( $label ); ?>
										</label>
									<?php endforeach; ?>
								</div>

								<hr>

								<!-- Default Enforcement Action -->
								<h3 class="restrictly-subsection-heading"><?php esc_html_e( 'Default Content Enforcement Action', 'restrictly-access-control' ); ?></h3>
								<p class="restrictly-subsection-description">
									<?php esc_html_e( 'This determines what happens when a user tries to access a restricted post, page, or other content type. It does not affect menu or navigation visibility.', 'restrictly-access-control' ); ?>
								</p>

								<table class="form-table" role="presentation">
									<tbody>
									<tr>
										<td class="restrictly-p-t-0">
											<select name="restrictly_default_action" id="restrictly_default_action" class="restrictly-select">
												<option value="custom_message" <?php selected( $current_action, 'custom_message' ); ?>>
													<?php esc_html_e( 'Show a Message', 'restrictly-access-control' ); ?>
												</option>
												<option value="custom_url" <?php selected( $current_action, 'custom_url' ); ?>>
													<?php esc_html_e( 'Forward to a URL', 'restrictly-access-control' ); ?>
												</option>
											</select>
										</td>
									</tr>

									<tr>
										<td>
											<label for="restrictly_default_message"><strong><?php esc_html_e( 'Default Message', 'restrictly-access-control' ); ?></strong></label><br />
											<textarea name="restrictly_default_message" id="restrictly_default_message" rows="3" class="restrictly-textarea"><?php echo esc_textarea( $current_message ); ?></textarea>
										</td>
									</tr>

									<tr>
										<td>
											<label for="restrictly_default_forward_url"><strong><?php esc_html_e( 'Forward URL', 'restrictly-access-control' ); ?></strong></label><br />
											<input type="text" name="restrictly_default_forward_url" id="restrictly_default_forward_url" value="<?php echo esc_url( $current_forward_url ); ?>" class="restrictly-input" />
										</td>
									</tr>
									</tbody>
								</table>

								<hr>

								<!-- Always Allow Admins -->
								<h3 class="restrictly-subsection-heading"><?php esc_html_e( 'Always Allow Administrators', 'restrictly-access-control' ); ?></h3>
								<p class="restrictly-subsection-description"><?php esc_html_e( 'When enabled, Administrators can always view and edit all content regardless of Restrictly™ restrictions.', 'restrictly-access-control' ); ?></p>

								<div class="restrictly-checkbox-group restrictly-m-b-20">
									<label class="restrictly-label">
										<input type="checkbox" class="restrictly-checkbox" name="restrictly_always_allow_admins" value="1" <?php checked( (int) get_option( 'restrictly_always_allow_admins', 1 ), 1 ); ?>>
										<?php esc_html_e( 'Always allow Administrators full access', 'restrictly-access-control' ); ?>
									</label>
								</div>

								<!-- Block Editor Visibility -->
								<hr>

								<h3 class="restrictly-subsection-heading">
									<?php esc_html_e( 'Block Editor Visibility', 'restrictly-access-control' ); ?>
								</h3>
								<p class="restrictly-subsection-description">
									<?php esc_html_e( 'Control whether Restrictly™ visibility indicators appear inside Full Site Editing (FSE) Navigation menus. This helps keep your workspace clean when editing menus.', 'restrictly-access-control' ); ?>
								</p>

								<div class="restrictly-checkbox-group restrictly-m-b-20">
									<label class="restrictly-label">
										<input type="checkbox"
												class="restrictly-checkbox"
												name="restrictly_show_nav_pills"
												value="1"
												<?php checked( (int) get_option( 'restrictly_show_nav_pills', 0 ), 1 ); ?>>
										<?php esc_html_e( 'Show visibility indicators in Navigation menus', 'restrictly-access-control' ); ?>
									</label>
								</div>
							</div>
						</div>
					</div>

					<!-- Menu Restrictions Tab -->
					<div id="menu-restrictions" class="restrictly-tab-content">
						<div class="postbox restrictly-settings-box">
							<div class="inside restrictly-m-t-0 restrictly-m-b-0">
								<h2><?php esc_html_e( 'Menu Restrictions', 'restrictly-access-control' ); ?></h2>

								<?php if ( $is_block_theme ) : ?>
									<div class="notice notice-info restrictly-fse-info">
										<p><strong><?php esc_html_e( 'Heads up!', 'restrictly-access-control' ); ?></strong>
											<?php esc_html_e( 'You are using a Full Site Editing (block-based) theme. Restrictly™ will manage Navigation block visibility directly inside the Site Editor instead of traditional menus.', 'restrictly-access-control' ); ?>
										</p>
									</div>
								<?php else : ?>
									<!-- Menu Highlighting -->
									<div class="restrictly-section">
										<h3 class="restrictly-subsection-heading"><?php esc_html_e( 'Mismatch Highlighting', 'restrictly-access-control' ); ?></h3>
										<div class="restrictly-option">
											<label class="restrictly-label">
												<input type="checkbox" class="restrictly-checkbox" name="restrictly_enable_menu_flags" value="1" <?php checked( 1, ( empty( $registered_nav_menus ) ? 0 : $current_enable_menu_flags ) ); ?> <?php echo ( empty( $registered_nav_menus ) ) ? 'disabled' : ''; ?>>
												<?php esc_html_e( 'Visually highlight menu items that may have permission inconsistencies.', 'restrictly-access-control' ); ?>
											</label>
										</div>
									</div>
								<?php endif; ?>
							</div>
						</div>
					</div>

					<?php
					submit_button(
						__( 'Save Settings', 'restrictly-access-control' ),
						'primary',
						'restrictly_save_settings'
					);
					?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Sanitizes the Restrictly™ enforcement action value.
	 *
	 * Ensures the selected enforcement action is one of the allowed options.
	 *
	 * @param string $action The enforcement action value to sanitize.
	 *
	 * @return string The sanitized enforcement action (defaults to 'custom_message' if invalid).
	 *
	 * @since 0.1.0
	 */
	public static function sanitize_restrictly_enforcement_action( string $action ): string {
		$allowed = array( 'custom_message', 'custom_url' );
		return in_array( $action, $allowed, true ) ? $action : 'custom_message';
	}

	/**
	 * Sanitizes the Restrictly™ forward URL value.
	 *
	 * Trims whitespace and validates either a relative path (e.g. /example/page)
	 * or a properly formatted absolute URL.
	 *
	 * @param string $url The forward URL to sanitize.
	 *
	 * @return string The sanitized and validated URL, or an empty string if invalid.
	 *
	 * @since 0.1.0
	 */
	public static function sanitize_restrictly_forward_url( string $url ): string {
		$url = trim( $url );

		if ( empty( $url ) || preg_match( '/^\/[a-zA-Z0-9\-._~\/]*$/', $url ) ) {
			return $url;
		}

		return filter_var( $url, FILTER_VALIDATE_URL ) ? esc_url_raw( $url ) : '';
	}

	/** Save settings */
	public static function save_restrictly_settings(): void {
		if ( ! isset( $_POST['restrictly_save_settings'] ) ) {
			return;
		}
		check_admin_referer( 'restrictly_settings_nonce' );

		$content_types = isset( $_POST['restrictly_content_types'] )
				? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['restrictly_content_types'] ) )
				: array();
		update_option( 'restrictly_content_types', ContentTypeService::sanitize_content_types( $content_types ) );

		$action = isset( $_POST['restrictly_default_action'] )
				? sanitize_text_field( wp_unslash( $_POST['restrictly_default_action'] ) )
				: self::DEFAULT_ACTION;
		update_option( 'restrictly_default_action', self::sanitize_restrictly_enforcement_action( $action ) );

		$message = isset( $_POST['restrictly_default_message'] )
				? sanitize_textarea_field( wp_unslash( $_POST['restrictly_default_message'] ) )
				: self::DEFAULT_MESSAGE;
		update_option( 'restrictly_default_message', $message );

		$url = isset( $_POST['restrictly_default_forward_url'] )
				? esc_url_raw( wp_unslash( $_POST['restrictly_default_forward_url'] ) )
				: self::DEFAULT_URL;
		update_option( 'restrictly_default_forward_url', self::sanitize_restrictly_forward_url( $url ) );

		update_option( 'restrictly_always_allow_admins', isset( $_POST['restrictly_always_allow_admins'] ) ? 1 : 0 );

		$menu_flags = isset( $_POST['restrictly_enable_menu_flags'] ) ? 1 : 0;
		update_option( 'restrictly_enable_menu_flags', ( ! empty( get_registered_nav_menus() ) ) ? $menu_flags : 0 );

		$show_nav_pills = isset( $_POST['restrictly_show_nav_pills'] ) ? 1 : 0;
		update_option( 'restrictly_show_nav_pills', $show_nav_pills );

		echo '<div class="updated notice is-dismissible"><p>' . esc_html__( 'Restrictly settings have been saved.', 'restrictly-access-control' ) . '</p></div>';
	}
}

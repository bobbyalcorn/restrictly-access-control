<?php
/**
 * Handles menu restriction functionality for the Restrictly plugin.
 *
 * This file contains the Menus class, which manages visibility and access
 * control for WordPress navigation menu items based on user roles and login status.
 *
 * @package Restrictly
 * @since   0.1.0
 */

namespace Restrictly\Core\Admin;

use Restrictly\Core\Common\Base;
use Restrictly\Core\Services\RoleService;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Restricts menu visibility based on user roles and login status.
 *
 * This class is responsible for:
 * - Adding custom restriction fields to WordPress menu items.
 * - Filtering menu items before they are displayed.
 * - Enforcing visibility settings based on user authentication and roles.
 * - Providing admin notices for menu item restriction mismatches.
 *
 * @since 0.1.0
 */
class Menus {

	/**
	 * The number of mismatched menu items.
	 *
	 * @var int
	 *
	 * @since 0.1.0
	 */
	private static int $mismatch_count = 0;

	/**
	 * Initializes the menu restriction functionality.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function init(): void {
		// Only load in the admin.
		if ( ! is_admin() ) {
			return;
		}

		// Add admin notice if no primary menu is assigned.
		add_action( 'admin_notices', array( __CLASS__, 'restrictly_admin_menu_warning' ) );

		// Check if menus are registered.
		$menu_locations = get_nav_menu_locations();

		// If no menus are registered, return early.
		if ( empty( $menu_locations ) ) {
			return;
		}

		// Hook into admin notices.
		add_action( 'admin_notices', array( __CLASS__, 'restrictly_admin_notice' ) );

		// Add custom fields to menu items in the Appearance > Menus screen.
		add_action( 'wp_nav_menu_item_custom_fields', array( __CLASS__, 'restrictly_add_menu_custom_fields' ), 10, 4 );

		// Save the fields when menu items are saved.
		add_action( 'wp_update_nav_menu_item', array( __CLASS__, 'restrictly_save_menu_fields' ), 10, 2 );

		// Add class to mismatched menu items in Appearance > Menus.
		add_filter( 'wp_setup_nav_menu_item', array( __CLASS__, 'restrictly_mark_mismatched_menu_items' ) );

		// Enqueue admin scripts.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'restrictly_enqueue_admin_scripts' ) );

		// Force menu refresh after saving to update flags immediately.
		add_action( 'wp_update_nav_menu_item', array( __CLASS__, 'restrictly_force_menu_refresh' ), 20, 2 );

		// Enqueue script to repaint menu after save.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_post_save_repaint_script' ) );
	}

	/**
	 * Force fresh menu flag evaluation after saving.
	 *
	 * Clears transient and cache so Restrictly™ will repaint states immediately
	 * on the next admin load — without needing a manual refresh.
	 *
	 * @param int $menu_id         The ID of the navigation menu being updated. (Unused).
	 * @param int $menu_item_db_id The ID of the specific menu item being updated. (Unused).
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function restrictly_force_menu_refresh( int $menu_id, int $menu_item_db_id ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		// Remove transient flag so the next render is clean + fresh.
		delete_transient( 'restrictly_mismatch_flag' );

		// Clear Restrictly object cache.
		wp_cache_flush();
	}

	/**
	 * Force menu UI repaint after saving menu items.
	 *
	 * Ensures Restrictly™ menu colors (side highlights) immediately reflect
	 * the new restriction states after hitting “Save Menu,” without a manual refresh.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function enqueue_post_save_repaint_script(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		// Only load on nav-menus.php.
		$screen = get_current_screen();
		if ( ! $screen || 'nav-menus' !== $screen->id ) {
			return;
		}

		// Verify safe query vars (sanitized before use).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading URL parameters only, not processing form data
		$menu = isset( $_GET['menu'] ) ? sanitize_text_field( wp_unslash( $_GET['menu'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

		// If user just updated the menu, trigger repaint.
		if ( ! empty( $menu ) && 'edit' === $action ) {
			wp_add_inline_script(
				'jquery-core',
				'jQuery(function($){ setTimeout(function(){ location.reload(); }, 100); });'
			);
		}
	}

	/**
	 * Enqueues menu scripts and styles.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function restrictly_enqueue_admin_scripts( string $hook ): void {
		// Only enqueue on the Menus page.
		if ( 'nav-menus.php' !== $hook ) {
			return;
		}

		// Only allow users with permission to manage menus.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Only load scripts if menus are registered.
		if ( empty( get_registered_nav_menus() ) && empty( wp_get_nav_menus() ) ) {
			return;
		}

		// Resolve paths using the plugin base helpers.
		$file_path = Base::plugin_path() . 'assets/js/menu.js';
		$version   = file_exists( $file_path ) ? filemtime( $file_path ) : time();

		wp_enqueue_script(
			'restrictly-menu-script',
			Base::plugin_url() . 'assets/js/menu.js',
			array( 'jquery' ),
			(string) $version,
			true
		);
	}

	/**
	 * Displays admin notices about menus on the classic Menus screen only.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function restrictly_admin_menu_warning(): void {
		// If it's a block (FSE) theme, nav-menus is irrelevant — show nothing here.
		$is_block_theme = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
		if ( $is_block_theme ) {
			return;
		}

		// Classic theme: check menu state.
		$wp_nav_menus         = wp_get_nav_menus();
		$registered_nav_menus = get_registered_nav_menus();
		$theme_supports_menus = current_theme_supports( 'menus' );

		if ( ! $theme_supports_menus ) {
			// Classic theme that truly doesn't support menus.
			echo '<div class="notice notice-error"><p>';
			printf(
				'<strong>%s</strong> %s',
				esc_html__( 'Notice:', 'restrictly-access-control' ),
				esc_html__( 'The current theme does not support menus. Menu restrictions will not be applied.', 'restrictly-access-control' )
			);
			echo '</p></div>';
			return;
		}

		// Theme supports menus; check if any exist / are registered.
		if ( empty( $wp_nav_menus ) ) {
			echo '<div class="notice notice-error"><p>';
			printf(
				'<strong>%s</strong> %s <a href="%s">%s</a>. %s',
				esc_html__( 'Notice:', 'restrictly-access-control' ),
				esc_html__( 'No menus have been detected. To enforce menu restrictions, please assign a menu in', 'restrictly-access-control' ),
				esc_url( admin_url( 'nav-menus.php' ) ),
				esc_html__( 'Appearance > Menus', 'restrictly-access-control' ),
				esc_html__( 'Menu restrictions will not be applied until a menu is created.', 'restrictly-access-control' )
			);
			echo '</p></div>';
		} elseif ( empty( $registered_nav_menus ) ) {
			echo '<div class="notice notice-warning"><p>';
			printf(
				'<strong>%s</strong> %s',
				esc_html__( 'Notice:', 'restrictly-access-control' ),
				esc_html__( 'No registered menus have been detected. Menu restrictions will not be applied until a menu is created.', 'restrictly-access-control' )
			);
			echo '</p></div>';
		}
	}

	/**
	 * Displays an admin notice for menu items with mismatched restrictions.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function restrictly_admin_notice(): void {
		// Check if the transient flag is set (or alternatively, check if the count is greater than zero).
		if ( get_transient( 'restrictly_mismatch_flag' ) ) {
			?>
			<div class="notice notice-warning is-dismissible">
				<p>
					<strong><?php esc_html_e( 'Restrictly Notice:', 'restrictly-access-control' ); ?></strong>
					<?php
					// Output the mismatch count from the static property.
					echo esc_html(
						sprintf(
							// translators: %d is the number of menu items with mismatched restrictions.
							_n( 'There is %d menu item with mismatched restrictions.', 'There are %d menu items with mismatched restrictions.', self::$mismatch_count, 'restrictly-access-control' ),
							self::$mismatch_count
						)
					);
					?>
				</p>
			</div>
			<?php
			// Remove flag after showing notice.
			delete_transient( 'restrictly_mismatch_flag' );
		}
	}

	/**
	 * Add Restrictly™ mismatch indicators to nav menu items.
	 *
	 * Compares each menu item's visibility and role restrictions
	 * against its linked page or post restriction settings.
	 * Flags mismatched items with visual indicators in the admin menu editor.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_Post&object{classes:array<string>,object_id:int,type:string} $menu_item Menu item object.
	 * @return WP_Post The modified menu item with Restrictly flags applied.
	 */
	public static function restrictly_mark_mismatched_menu_items( $menu_item ) {
		if ( ! get_option( 'restrictly_enable_menu_flags', 1 ) ) {
			return $menu_item;
		}

		// Ensure mutable classes.
		if ( ! isset( $menu_item->classes ) || ! is_array( $menu_item->classes ) ) {
			/**
			 * Ignore next line for PHPStan type validation.
			 *
			 * @phpstan-ignore-next-line
			 */
			$menu_item->classes = array();
		}

		// === Handle custom links (no page object) ===
		if ( 'custom' === (string) $menu_item->type ) {
			$visibility = get_post_meta( $menu_item->ID, 'restrictly_menu_visibility', true );
			$visibility = ( '' !== $visibility && false !== $visibility ) ? $visibility : 'everyone';
			$roles      = (array) get_post_meta( $menu_item->ID, 'restrictly_menu_roles', true );
			$roles      = array_unique( array_filter( array_map( 'trim', $roles ) ) );

			// Only invalid if "Everyone" + roles selected.
			if ( 'everyone' === $visibility && ! empty( $roles ) ) {
				/**
				 * Ignore next line for PHPStan type validation.
				 *
				 * @phpstan-ignore-next-line
				 */
				$menu_item->classes[] = 'restrictly-mismatch';
				self::add_highlight_script( (int) $menu_item->ID, 'has-restrictly-mismatch' );
			} else {
				/**
				 * Ignore next line for PHPStan type validation.
				 *
				 * @phpstan-ignore-next-line
				 */
				$menu_item->classes[] = 'restrictly-match';
				self::add_highlight_script( (int) $menu_item->ID, 'has-restrictly-match' );
			}

			return $menu_item;
		}

		// === Skip unsupported or non-object menu items ===
		if ( empty( $menu_item->object_id ) ) {
			return $menu_item;
		}

		// === Normalize Menu Meta ===
		$visibility = get_post_meta( $menu_item->ID, '_restrictly_menu_visibility', true );
		$visibility = ( '' !== $visibility && false !== $visibility ) ? $visibility : 'everyone';
		$roles      = (array) get_post_meta( $menu_item->ID, 'restrictly_menu_roles', true );
		$roles      = array_unique( array_filter( array_map( 'trim', $roles ) ) );

		// === Normalize Page Meta ===
		$page_login_status  = get_post_meta( $menu_item->object_id, 'restrictly_page_access_by_login_status', true );
		$page_allowed_roles = get_post_meta( $menu_item->object_id, 'restrictly_page_access_by_role', true );
		$page_allowed_roles = is_array( $page_allowed_roles ) ? $page_allowed_roles : array();
		$page_allowed_roles = array_unique( array_filter( array_map( 'trim', $page_allowed_roles ) ) );
		$page_login_status  = ( empty( $page_login_status ) || 'everyone' === $page_login_status ) ? '' : (string) $page_login_status;

		// === Cache bypass if stale ===
		global $wpdb;

		$cache_key = 'restrictly_page_meta_' . (int) $menu_item->object_id;
		$meta_rows = wp_cache_get( $cache_key, 'restrictly-access-control' );

		if ( false === $meta_rows ) {
			/**
			 * Direct meta query fallback — safe, sanitized, and intentionally uncached.
			 *
			 * @phpcs:disable WordPress.DB.DirectDatabaseQuery
			 * @phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
			 */
			$meta_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT meta_key, meta_value FROM {$wpdb->postmeta}
				 WHERE post_id = %d
				   AND meta_key IN (%s, %s)",
					$menu_item->object_id,
					'restrictly_page_access_by_login_status',
					'restrictly_page_access_by_role'
				),
				'ARRAY_A'
			);
			/**
			 * Re-enable PHPCS checks for direct database query rules.
			 *
			 * @phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.DirectQuery
			 */
			wp_cache_set( $cache_key, $meta_rows, 'restrictly-access-control', 60 );
		}

		foreach ( $meta_rows as $meta ) {
			if ( 'restrictly_page_access_by_login_status' === $meta['meta_key'] ) {
				$page_login_status = ( 'everyone' === $meta['meta_value'] || '' === $meta['meta_value'] )
						? ''
						: (string) $meta['meta_value'];
			}
			if ( 'restrictly_page_access_by_role' === $meta['meta_key'] ) {
				$maybe_roles = maybe_unserialize( $meta['meta_value'] );
				if ( is_array( $maybe_roles ) && ! empty( $maybe_roles ) ) {
					$page_allowed_roles = array_unique( array_filter( array_map( 'trim', $maybe_roles ) ) );
				}
			}
		}

		// === Determine restriction status ===
		$menu_is_open    = ( 'everyone' === $visibility && empty( $roles ) );
		$page_is_open    = ( ( '' === $page_login_status || 'everyone' === $page_login_status ) && empty( $page_allowed_roles ) );
		$menu_restricted = ! $menu_is_open;
		$page_restricted = ! $page_is_open;

		// === Compare restrictions ===
		$has_mismatch = false;

		if ( $menu_restricted && $page_restricted ) {
			if ( $visibility !== $page_login_status ) {
				$has_mismatch = true;
			}
			if (
					! empty( array_diff( $roles, $page_allowed_roles ) ) ||
					! empty( array_diff( $page_allowed_roles, $roles ) )
			) {
				$has_mismatch = true;
			}
		} elseif ( $menu_restricted xor $page_restricted ) {
			$has_mismatch = true;
		}

		// === Apply Classes + Marker ===
		if ( $has_mismatch ) {
			/**
			 * Ignore next line for PHPStan type validation.
			 *
			 * @phpstan-ignore-next-line
			 */
			$menu_item->classes[] = 'restrictly-mismatch';
			self::add_highlight_script( (int) $menu_item->ID, 'has-restrictly-mismatch' );

		} elseif ( $menu_is_open && $page_is_open ) {
			/**
			 * Ignore next line for PHPStan type validation.
			 *
			 * @phpstan-ignore-next-line
			 */
			$menu_item->classes[] = 'restrictly-match';
			self::add_highlight_script( (int) $menu_item->ID, 'has-restrictly-match' );

		} elseif ( $menu_restricted && $page_restricted ) {
			/**
			 * Ignore next line for PHPStan type validation.
			 *
			 * @phpstan-ignore-next-line
			 */
			$menu_item->classes[] = 'restrictly-match';
			self::add_highlight_script( (int) $menu_item->ID, 'has-restrictly-match' );

		} else {
			/**
			 * Ignore next line for PHPStan type validation.
			 *
			 * @phpstan-ignore-next-line
			 */
			$menu_item->classes[] = 'restrictly-neutral';
			self::add_highlight_script( (int) $menu_item->ID, 'has-restrictly-neutral' );
		}

		return $menu_item;
	}

	/**
	 * Injects a JavaScript snippet that adds a highlight class
	 * to the `.menu-item-settings` node for a specific menu item row.
	 *
	 * Uses `admin_print_footer_scripts` so we only output once per page load.
	 *
	 * @param int    $id        Menu item ID.
	 * @param string $css_class CSS class name to apply to the settings container.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	private static function add_highlight_script( int $id, string $css_class ): void {
		static $queued = array();

		// Prevent duplicate script blocks for the same (id,class) pair.
		$key = $id . ':' . $css_class;
		if ( isset( $queued[ $key ] ) ) {
			return;
		}
		$queued[ $key ] = true;

		add_action(
			'admin_print_footer_scripts',
			function () use ( $id, $css_class ) {
				?>
					<script>
						document.addEventListener('DOMContentLoaded', function () {
							var el = document.getElementById('menu-item-settings-<?php echo esc_js( (string) $id ); ?>');
							if (el && !el.classList.contains('<?php echo esc_js( $css_class ); ?>')) {
								el.classList.add('<?php echo esc_js( $css_class ); ?>');
							}
						});
					</script>
					<?php
			},
			99
		);
	}

	/**
	 * Adds custom fields to menu items in the Appearance > Menus screen.
	 *
	 * @param int   $item_id Menu item ID.
	 * @param mixed $item    Menu item object.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function restrictly_add_menu_custom_fields( int $item_id, mixed $item ): void {
		// Get visibility and roles for the menu item.
		$visibility = get_post_meta( $item_id, 'restrictly_menu_visibility', true );
		$visibility = ( '' !== $visibility && false !== $visibility ) ? $visibility : 'everyone';
		$roles      = get_post_meta( $item_id, 'restrictly_menu_roles', true );
		$roles      = is_array( $roles ) ? $roles : array();
		$all_roles  = RoleService::get_available_roles();

		// Initialize warnings and flags.
		$role_warning       = '';
		$visibility_warning = '';
		$mismatch_detected  = false;
		$visibility_class   = '';

		// Check if menu flags are enabled.
		if ( get_option( 'restrictly_enable_menu_flags', 1 ) ) {

			// Skip or evaluate menu item based on type.
			if ( 'custom' === (string) $item->type ) {
				// Handle custom links separately.
				$visibility_class  = 'restrictly-match-visibility';
				$mismatch_detected = false;

				// Invalid combo: Everyone + Roles.
				if ( 'everyone' === $visibility && ! empty( $roles ) ) {
					$role_warning     .= sprintf(
						'<p class="restrictly-mismatch-warning">%s</p>',
						esc_html__( 'Invalid setup: "Everyone" cannot have role restrictions selected.', 'restrictly-access-control' )
					);
					$mismatch_detected = true;
				}
			} elseif ( ! empty( $item->object_id ) ) {

				// Page meta.
				$page_login_status  = get_post_meta( $item->object_id, 'restrictly_page_access_by_login_status', true );
				$page_allowed_roles = get_post_meta( $item->object_id, 'restrictly_page_access_by_role', true );

				// Normalize arrays.
				$roles              = array_unique( array_filter( array_map( 'trim', (array) $roles ) ) );
				$page_allowed_roles = array_unique( array_filter( array_map( 'trim', (array) $page_allowed_roles ) ) );

				$page_login_status  = ( empty( $page_login_status ) || 'everyone' === $page_login_status ) ? '' : (string) $page_login_status;
				$page_allowed_roles = empty( $page_allowed_roles ) ? array() : $page_allowed_roles;
				$roles              = empty( $roles ) ? array() : $roles;

				// Unrestricted checks.
				$menu_is_unrestricted = ( 'everyone' === $visibility && empty( $roles ) );
				$page_is_unrestricted = ( ( '' === $page_login_status || 'everyone' === $page_login_status ) && empty( $page_allowed_roles ) );

				// Perfect match shortcut.
				if ( $menu_is_unrestricted && $page_is_unrestricted ) {
					$visibility_class = 'restrictly-match-visibility';
				} else {

					// === ROLE MISMATCH ===
					if ( ! empty( $roles ) ) {
						if ( empty( $page_allowed_roles ) ) {
							$role_warning     .= '<p class="restrictly-mismatch-warning">' . esc_html__( 'Role mismatch. Page has no role restrictions.', 'restrictly-access-control' ) . '</p>';
							$mismatch_detected = true;
						} else {
							$extra_roles   = array_diff( $roles, $page_allowed_roles );
							$missing_roles = array_diff( $page_allowed_roles, $roles );

							if ( ! empty( $extra_roles ) || ! empty( $missing_roles ) ) {
								$role_warning     .= '<p class="restrictly-mismatch-warning">' .
													esc_html__( 'Role mismatch. Page allows only:', 'restrictly-access-control' ) .
													' <strong>' . esc_html( implode( ', ', array_map( 'ucfirst', $page_allowed_roles ) ) ) . '</strong></p>';
								$mismatch_detected = true;
							}
						}
					} elseif ( ! empty( $page_allowed_roles ) ) {
						$role_warning     .= '<p class="restrictly-mismatch-warning">' .
											esc_html__( 'Role mismatch. Menu has no role restrictions. Page allows only:', 'restrictly-access-control' ) .
											' <strong>' . esc_html( implode( ', ', array_map( 'ucfirst', $page_allowed_roles ) ) ) . '</strong></p>';
						$mismatch_detected = true;
					}

					// === VISIBILITY MISMATCH ===
					$visibility_mismatch = false;

					if ( '' !== $page_login_status && $page_login_status !== $visibility ) {
						$visibility_mismatch = true;
						$visibility_warning .= '<p class="restrictly-mismatch-warning">' .
												esc_html__( 'Visibility mismatch. Page requires:', 'restrictly-access-control' ) .
												' <strong>' . esc_html( ucfirst( str_replace( '_', ' ', $page_login_status ) ) ) . '</strong></p>';

					} elseif ( 'everyone' === $visibility && ! empty( $roles ) ) {
						$role_warning     .= '<p class="restrictly-mismatch-warning">' .
											esc_html__( 'Invalid setup: "Everyone" cannot have role restrictions selected.', 'restrictly-access-control' ) .
											'</p>';
						$mismatch_detected = true;

					} elseif ( '' === $page_login_status && false === $menu_is_unrestricted && empty( $page_allowed_roles ) ) {
						$visibility_mismatch = true;
						$visibility_warning .= '<p class="restrictly-mismatch-warning">' .
												esc_html__( 'Page has no restrictions. Menu should be set to "Everyone" with no roles.', 'restrictly-access-control' ) .
												'</p>';
					}

					$visibility_class = $visibility_mismatch ? 'restrictly-mismatch-field' : 'restrictly-match-visibility';
					if ( $visibility_mismatch ) {
						$mismatch_detected = true;
					}
				}
			}
		}

		// Admin banner counter.
		if ( $mismatch_detected ) {
			++self::$mismatch_count;
			set_transient( 'restrictly_mismatch_flag', true, 60 );
		}

		// === SHOW/HIDE ROLES ON INITIAL RENDER ===
		$show_roles = ( 'logged_in_users' === $visibility );
		?>

		<?php wp_nonce_field( 'restrictly_save_menu_meta_box', 'restrictly_save_menu_meta_box_nonce' ); ?>

		<div class="description-wide strictly-menu-panel restrictly-menu-panel">

			<p class="restrictly-menu-panel-title">
				<span class="restrictly-icon-16"></span>
				<strong><?php esc_html_e( 'Restrictly Menu Visibility', 'restrictly-access-control' ); ?></strong>
			</p>

			<?php
			// Visibility mismatch warning.
			if ( ! empty( $visibility_warning ) ) {
				echo wp_kses_post( $visibility_warning );
			}
			?>

			<p class="restrictly-m-t-0">
				<label for="restrictly_menu_visibility_<?php echo esc_attr( (string) $item_id ); ?>">
					<strong><?php esc_html_e( 'Visibility:', 'restrictly-access-control' ); ?></strong>
				</label>
				<select name="restrictly_menu_visibility[<?php echo esc_attr( (string) $item_id ); ?>]"
						id="restrictly_menu_visibility_<?php echo esc_attr( (string) $item_id ); ?>"
						class="<?php echo esc_attr( $visibility_class ); ?>">
					<option value="everyone" <?php selected( $visibility, 'everyone' ); ?>><?php esc_html_e( 'Everyone', 'restrictly-access-control' ); ?></option>
					<option value="logged_in_users" <?php selected( $visibility, 'logged_in_users' ); ?>><?php esc_html_e( 'Logged In Users', 'restrictly-access-control' ); ?></option>
					<option value="logged_out_users" <?php selected( $visibility, 'logged_out_users' ); ?>><?php esc_html_e( 'Logged Out Users', 'restrictly-access-control' ); ?></option>
				</select>
			</p>

			<?php
			if ( ! empty( $role_warning ) ) {
				echo wp_kses_post( $role_warning );
			}
			?>

			<!-- Roles Block -->
			<p class="restrictly-m-t-0" style="<?php echo $show_roles ? '' : 'display:none;'; ?>">
				<label><strong><?php esc_html_e( 'Allowed Roles:', 'restrictly-access-control' ); ?></strong></label><br>

				<?php foreach ( $all_roles as $role_key => $role_name ) : ?>
					<?php
					$role_class = '';
					if ( ! empty( $page_allowed_roles ) ) {
						if ( in_array( $role_key, $page_allowed_roles, true ) ) {
							$role_class = in_array( $role_key, (array) $roles, true ) ? 'restrictly-match-field' : 'restrictly-mismatch-field';
						} elseif ( in_array( $role_key, (array) $roles, true ) ) {
							$role_class = 'restrictly-mismatch-field';
						}
					} elseif ( 'everyone' === $visibility && ! empty( $roles ) ) {
						if ( in_array( $role_key, (array) $roles, true ) ) {
							$role_class = 'restrictly-mismatch-field';
						}
					}
					?>
					<label>
						<input type="checkbox"
								name="restrictly_menu_roles[<?php echo esc_attr( (string) $item_id ); ?>][]"
								value="<?php echo esc_attr( $role_key ); ?>"
								class="<?php echo esc_attr( $role_class ); ?>"
								<?php checked( in_array( $role_key, (array) $roles, true ) ); ?>>
						<?php echo esc_html( $role_name ); ?>
					</label><br>
				<?php endforeach; ?>
			</p>

		</div>
		<?php
	}

	/**
	 * Saves custom menu item fields when the menu is updated.
	 *
	 * @param int $menu_id         Unused parameter.
	 * @param int $menu_item_db_id Menu item database ID.
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function restrictly_save_menu_fields( int $menu_id, int $menu_item_db_id ): void {
		// Verify nonce.
		if (
				! isset( $_POST['restrictly_save_menu_meta_box_nonce'] ) ||
				! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['restrictly_save_menu_meta_box_nonce'] ) ), 'restrictly_save_menu_meta_box' )
		) {
			return;
		}

		// Check if this is an autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		// Ensure the user has permission to edit menus.
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}

		// Sanitize and save the menu visibility setting.
		if ( isset( $_POST['restrictly_menu_visibility'][ $menu_item_db_id ] ) ) {
			$visibility = sanitize_text_field( wp_unslash( $_POST['restrictly_menu_visibility'][ $menu_item_db_id ] ) );
			update_post_meta( $menu_item_db_id, 'restrictly_menu_visibility', $visibility );
		} else {
			delete_post_meta( $menu_item_db_id, 'restrictly_menu_visibility' );
		}

		// Sanitize and save user roles (array of values).
		if ( isset( $_POST['restrictly_menu_roles'][ $menu_item_db_id ] ) && is_array( $_POST['restrictly_menu_roles'][ $menu_item_db_id ] ) ) {
			$roles = array_map( 'sanitize_text_field', wp_unslash( $_POST['restrictly_menu_roles'][ $menu_item_db_id ] ) );
			update_post_meta( $menu_item_db_id, 'restrictly_menu_roles', $roles );
		} else {
			update_post_meta( $menu_item_db_id, 'restrictly_menu_roles', array() );
		}
	}
}
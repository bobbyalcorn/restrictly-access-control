<?php
/**
 * Handles quick edit functionality for content types.
 *
 * @package Restrictly
 * @since   0.1.0
 */

namespace Restrictly\Core\Admin;

use Restrictly\Core\Common\RoleHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Handles quick edit functionality for content types.
 *
 * @since 0.1.0
 */
class QuickEditContentType extends ContentTypeBase {

	/**
	 * Initialize quick edit functionality.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function init(): void {
		// Only run in the admin.
		if ( ! is_admin() ) {
			return;
		}

		// Get the selected content types and ensure it's an array.
		$content_types = get_option( 'restrictly_content_types', array( 'page' ) );

		// Add filters and actions for each selected content type.
		foreach ( $content_types as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( 'Restrictly\Core\Admin\ContentTypeBase', 'add_restrictly_columns' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( 'Restrictly\Core\Admin\ContentTypeBase', 'render_restrictly_columns' ), 10, 2 );
		}

		// Add the Quick Edit custom box.
		add_action( 'quick_edit_custom_box', array( __CLASS__, 'restrictly_quick_edit_custom_box' ), 10, 2 );
	}

	/**
	 * Output the Quick Edit custom box fields.
	 *
	 * @param string $column_name The column name.
	 * @param string $post_type The post type.
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function restrictly_quick_edit_custom_box( string $column_name, string $post_type ): void {
		// Get the selected content types and ensure it's an array.
		$content_types = get_option( 'restrictly_content_types', array( 'page' ) );
		if ( ! in_array( $post_type, $content_types, true ) ) {
			return;
		}

		// Output Quick Edit fields only once - in the login status column.
		if ( 'restrictly_login_status' !== $column_name ) {
			return;
		}
		?>
		<fieldset class="inline-edit-col restrictly-quick-edit-panel">
			<div class="inline-edit-col">
				<div class="restrictly-quick-edit-panel-header">
					<span class="restrictly-icon-16"><!-- Loaded dynamically by the class-base.php --></span>
					<?php esc_html_e( 'Restrictly Content Visibility', 'restrictly-access-control' ); ?>
				</div>
				<!-- Hidden nonce field for Quick Edit -->
				<input type="hidden" name="restrictly_save_page_access_meta_box_nonce" value="<?php echo esc_attr( wp_create_nonce( 'restrictly_save_page_access_meta_box' ) ); ?>">

				<!-- Login Status -->
				<table class="restrictly-w-100 restrictly-m-b-10">
					<tr>
						<td>
							<span class="title restrictly-screen-reader-span-140"><strong><?php esc_html_e( 'Login Status:', 'restrictly-access-control' ); ?></strong></span>
							<label for="restrictly_page_access_by_login_status_quickedit" class="screen-reader-text"><?php esc_html_e( 'Login Status:', 'restrictly-access-control' ); ?></label>
							<select class="restrictly-select" name="restrictly_page_access_by_login_status" id="restrictly_page_access_by_login_status_quickedit">
								<option value="everyone"><?php esc_html_e( 'Everyone', 'restrictly-access-control' ); ?></option>
								<option value="logged_in_users"><?php esc_html_e( 'Logged In Users', 'restrictly-access-control' ); ?></option>
								<option value="logged_out_users"><?php esc_html_e( 'Logged Out Users', 'restrictly-access-control' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<!-- User Role Checkboxes -->
				<div class="restrictly_role_selection_quickedit" style="display: none;">
					<table class="restrictly-w-100 restrictly-m-b-10">
						<tr>
							<td>
								<span class="title restrictly-screen-reader-span-140"><strong><?php esc_html_e( 'Specific Roles:', 'restrictly-access-control' ); ?></strong></span>
								<?php
								$available_roles = RoleHelper::get_available_roles();

								foreach ( $available_roles as $role_key => $role_name ) {
									printf(
										'<span class="restrictly-inline-checkbox-group">
												<label class="restrictly-inline-label">
													<input class="restrictly-checkbox" type="checkbox" name="restrictly_page_access_by_role[]" value="%s"> %s
												</label>
											</span>',
										esc_attr( $role_key ),
										esc_html( $role_name )
									);
								}
								?>
							</td>
						</tr>
					</table>

				</div>

				<!-- Enforcement Action -->
				<div class="restrictly_enforcement_quickedit" style="display: none;">

					<table class="restrictly-w-100 restrictly-m-b-10">
						<tr>
							<td>
								<span class="title restrictly-screen-reader-span-140"><strong><?php esc_html_e( 'Enforcement Action:', 'restrictly-access-control' ); ?></strong></span>
								<label for="restrictly_enforcement_action_quickedit" class="screen-reader-text"><?php esc_html_e( 'Enforcement Action:', 'restrictly-access-control' ); ?></label>
								<select class="restrictly-select" name="restrictly_enforcement_action" id="restrictly_enforcement_action_quickedit">
									<option value="default"><?php esc_html_e( 'Use Default', 'restrictly-access-control' ); ?></option>
									<option value="custom_message"><?php esc_html_e( 'Custom Message', 'restrictly-access-control' ); ?></option>
									<option value="custom_url"><?php esc_html_e( 'Custom URL', 'restrictly-access-control' ); ?></option>
								</select>
							</td>
						</tr>
					</table>

					<!-- Custom Message -->
					<div id="restrictly_custom_message_container_quickedit">
						<table class="restrictly-w-100">
							<tr>
								<td>
									<span class="title restrictly-screen-reader-span-140"><strong><?php esc_html_e( 'Custom Message:', 'restrictly-access-control' ); ?></strong></span>
									<label for="restrictly_custom_message_quickedit" class="screen-reader-text"><?php esc_html_e( 'Custom Message', 'restrictly-access-control' ); ?></label>
									<textarea class="restrictly-textarea" name="restrictly_custom_message" id="restrictly_custom_message_quickedit"></textarea>
								</td>
							</tr>
						</table>
					</div>

					<!-- Custom Forward URL -->
					<div id="restrictly_custom_forward_url_container_quickedit">
						<table class="restrictly-w-100">
							<tr>
								<td>
									<span class="title restrictly-screen-reader-span-140"><strong><?php esc_html_e( 'Custom Forward URL:', 'restrictly-access-control' ); ?></strong></span>
									<label for="restrictly_custom_forward_url_quickedit" class="screen-reader-text"><?php esc_html_e( 'Custom Forward URL:', 'restrictly-access-control' ); ?></label>
									<input class="restrictly-input" type="text" name="restrictly_custom_forward_url" id="restrictly_custom_forward_url_quickedit" />
								</td>
							</tr>
						</table>
					</div>

				</div>

			</div>
		</fieldset>
		<?php
	}
}

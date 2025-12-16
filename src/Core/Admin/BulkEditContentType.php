<?php
/**
 * Handles bulk edit functionality for content types.
 *
 * @package Restrictly
 * @since   0.1.0
 */

namespace Restrictly\Core\Admin;

use Restrictly\Core\Common\RoleHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Handles bulk edit functionality for content types.
 *
 * @since 0.1.0
 */
class BulkEditContentType extends ContentTypeBase {

	/**
	 * Initialize bulk edit functionality.
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

		// Bulk edit custom box.
		add_action( 'bulk_edit_custom_box', array( __CLASS__, 'restrictly_bulk_edit_custom_box' ), 10, 2 );

		// Enqueue admin scripts for bulk edit ajax.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'restrictly_enqueue_admin_scripts' ) );

		// Handle bulk edit save.
		add_action( 'wp_ajax_restrictly_bulk_edit', array( __CLASS__, 'restrictly_bulk_edit_save' ) );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function restrictly_enqueue_admin_scripts( string $hook ): void {
		if ( 'edit.php' === $hook ) {
			wp_localize_script(
				'restrictly-content-types-script',
				'restrictlyAdmin',
				array(
					'bulkEditNonce' => wp_create_nonce( 'restrictly_bulk_edit_nonce' ),
					'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				)
			);
		}
	}

	/**
	 * Output the Bulk Edit custom box fields.
	 *
	 * @param string $column_name The column name.
	 * @param string $post_type The post type.
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function restrictly_bulk_edit_custom_box( string $column_name, string $post_type ): void {
		// Get the selected content types and ensure it's an array.
		$content_types = (array) get_option( 'restrictly_content_types', array( 'page' ) );
		if ( ! in_array( $post_type, $content_types, true ) ) {
			return;
		}

		// If we're not in the login status column, return.
		if ( 'restrictly_login_status' !== $column_name ) {
			return;
		}
		?>
		<fieldset class="inline-edit-col restrictly-bulk-edit-panel">
			<div class="inline-edit-col">
				<div class="restrictly-bulk-edit-panel-header">
					<span class="restrictly-icon-16">
						<!-- Loaded dynamically by the class-base.php --></span>
					<?php esc_html_e( 'Restrictly Content Visibility Bulk Edit', 'restrictly-access-control' ); ?>
				</div>

				<!-- Login Status -->
				<table class="restrictly-w-100 restrictly-m-b-10">
					<tr>
						<td>
							<span class="title restrictly-screen-reader-span-140"><strong><?php esc_html_e( 'Login Status:', 'restrictly-access-control' ); ?></strong></span>
							<label for="bulk_restrictly_page_access_by_login_status" class="screen-reader-text"><?php esc_html_e( 'Login Status:', 'restrictly-access-control' ); ?></label>
							<select class="restrictly-select" name="bulk_restrictly_page_access_by_login_status" id="bulk_restrictly_page_access_by_login_status">
								<option value=""><?php esc_html_e( '- No Change -', 'restrictly-access-control' ); ?></option>
								<option value="everyone"><?php esc_html_e( 'Everyone', 'restrictly-access-control' ); ?></option>
								<option value="logged_in_users"><?php esc_html_e( 'Logged In Users', 'restrictly-access-control' ); ?></option>
								<option value="logged_out_users"><?php esc_html_e( 'Logged Out Users', 'restrictly-access-control' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<!-- User Role Checkboxes -->
				<div class="restrictly_role_selection_bulkedit" style="display: none;">
					<table class="restrictly-w-100 restrictly-m-b-10">
						<tr>
							<td>
								<span class="title restrictly-screen-reader-span-140"><strong><?php esc_html_e( 'Specific Roles:', 'restrictly-access-control' ); ?></strong></span>
								<?php
								$available_roles = RoleHelper::get_available_roles();

								/**
								 * Available roles array used for rendering checkboxes.
								 *
								 * @var array<string, string> $available_roles
								 */
								foreach ( $available_roles as $role_key => $role_name ) {
									printf(
										'<span class="restrictly-inline-checkbox-group">
												<label class="restrictly-inline-label">
													<input class="restrictly-checkbox" type="checkbox" name="bulk_restrictly_page_access_by_role[]" value="%s"> %s
												</label>
											</span>',
										esc_attr( (string) $role_key ),
										esc_html( $role_name )
									);
								}
								?>
							</td>
						</tr>
					</table>
				</div>

				<div class="restrictly_enforcement_bulkedit" style="display: none;">

					<!-- Enforcement Action -->
					<table class="restrictly-w-100 restrictly-m-b-10">
						<tr>
							<td>
								<span class="title restrictly-screen-reader-span-140"><strong><?php esc_html_e( 'Enforcement Action:', 'restrictly-access-control' ); ?></strong></span>
								<label for="bulk_restrictly_enforcement_action" class="screen-reader-text"><?php esc_html_e( 'Enforcement Action:', 'restrictly-access-control' ); ?></label>
								<select class="restrictly-select" name="bulk_restrictly_enforcement_action" id="bulk_restrictly_enforcement_action">
									<option value=""><?php esc_html_e( '- No Change -', 'restrictly-access-control' ); ?></option>
									<option value="default"><?php esc_html_e( 'Use Default', 'restrictly-access-control' ); ?></option>
									<option value="custom_message"><?php esc_html_e( 'Custom Message', 'restrictly-access-control' ); ?></option>
									<option value="custom_url"><?php esc_html_e( 'Custom URL', 'restrictly-access-control' ); ?></option>
								</select>
							</td>
						</tr>
					</table>

					<!-- Custom Message -->
					<div id="restrictly_custom_message_container_bulkedit">
						<table class="restrictly-w-100">
							<tr>
								<td>
									<span class="title restrictly-screen-reader-span-140"><strong><?php esc_html_e( 'Custom Message:', 'restrictly-access-control' ); ?></strong></span>
									<label for="bulk_restrictly_custom_message" class="screen-reader-text"><?php esc_html_e( 'Custom Message', 'restrictly-access-control' ); ?></label>
									<textarea class="restrictly-textarea" name="bulk_restrictly_custom_message" id="bulk_restrictly_custom_message"></textarea>
								</td>
							</tr>
						</table>
					</div>

					<!-- Custom Forward URL -->
					<div id="restrictly_custom_forward_url_container_bulkedit">
						<table class="restrictly-w-100">
							<tr>
								<td>
									<span class="title restrictly-screen-reader-span-140"><strong><?php esc_html_e( 'Custom Forward URL:', 'restrictly-access-control' ); ?></strong></span>
									<label for="bulk_restrictly_custom_forward_url" class="screen-reader-text"><?php esc_html_e( 'Custom Forward URL:', 'restrictly-access-control' ); ?></label>
									<input class="restrictly-input" type="text" name="bulk_restrictly_custom_forward_url" id="bulk_restrictly_custom_forward_url" />
								</td>
							</tr>
						</table>
					</div>

				</div>

			</div>
		</fieldset>
		<?php
	}

	/**
	 * Handles bulk editing of content restrictions for multiple posts.
	 *
	 * This method processes AJAX requests to update restriction settings on multiple posts
	 * at once, based on user role and login status. It ensures security checks, validates
	 * input data, and updates post meta accordingly.
	 *
	 * @return void Outputs a JSON response indicating success or failure.
	 *
	 *  @since 0.1.0
	 */
	public static function restrictly_bulk_edit_save(): void {
		// Check for nonce security.
		if (
			! isset( $_POST['security'] ) ||
			! wp_verify_nonce( sanitize_text_field( (string) wp_unslash( $_POST['security'] ) ), 'restrictly_bulk_edit_nonce' )
		) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'restrictly-access-control' ) ) );
		}

		// Validate user permissions.
		if ( ! current_user_can( 'edit_posts' ) || empty( $_POST['post_ids'] ) || ! is_array( $_POST['post_ids'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request', 'restrictly-access-control' ) ) );
		}

		// Ensure post IDs are integers.
		$post_ids = array_map( 'intval', $_POST['post_ids'] );

		// Fields to update.
		$fields_to_update = array();

		// Update the login status if provided.
		if ( isset( $_POST['restrictly_page_access_by_login_status'] ) && '' !== $_POST['restrictly_page_access_by_login_status'] ) {
			$fields_to_update['restrictly_page_access_by_login_status'] = sanitize_text_field( wp_unslash( $_POST['restrictly_page_access_by_login_status'] ) );
		}

		// Update the user roles if provided.
		if ( ! empty( $_POST['restrictly_page_access_by_role'] ) && is_array( $_POST['restrictly_page_access_by_role'] ) ) {
			$fields_to_update['restrictly_page_access_by_role'] = array_map( 'sanitize_text_field', wp_unslash( $_POST['restrictly_page_access_by_role'] ) );
		}

		// Update the enforcement action if provided.
		if ( isset( $_POST['restrictly_enforcement_action'] ) && '' !== $_POST['restrictly_enforcement_action'] ) {
			$enforcement_action = sanitize_text_field( wp_unslash( $_POST['restrictly_enforcement_action'] ) );
			if ( in_array( $enforcement_action, array( 'default', 'custom_message', 'custom_url' ), true ) ) {
				$fields_to_update['restrictly_enforcement_action'] = $enforcement_action;
			}
		}

		// Update the custom message if provided.
		if ( ! empty( $_POST['restrictly_custom_message'] ) ) {
			$fields_to_update['restrictly_custom_message'] = wp_kses_post( wp_unslash( $_POST['restrictly_custom_message'] ) );
		}

		// Update the custom forward URL if provided.
		if ( ! empty( $_POST['restrictly_custom_forward_url'] ) ) {
			$fields_to_update['restrictly_custom_forward_url'] = esc_url_raw( wp_unslash( $_POST['restrictly_custom_forward_url'] ) );
		}

		// If no changes were provided, return an error.
		if ( empty( $fields_to_update ) ) {
			wp_send_json_error( array( 'message' => __( 'No changes were provided.', 'restrictly-access-control' ) ) );
		}

		// Update the post meta for each post.
		foreach ( $post_ids as $post_id ) {
			// Ensure the user has permission to edit the post.
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}

			// Update the post meta fields.
			foreach ( $fields_to_update as $meta_key => $value ) {
				update_post_meta( $post_id, $meta_key, $value );
			}
		}

		// Return a success message.
		wp_send_json_success( array( 'message' => __( 'Bulk edit successful!', 'restrictly-access-control' ) ) );
	}
}

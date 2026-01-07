<?php
/**
 * Handles standard (full) edit functionality for content types.
 *
 * @package Restrictly
 * @since   0.1.0
 */

namespace Restrictly\Core\Admin;

use WP_Post;
use Restrictly\Core\Services\RoleService;

defined( 'ABSPATH' ) || exit;

/**
 * Handles standard (full) edit functionality for content types.
 *
 * @since 0.1.0
 */
class StandardEditContentType extends ContentTypeBase {

	/**
	 * Initialize the standard edit functionality
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

		// Add meta boxes to content types.
		add_action( 'add_meta_boxes', array( __CLASS__, 'restrictly_add_meta_boxes' ) );

		// Save the meta box data.
		add_action( 'save_post', array( __CLASS__, 'restrictly_save_meta_box' ) );
	}

	/**
	 * Add meta boxes to content types
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function restrictly_add_meta_boxes(): void {
		// Get the content types to add the meta box to.
		$content_types = get_option( 'restrictly_content_types', array( 'page' ) );

		// Add a meta box for each content type.
		foreach ( $content_types as $post_type ) {
			add_meta_box(
				'restrictly_page_access_meta_box_' . sanitize_title( $post_type ),
				esc_html__( 'Restrictly Content Visibility', 'restrictly-access-control' ),
				array( __CLASS__, 'restrictly_render_page_access_meta_box' ),
				$post_type,
				'normal'
			);
		}
	}

	/**
	 * Render the meta box on the full edit screen.
	 *
	 * @param WP_Post $post The post object.
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function restrictly_render_page_access_meta_box( WP_Post $post ): void {
		// Get the login status meta.
		$login_status = get_post_meta( $post->ID, 'restrictly_page_access_by_login_status', true );
		$login_status = ( '' !== $login_status && false !== $login_status ) ? $login_status : 'everyone';

		// Get the user role meta.
		$role = get_post_meta( $post->ID, 'restrictly_page_access_by_role', true );
		$role = ( is_array( $role ) ) ? $role : array();

		// Get the enforcement action meta.
		$enforcement_action = get_post_meta( $post->ID, 'restrictly_enforcement_action', true );
		$enforcement_action = ( '' !== $enforcement_action && false !== $enforcement_action ) ? $enforcement_action : 'default';

		// Get the custom message meta.
		$custom_message = get_post_meta( $post->ID, 'restrictly_custom_message', true );

		// Get the custom forward URL meta.
		$custom_forward_url = get_post_meta( $post->ID, 'restrictly_custom_forward_url', true );
		?>
		<!-- WordPress nonce field for security -->
		<?php wp_nonce_field( 'restrictly_save_page_access_meta_box', 'restrictly_save_page_access_meta_box_nonce' ); ?>
		<div class="restrictly-inside">

			<!-- Login Status -->
			<table class="restrictly-w-100 restrictly-m-b-10">
				<tr>
					<td>
						<span class="title restrictly-screen-reader-span-140"><strong><?php esc_html_e( 'Login Status:', 'restrictly-access-control' ); ?></strong></span>
						<label for="restrictly_page_access_by_login_status" class="screen-reader-text"><?php esc_html_e( 'Login Status:', 'restrictly-access-control' ); ?></label>
						<select class="restrictly-select" name="restrictly_page_access_by_login_status" id="restrictly_page_access_by_login_status">
							<option value="everyone" <?php selected( $login_status, 'everyone' ); ?>>
								<?php esc_html_e( 'Everyone', 'restrictly-access-control' ); ?>
							</option>
							<option value="logged_in_users" <?php selected( $login_status, 'logged_in_users' ); ?>>
								<?php esc_html_e( 'Logged In Users', 'restrictly-access-control' ); ?>
							</option>
							<option value="logged_out_users" <?php selected( $login_status, 'logged_out_users' ); ?>>
								<?php esc_html_e( 'Logged Out Users', 'restrictly-access-control' ); ?>
							</option>
						</select>
					</td>
				</tr>
			</table>

			<!-- User Role Checkboxes -->
			<table class="restrictly-w-100 restrictly-m-b-10" id="restrictly_page_access_by_role">
				<tr>
					<td>
						<span class="title restrictly-screen-reader-span-140"><strong><?php esc_html_e( 'Specific Roles:', 'restrictly-access-control' ); ?></strong></span>
						<?php
						$available_roles = RoleService::get_available_roles();

						foreach ( $available_roles as $role_key => $role_name ) {
							printf(
								'<span class="restrictly-inline-checkbox-group">
										<label class="restrictly-inline-label">
											<input class="restrictly-checkbox" type="checkbox" name="restrictly_page_access_by_role[]" value="%s" %s> %s
										</label>
									</span>',
								esc_attr( $role_key ),
								checked( in_array( $role_key, $role, true ), true, false ),
								esc_html( $role_name )
							);
						}
						?>
					</td>
				</tr>
			</table>

			<!-- Enforcement Action -->
			<table class="restrictly-w-100 restrictly-m-b-10">
				<tr>
					<td>
						<span class="title restrictly-screen-reader-span-140"><strong><?php esc_html_e( 'Enforcement Action:', 'restrictly-access-control' ); ?></strong></span>
						<label for="restrictly_enforcement_action" class="screen-reader-text"><?php esc_html_e( 'Enforcement Action:', 'restrictly-access-control' ); ?></label>
						<select class="restrictly-select" name="restrictly_enforcement_action" id="restrictly_enforcement_action">
							<option value="default" <?php selected( $enforcement_action, 'default' ); ?>>
								<?php esc_html_e( 'Use Default', 'restrictly-access-control' ); ?>
							</option>
							<option value="custom_message" <?php selected( $enforcement_action, 'custom_message' ); ?>>
								<?php esc_html_e( 'Custom Message', 'restrictly-access-control' ); ?>
							</option>
							<option value="custom_url" <?php selected( $enforcement_action, 'custom_url' ); ?>>
								<?php esc_html_e( 'Custom URL', 'restrictly-access-control' ); ?>
							</option>
						</select>
					</td>
				</tr>
			</table>

			<!-- Custom Message -->
			<div id="restrictly_custom_message_container" style="display: <?php echo esc_attr( ( 'custom_message' === $enforcement_action ? 'block' : 'none' ) ); ?>;">
				<table class="restrictly-w-100">
					<tr>
						<td>
							<span class="title restrictly-screen-reader-span-140"><strong><?php esc_html_e( 'Custom Message:', 'restrictly-access-control' ); ?></strong></span>
							<label for="restrictly_custom_message" class="screen-reader-text"><?php esc_html_e( 'Custom Message', 'restrictly-access-control' ); ?></label>
							<textarea class="restrictly-textarea" name="restrictly_custom_message" id="restrictly_custom_message"><?php echo esc_textarea( $custom_message ); ?></textarea>
						</td>
					</tr>
				</table>
			</div>

			<!-- Custom Forward URL -->
			<div id="restrictly_custom_forward_url_container" style="display: <?php echo esc_attr( 'custom_url' === $enforcement_action ? 'block' : 'none' ); ?>;">
				<table class="restrictly-w-100">
					<tr>
						<td>
							<span class="title restrictly-screen-reader-span-140"><strong><?php esc_html_e( 'Custom Forward URL:', 'restrictly-access-control' ); ?></strong></span>
							<label for="restrictly_custom_forward_url" class="screen-reader-text"><?php esc_html_e( 'Custom Forward URL:', 'restrictly-access-control' ); ?></label>
							<input class="restrictly-input" type="text" name="restrictly_custom_forward_url" id="restrictly_custom_forward_url" value="<?php echo esc_attr( $custom_forward_url ); ?>" />
						</td>
					</tr>
				</table>
			</div>

		</div>
		<?php
	}

	/**
	 * Save the meta box data.
	 *
	 * @param int $post_id The post ID.
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function restrictly_save_meta_box( int $post_id ): void {
		// Verify the nonce.
		if (
			! isset( $_POST['restrictly_save_page_access_meta_box_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['restrictly_save_page_access_meta_box_nonce'] ) ), 'restrictly_save_page_access_meta_box' )
		) {
			return;
		}

		// Check if this is an autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Ensure the user has permission to edit the post.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Sanitize and save the login status.
		if ( isset( $_POST['restrictly_page_access_by_login_status'] ) ) {
			$login_status = sanitize_text_field( wp_unslash( $_POST['restrictly_page_access_by_login_status'] ) );
			update_post_meta( $post_id, 'restrictly_page_access_by_login_status', $login_status );
		}

		// Sanitize and save user roles (array of values).
		if ( isset( $_POST['restrictly_page_access_by_role'] ) && is_array( $_POST['restrictly_page_access_by_role'] ) ) {
			$roles = array_map( 'sanitize_text_field', wp_unslash( $_POST['restrictly_page_access_by_role'] ) );
			update_post_meta( $post_id, 'restrictly_page_access_by_role', array_values( array_filter( $roles ) ) );
		} else {
			update_post_meta( $post_id, 'restrictly_page_access_by_role', array() );
		}

		// Sanitize and save enforcement action.
		if ( isset( $_POST['restrictly_enforcement_action'] ) ) {
			$enforcement_action = sanitize_text_field( wp_unslash( $_POST['restrictly_enforcement_action'] ) );
			if ( in_array( $enforcement_action, array( 'default', 'custom_message', 'custom_url' ), true ) ) {
				update_post_meta( $post_id, 'restrictly_enforcement_action', $enforcement_action );
			}
		}

		// Sanitize and save custom message (allow safe HTML).
		if ( ! empty( $_POST['restrictly_custom_message'] ) ) {
			$custom_message = wp_kses_post( wp_unslash( $_POST['restrictly_custom_message'] ) );
			update_post_meta( $post_id, 'restrictly_custom_message', $custom_message );
		}

		// Sanitize and save custom forward URL (ensure valid URL).
		if ( ! empty( $_POST['restrictly_custom_forward_url'] ) ) {
			$custom_forward_url = esc_url_raw( wp_unslash( $_POST['restrictly_custom_forward_url'] ) );
			update_post_meta( $post_id, 'restrictly_custom_forward_url', $custom_forward_url );
		} else {
			delete_post_meta( $post_id, 'restrictly_custom_forward_url' );
		}
	}
}

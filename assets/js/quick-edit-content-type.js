/**
 * Quick Edit functionality for Restrictly.
 *
 * Handles loading data into the inline Quick Edit interface and toggling enforcement action fields.
 *
 * @package Restrictly
 *
 * @since 0.1.0
 */

jQuery(document).ready(function ($) {
	/**
	 * Toggle enforcement action fields for Quick Edit.
	 *
	 * @param {string} action    The selected enforcement action.
	 * @param {object} container The inline editor container.
	 * @return {void}
	 *
	 * @since 0.1.0
	 */
	function toggleEnforcementFields(action, container) {
		var $customMessageContainer = container.find('#restrictly_custom_message_container_quickedit');
		var $customForwardContainer = container.find(
			'#restrictly_custom_forward_url_container_quickedit'
		);

		if (action === 'custom_message') {
			$customMessageContainer.show();
			$customForwardContainer.hide();
		} else if (action === 'custom_url') {
			$customForwardContainer.show();
			$customMessageContainer.hide();
		} else {
			$customMessageContainer.hide();
			$customForwardContainer.hide();
		}
	}

	/**
	 * Toggle fields based on login status.
	 *
	 * @param {string} loginStatus The selected login status.
	 * @param {object} container   The inline editor container.
	 * @return {void}
	 *
	 * @since 0.1.0
	 */
	function toggleLoginStatusFields(loginStatus, container) {
		var $roleSelectionContainer = container.find('.restrictly_role_selection_quickedit');
		var $enforcementContainer = container.find('.restrictly_enforcement_quickedit');
		var $customMessageContainer = container.find('#restrictly_custom_message_container_quickedit');
		var $customForwardContainer = container.find(
			'#restrictly_custom_forward_url_container_quickedit'
		);

		if (loginStatus !== 'everyone') {
			$enforcementContainer.show();

			if (loginStatus === 'logged_in_users') {
				$roleSelectionContainer.show();
			} else {
				$roleSelectionContainer.hide();
			}

			// Check enforcement action to determine visibility.
			var action = container.find('select[name="restrictly_enforcement_action"]').val();
			toggleEnforcementFields(action, container);
		} else {
			$roleSelectionContainer.hide();
			$enforcementContainer.hide();
			$customMessageContainer.hide();
			$customForwardContainer.hide();
		}
	}

	/**
	 * Overrides WordPress core inline edit behavior to integrate Restrictly fields.
	 *
	 * Populates custom Restrictly metadata (login status, roles, enforcement actions)
	 * into the Quick Edit interface.
	 *
	 * @since 0.1.0
	 */
	if (typeof inlineEditPost !== 'undefined') {
		var $wp_inline_edit = inlineEditPost.edit;

		inlineEditPost.edit = function (id) {
			$wp_inline_edit.apply(this, arguments);

			var post_id = typeof id === 'object' ? parseInt(this.getId(id)) : 0;
			if (post_id > 0) {
				var $postRow = $('#post-' + post_id);
				var $quickEditRow = $('#edit-' + post_id);

				// Extract stored meta data from hidden elements.
				var restrictStatus =
					$postRow.find('.restrictly_hidden_login_status').data('restrictly-login-status') ||
					'everyone';
				var restrictRoles =
					$postRow.find('.restrictly_hidden_user_role').data('restrictly-user-role') || '';
				var enforcementAction =
					$postRow
						.find('.restrictly_hidden_enforcement_action')
						.data('restrictly-enforcement-action') || 'default';
				var customMessage =
					$postRow
						.find('.restrictly_hidden_enforcement_action')
						.data('restrictly-custom-message') || '';
				var customForwardUrl =
					$postRow
						.find('.restrictly_hidden_enforcement_action')
						.data('restrictly-custom-forward-url') || '';

				// Convert roles into an array.
				if (Array.isArray(restrictRoles)) {
					// keep as is
				} else if (restrictRoles) {
					restrictRoles = restrictRoles.split(', ');
				} else {
					restrictRoles = [];
				}

				// Populate Quick Edit fields.
				var $loginSelect = $quickEditRow.find(
					'select[name="restrictly_page_access_by_login_status"]'
				);
				var $roleInputs = $quickEditRow.find('input[name="restrictly_page_access_by_role[]"]');
				var $enforcement = $quickEditRow.find('select[name="restrictly_enforcement_action"]');

				$loginSelect.val(restrictStatus);
				$roleInputs.prop('checked', false);
				if (restrictRoles.length) {
					$.each(restrictRoles, function (_, roleVal) {
						$roleInputs.filter('[value="' + roleVal + '"]').prop('checked', true);
					});
				}
				$enforcement.val(enforcementAction);

				// Toggle fields based on current login status.
				toggleLoginStatusFields(restrictStatus, $quickEditRow);

				// Toggle enforcement fields if needed.
				if (restrictStatus !== 'everyone') {
					toggleEnforcementFields(enforcementAction, $quickEditRow);

					// Set custom message or URL if needed.
					if (enforcementAction === 'custom_message') {
						$quickEditRow.find('textarea[name="restrictly_custom_message"]').val(customMessage);
					}
					if (enforcementAction === 'custom_url') {
						$quickEditRow.find('input[name="restrictly_custom_forward_url"]').val(customForwardUrl);
					}
				}
			}
		};
	}

	/**
	 * Handle login status field changes within Quick Edit.
	 *
	 * @since 0.1.0
	 */
	$(document).on('change', 'select[name="restrictly_page_access_by_login_status"]', function () {
		var loginStatus = $(this).val();
		var container = $(this).closest('.inline-edit-col');
		toggleLoginStatusFields(loginStatus, container);
	});

	/**
	 * Handle enforcement action field changes within Quick Edit.
	 *
	 * @since 0.1.0
	 */
	$(document).on('change', 'select[name="restrictly_enforcement_action"]', function () {
		var action = $(this).val();
		var container = $(this).closest('.inline-edit-col');
		toggleEnforcementFields(action, container);
	});
});

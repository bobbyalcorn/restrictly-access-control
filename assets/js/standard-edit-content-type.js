/**
 * Full Edit functionality for Restrictly.
 *
 * Handles dynamic field visibility on the standard edit screen.
 * - Toggles enforcement fields (Custom Message / Custom URL)
 * - Adjusts role and enforcement field visibility based on login status
 * - Ensures proper visibility on page load and field change events
 *
 * @package Restrictly
 *
 * @since 0.1.0
 */

jQuery(document).ready(function ($) {
	/**
	 * Toggle visibility of enforcement action fields (Custom Message, Custom URL)
	 * within the standard edit context.
	 *
	 * @param {string} action    The selected enforcement action.
	 * @param {object} container The container element (a jQuery object).
	 * @return {void}
	 *
	 * @since 0.1.0
	 */
	function toggleEnforcementFields(action, container) {
		var $customMessageContainer = container.find(
			'.restrictly_custom_message_container, #restrictly_custom_message_container'
		);
		var $customForwardContainer = container.find(
			'.restrictly_custom_forward_url_container, #restrictly_custom_forward_url_container'
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
	 * Toggle visibility of role and enforcement action fields based on login status.
	 *
	 * @param {string} loginStatus The selected login status.
	 * @param {object} container   The container element (a jQuery object).
	 * @return {void}
	 *
	 * @since 0.1.0
	 */
	function toggleLoginDependentFields(loginStatus, container) {
		var $rolesTable = container.find('#restrictly_page_access_by_role');
		var $enforcementTable = container.find('table:has(#restrictly_enforcement_action)');
		var $customMessageContainer = container.find('#restrictly_custom_message_container');
		var $customForwardContainer = container.find('#restrictly_custom_forward_url_container');

		if (loginStatus !== 'everyone') {
			$enforcementTable.show();

			// Show roles only for logged-in users.
			if (loginStatus === 'logged_in_users') {
				$rolesTable.show();
			} else {
				$rolesTable.hide();
			}

			// Determine which enforcement fields to show.
			var action = $('#restrictly_enforcement_action').val();
			toggleEnforcementFields(action, container);
		} else {
			$rolesTable.hide();
			$enforcementTable.hide();
			$customMessageContainer.hide();
			$customForwardContainer.hide();
		}
	}

	/**
	 * Handle login status field changes within the standard edit screen.
	 *
	 * @since 0.1.0
	 */
	$(document).on('change', '#restrictly_page_access_by_login_status', function () {
		var loginStatus = $(this).val();
		var container = $(this).closest('.restrictly-inside');
		toggleLoginDependentFields(loginStatus, container);
	});

	/**
	 * Handle enforcement action field changes within the standard edit screen.
	 *
	 * @since 0.1.0
	 */
	$(document).on('change', '#restrictly_enforcement_action', function () {
		var action = $(this).val();
		var container = $(this).closest('.restrictly-inside');
		toggleEnforcementFields(action, container);
	});

	/**
	 * Initialize field visibility on page load.
	 *
	 * Ensures the correct fields are visible when editing
	 * an existing post or page.
	 *
	 * @since 0.1.0
	 */
	$(window).on('load', function () {
		var $loginStatus = $('#restrictly_page_access_by_login_status');

		if ($loginStatus.length) {
			var container = $loginStatus.closest('.restrictly-inside');
			toggleLoginDependentFields($loginStatus.val(), container);
		}
	});
});

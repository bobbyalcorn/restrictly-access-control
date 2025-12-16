/**
 * Bulk Edit functionality for Restrictly.
 *
 * Handles toggling enforcement action fields in the Bulk Edit panel and applies bulk changes.
 *
 * @package Restrictly
 *
 * @since 0.1.0
 */

const { __ } = wp.i18n;

jQuery(document).ready(function ($) {
	/**
	 * Toggle enforcement action fields for Bulk Edit.
	 *
	 * @param {string} action    The selected enforcement action.
	 * @param {object} container The bulk edit container.
	 * @return {void}
	 *
	 * @since 0.1.0
	 */
	function toggleEnforcementFields(action, container) {
		let $customMessageContainer = container.find('#restrictly_custom_message_container_bulkedit');
		let $customForwardContainer = container.find(
			'#restrictly_custom_forward_url_container_bulkedit'
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
	 * Toggle fields based on login status for Bulk Edit.
	 *
	 * @param {string} loginStatus The selected login status.
	 * @param {object} container   The bulk edit container.
	 * @return {void}
	 *
	 * @since 0.1.0
	 */
	function toggleLoginStatusFields(loginStatus, container) {
		let $roleSelectionContainer = container.find('.restrictly_role_selection_bulkedit');
		let $enforcementContainer = container.find('.restrictly_enforcement_bulkedit');
		let $customMessageContainer = container.find('#restrictly_custom_message_container_bulkedit');
		let $customForwardContainer = container.find(
			'#restrictly_custom_forward_url_container_bulkedit'
		);

		if (loginStatus !== 'everyone') {
			$enforcementContainer.show();

			if (loginStatus === 'logged_in_users') {
				$roleSelectionContainer.show();
			} else {
				$roleSelectionContainer.hide();
			}

			// Check enforcement action to determine visibility.
			var action = container.find('select[name="bulk_restrictly_enforcement_action"]').val();
			toggleEnforcementFields(action, container);
		} else {
			$roleSelectionContainer.hide();
			$enforcementContainer.hide();
			$customMessageContainer.hide();
			$customForwardContainer.hide();
		}
	}

	// ==========================
	// Field Toggle Handlers
	// ==========================

	/**
	 * Handle login status change events in Bulk Edit.
	 *
	 * @since 0.1.0
	 */
	$(document).on(
		'change',
		'select[name="bulk_restrictly_page_access_by_login_status"]',
		function () {
			let loginStatus = $(this).val();
			let container = $(this).closest('.restrictly-bulk-edit-panel');
			toggleLoginStatusFields(loginStatus, container);
		}
	);

	/**
	 * Sends collected Bulk Edit data to the backend via AJAX.
	 *
	 * @since 0.1.0
	 */
	$(document).on('change', 'select[name="bulk_restrictly_enforcement_action"]', function () {
		let action = $(this).val();
		let container = $(this).closest('.restrictly-bulk-edit-panel');
		toggleEnforcementFields(action, container);
	});

	// Initialize the fields on page load - use the document ready event.
	$(document).ready(function () {
		// Hide custom message and URL containers by default.
		$('#restrictly_custom_message_container_bulkedit').hide();
		$('#restrictly_custom_forward_url_container_bulkedit').hide();
	});

	// Bulk Edit: When the "Bulk Edit" button is clicked.
	$('#bulk_edit').on('click', function () {
		let postIds = [];

		// Collect the selected post IDs.
		$('.check-column input[type="checkbox"]:checked').each(function () {
			let postId = $(this).val();
			if ($.isNumeric(postId)) {
				postIds.push(parseInt(postId));
			}
		});

		if (postIds.length === 0) {
			return;
		}

		// Gather selected field values.
		let loginStatus = $('select[name="bulk_restrictly_page_access_by_login_status"]').val();
		let roles = [];
		$('input[name="bulk_restrictly_page_access_by_role[]"]:checked').each(function () {
			roles.push($(this).val());
		});
		let enforcementAction = $('select[name="bulk_restrictly_enforcement_action"]').val();
		let customMessage = $('textarea[name="bulk_restrictly_custom_message"]').val();
		let customForwardUrl = $('input[name="bulk_restrictly_custom_forward_url"]').val();

		let data = {
			action: 'restrictly_bulk_edit',
			post_ids: postIds,
			restrictly_page_access_by_login_status: loginStatus || '',
			restrictly_page_access_by_role: roles.length ? roles : '',
			restrictly_enforcement_action: enforcementAction || '',
			restrictly_custom_message: customMessage || '',
			restrictly_custom_forward_url: customForwardUrl || '',
			security: restrictlyAdmin.bulkEditNonce
		};

		// Send the bulk edit data via AJAX.
		$.post(restrictlyAdmin.ajaxUrl, data, function (response) {
			if (response.success) {
				location.reload();
			} else {
				alert(__('Bulk edit failed:', 'restrictly-access-control') + ' ' + response.data.message);
			}
		});
	});
});

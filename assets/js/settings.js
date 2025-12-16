/**
 * Restrictly Settings Page JavaScript
 *
 * Handles the visibility of enforcement action fields on the settings page.
 * - Toggles display of "Custom Message" and "Custom URL" fields based on selection.
 * - Ensures correct field visibility on page load.
 * - Uses event delegation for reliable field updates.
 *
 * @package Restrictly
 *
 * @since 0.1.0
 */

jQuery(document).ready(function ($) {
	// Cache selectors for performance.
	const $restrictlyDefaultAction = $('select[name="restrictly_default_action"]');
	const $messageField = $('textarea[name="restrictly_default_message"]');
	const $forwardUrlField = $('input[name="restrictly_default_forward_url"]');

	/**
	 * Prevent navigation for disabled tabs on the settings page.
	 *
	 * Ensures that any `.nav-tab.disabled` elements cannot trigger
	 * page reloads or hash navigation when clicked.
	 *
	 * @return {void}
	 *
	 * @since 0.1.0
	 */
	const disabledTabs = document.querySelectorAll('.nav-tab.disabled');

	if (disabledTabs.length > 0) {
		disabledTabs.forEach((tab) => {
			tab.addEventListener('click', (event) => {
				event.preventDefault();
			});
		});
	}

	/**
	 * Handle tab switching for Restrictly settings sections.
	 *
	 * @since 0.1.0
	 */
	$('.nav-tab').on('click', function (e) {
		e.preventDefault();

		// Update active tab.
		$('.nav-tab').removeClass('nav-tab-active');
		$(this).addClass('nav-tab-active');

		// Show corresponding content.
		$('.restrictly-tab-content').removeClass('active');
		$($(this).attr('href')).addClass('active');
	});

	/**
	 * Toggles visibility of Custom Message and Custom URL fields
	 * based on the selected default enforcement action.
	 *
	 * @return {void}
	 *
	 * @since 0.1.0
	 */
	function toggleEnforcementFields() {
		const action = $restrictlyDefaultAction.val();

		// Show or hide fields based on the selected action.
		if (action === 'custom_message') {
			$forwardUrlField.val('').closest('tr').hide();
			$messageField.closest('tr').show();
		} else if (action === 'custom_url') {
			$messageField.val('').closest('tr').hide();
			$forwardUrlField.closest('tr').show();
		} else {
			// Hide both when using default or other actions.
			$messageField.closest('tr').hide();
			$forwardUrlField.closest('tr').hide();
		}
	}

	/**
	 * Initialize the correct field visibility on page load.
	 *
	 * @since 0.1.0
	 */
	toggleEnforcementFields();

	/**
	 * Attach change listener for the enforcement action selector.
	 *
	 * @since 0.1.0
	 */
	$restrictlyDefaultAction.on('change', toggleEnforcementFields);
});

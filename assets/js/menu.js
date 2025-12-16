/* jshint esversion: 11 */

/**
 * Restrictly™ Menu Page Enhancements
 *
 * Adds resilient real-time highlighting for Restrictly™ menu mismatches
 * within the WordPress Menu Editor screen.
 *
 * Features:
 * - Instantly adds highlight classes to <li> elements.
 * - Persists through full DOM replacement after AJAX saves.
 * - Uses a self-rebinding MutationObserver with ajaxComplete fallback.
 *
 * @package Restrictly
 * @since   0.1.0
 */

jQuery(document).ready(function ($) {
	// ─────────────────────────────────────────────
	// Apply Restrictly™ highlights to all menu items.
	// ─────────────────────────────────────────────
	function applyRestrictlyHighlights() {
		$('.menu-item-settings').each(function () {
			const $settings = $(this);
			const $li = $settings.closest('li.menu-item');

			// Reset before rescanning.
			$settings.removeClass('has-restrictly-mismatch has-restrictly-match has-restrictly-neutral');
			$li.removeClass('restrictly-mismatch restrictly-match restrictly-neutral');

			// Determine highlight type.
			if (
				$settings.find('.restrictly-mismatch-field').length > 0 ||
				$settings.find('.restrictly-mismatch-warning').length > 0
			) {
				// Mismatch indicator.
				$settings.addClass('has-restrictly-mismatch');
				$li.addClass('restrictly-mismatch');
			} else if (
				$settings.find('.restrictly-match-field').length > 0 ||
				$settings.find('.restrictly-match-visibility').length > 0
			) {
				// Valid match indicator.
				$settings.addClass('has-restrictly-match');
				$li.addClass('restrictly-match');
			} else {
				// Default to neutral.
				$settings.addClass('has-restrictly-neutral');
				$li.addClass('restrictly-neutral');
			}
		});
	}

	let observer = null;

	// ─────────────────────────────────────────────
	// Attach (or reattach) MutationObserver to #menu-to-edit.
	// ─────────────────────────────────────────────
	function attachMenuObserver() {
		// Disconnect old observer if present.
		if (observer instanceof MutationObserver) {
			observer.disconnect();
		}

		const menuContainer = document.getElementById('menu-to-edit');
		if (!menuContainer) return;

		observer = new MutationObserver(() => {
			clearTimeout(window._restrictlyHighlightDelay);
			window._restrictlyHighlightDelay = setTimeout(applyRestrictlyHighlights, 150);
		});

		observer.observe(menuContainer, {
			childList: true,
			subtree: true
		});
	}

	// ─────────────────────────────────────────────
	// Initial render and observer setup.
	// ─────────────────────────────────────────────
	applyRestrictlyHighlights();
	attachMenuObserver();

	// ─────────────────────────────────────────────
	// Handle WordPress AJAX-based menu updates.
	// ─────────────────────────────────────────────
	$(document).ajaxComplete(function (_evt, _xhr, settings) {
		const dataStr =
			typeof settings.data === 'string' ? settings.data : $.param(settings.data || {});

		if (
			dataStr.indexOf('action=add-menu-item') !== -1 ||
			dataStr.indexOf('action=update-nav-menu') !== -1 ||
			dataStr.indexOf('action=save-menu') !== -1
		) {
			// Wait for new DOM to render, then rescan and rebind.
			setTimeout(() => {
				applyRestrictlyHighlights();
				attachMenuObserver();
			}, 250);
		}
	});

	// ─────────────────────────────────────────────
	// Catch manual menu events (safety net).
	// ─────────────────────────────────────────────
	$(document).on('wpNavMenuItemAdded wpNavMenuItemUpdated sortstop', function () {
		setTimeout(applyRestrictlyHighlights, 120);
	});

	/*******************************************************************************
	 * Restrictly™ Menu Visibility UX Standardization
	 * Hides/Shows the Allowed Roles section based on visibility selection.
	 ******************************************************************************/

	jQuery(document).on('change', 'select[id^="restrictly_menu_visibility_"]', function () {
		const $select = jQuery(this);
		const visibility = $select.val();
		const $panel = $select.closest('.restrictly-menu-panel');
		const $rolesWrapper = $panel.find('p:has(input[type="checkbox"])'); // Allowed Roles block

		// Everyone → Hide roles
		if (visibility === 'everyone') {
			$rolesWrapper.hide();
		}

		// Logged-in Users → Show roles
		else if (visibility === 'logged_in_users') {
			$rolesWrapper.show();
		}

		// Logged-out Users → Hide roles
		else if (visibility === 'logged_out_users') {
			$rolesWrapper.hide();
		}
	});

	// On page load (menus page load + ajax reload)
	jQuery(function () {
		jQuery('select[id^="restrictly_menu_visibility_"]').each(function () {
			const visibility = jQuery(this).val();
			const $panel = jQuery(this).closest('.restrictly-menu-panel');
			const $rolesWrapper = $panel.find('p:has(input[type="checkbox"])');

			if (visibility === 'logged_in_users') {
				$rolesWrapper.show();
			} else {
				$rolesWrapper.hide();
			}
		});
	});
});

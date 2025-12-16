/* jshint esversion: 11 */

/**
 * Adds Restrictly™ visibility controls to the Site Editor’s Navigation management screen.
 *
 * Extends the “Navigation” screen in the WordPress Site Editor, allowing
 * visibility and role restrictions to be applied directly to `wp_navigation` posts
 * (Appearance → Editor → Navigation).
 *
 * Dynamically loads available roles from RoleHelper::get_available_roles()
 * (localized into `window.RestrictlyBlockData.roles`).
 *
 * @package Restrictly
 *
 * @since   0.1.0
 */

(function (wp) {
	// Abort if WordPress core editSite context is unavailable or already loaded.
	if (!wp || !wp.editSite) return;
	if (window.RestrictlyNavScreenLoaded) return;
	window.RestrictlyNavScreenLoaded = true;

	// ─────────────────────────────────────────────
	// Load components safely across Site/Post/Block editors.
	// ─────────────────────────────────────────────
	const { PluginSidebar, PluginSidebarMoreMenuItem } =
		wp.plugins || wp.editPost?.components || wp.editSite?.components || {};
	const { registerPlugin } = wp.plugins;
	const { PanelBody, SelectControl, CheckboxControl } = wp.components;
	const { Fragment, createElement: el, useState, useEffect } = wp.element;
	const { dispatch, select } = wp.data;

	// Define Restrictly meta keys stored on wp_navigation posts.
	const metaKeys = {
		visibility: '_restrictly_visibility',
		roles: '_restrictly_roles'
	};

	// Retrieve available roles from localized Restrictly data.
	let roleOptions = [];

	if (window.RestrictlyBlockData && Array.isArray(window.RestrictlyBlockData.roles)) {
		roleOptions = window.RestrictlyBlockData.roles;
	}

	// ─────────────────────────────────────────────
	// Sidebar component for Restrictly™ Navigation visibility.
	// ─────────────────────────────────────────────
	const RestrictlyNavSidebar = () => {
		const editedPostType = select('core/editor')?.getCurrentPostType?.();
		if (editedPostType !== 'wp_navigation') return null;

		const meta = select('core/editor').getEditedPostAttribute('meta') || {};
		const [visibility, setVisibility] = useState(meta[metaKeys.visibility] || 'everyone');
		const [roles, setRoles] = useState(meta[metaKeys.roles] || []);

		// Sync local state whenever post meta changes.
		useEffect(() => {
			setVisibility(meta[metaKeys.visibility] || 'everyone');
			setRoles(meta[metaKeys.roles] || []);
		}, [meta]);

		// Update post meta with new values.
		const updateMeta = (key, value) => {
			dispatch('core/editor').editPost({
				meta: { ...meta, [key]: value }
			});
		};

		// Toggle a role checkbox.
		const toggleRole = (role, checked) => {
			const updated = new Set(roles);

			if (checked) {
				updated.add(role);
			} else {
				updated.delete(role);
			}

			const arr = Array.from(updated);
			setRoles(arr);
			updateMeta(metaKeys.roles, arr);
		};

		// Render Restrictly™ sidebar UI.
		return el(
			Fragment,
			null,
			el(PluginSidebarMoreMenuItem, { target: 'restrictly-nav-sidebar' }, 'Restrictly™ Visibility'),
			el(
				PluginSidebar,
				{
					name: 'restrictly-nav-sidebar',
					title: 'Restrictly™ Visibility',
					icon: 'visibility'
				},
				el(
					PanelBody,
					{ title: 'Visibility Settings', initialOpen: true },
					el(SelectControl, {
						label: 'Show this navigation to:',
						value: visibility,
						options: [
							{ label: 'Everyone', value: 'everyone' },
							{ label: 'Logged-in Users', value: 'logged_in' },
							{ label: 'Logged-out Users', value: 'logged_out' }
						],
						onChange: (val) => {
							setVisibility(val);
							updateMeta(metaKeys.visibility, val);
							if (val !== 'logged_in') {
								setRoles([]);
								updateMeta(metaKeys.roles, []);
							}
						}
					}),
					visibility === 'logged_in' &&
						roleOptions.length > 0 &&
						el(
							Fragment,
							null,
							roleOptions.map((role) =>
								el(CheckboxControl, {
									key: role.value,
									label: role.label,
									checked: roles.includes(role.value),
									onChange: (checked) => toggleRole(role.value, checked),
									__nextHasNoMarginBottom: true
								})
							)
						)
				)
			)
		);
	};

	// ─────────────────────────────────────────────
	// Register the Restrictly™ Navigation sidebar plugin.
	// ─────────────────────────────────────────────
	registerPlugin('restrictly-nav-screen-controls', {
		render: RestrictlyNavSidebar,
		icon: 'visibility'
	});
})(window.wp);

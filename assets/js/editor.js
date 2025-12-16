/* jshint esversion: 11 */

/**
 * Adds Restrictly™ navigation-level visibility controls to the WordPress Site Editor.
 *
 * Extends Gutenberg’s Navigation blocks with Restrictly visibility attributes
 * and a sidebar panel that controls who can see each navigation item.
 *
 * Mirrors block-visibility.js but is scoped to navigation-related blocks only.
 * Dynamically lists available roles from RoleHelper::get_available_roles()
 * (localized into `window.RestrictlyBlockData.roles`).
 *
 * @package Restrictly
 *
 * @since   0.1.0
 */

(function (wp) {
	// Prevent the generic block script from executing on navigation blocks.
	window.RestrictlyNavScriptActive = true;

	// Import WordPress dependencies.
	const { addFilter } = wp.hooks;
	const { createHigherOrderComponent } = wp.compose;
	const { createElement, Fragment } = wp.element;
	const { InspectorControls } = wp.blockEditor || wp.editor;
	const { PanelBody, SelectControl, CheckboxControl } = wp.components;

	// Targeted navigation block types.
	const TARGET_BLOCKS = [
		'core/navigation',
		'core/navigation-link',
		'core/navigation-submenu',
		'core/page-list',
		'core/page-list-item'
	];

	// Prevent duplicate execution.
	if (window.RestrictlyNavigationScriptLoaded) return;
	window.RestrictlyNavigationScriptLoaded = true;

	// ─────────────────────────────────────────────
	// Register Restrictly attributes for navigation blocks.
	// ─────────────────────────────────────────────
	addFilter(
		'blocks.registerBlockType',
		'restrictly/navigation/add-visibility-attributes',
		(settings, name) => {
			if (TARGET_BLOCKS.includes(name)) {
				settings.attributes = Object.assign(settings.attributes || {}, {
					restrictlyVisibility: { type: 'string', default: 'everyone' },
					restrictlyRoles: { type: 'array', default: [] }
				});
			}
			return settings;
		}
	);

	// ─────────────────────────────────────────────
	// Inject Restrictly Visibility panel into Inspector sidebar.
	// ─────────────────────────────────────────────
	const withVisibilityControl = createHigherOrderComponent((BlockEdit) => {
		return (props) => {
			if (!TARGET_BLOCKS.includes(props.name)) {
				return createElement(BlockEdit, props);
			}

			const { attributes, setAttributes } = props;
			const visibility = attributes.restrictlyVisibility || 'everyone';
			const selectedRoles = attributes.restrictlyRoles || [];

			const baseOptions = [
				{ label: 'Everyone', value: 'everyone' },
				{ label: 'Logged-in Users', value: 'logged_in' },
				{ label: 'Logged-out Users', value: 'logged_out' }
			];

			const hasRoleData =
				window.RestrictlyBlockData && Array.isArray(window.RestrictlyBlockData.roles);

			const roleOptions = hasRoleData ? window.RestrictlyBlockData.roles : [];

			let roleCheckboxes = null;

			if (visibility === 'logged_in' && roleOptions.length) {
				roleCheckboxes = roleOptions.map((role) =>
					createElement(CheckboxControl, {
						key: role.value,
						label: role.label,
						checked: selectedRoles.includes(role.value),
						onChange: (checked) => {
							const updated = new Set(selectedRoles);
							if (checked) updated.add(role.value);
							else updated.delete(role.value);
							setAttributes({ restrictlyRoles: Array.from(updated) });
						},
						__nextHasNoMarginBottom: true
					})
				);
			}

			return createElement(
				Fragment,
				null,
				createElement(BlockEdit, props),
				createElement(
					InspectorControls,
					{ key: 'restrictly-inspector-controls' },
					createElement(
						PanelBody,
						{ title: 'Restrictly Visibility', initialOpen: false },
						createElement(SelectControl, {
							label: 'Show this item to:',
							value: visibility,
							options: baseOptions,
							onChange: (val) => {
								setAttributes({ restrictlyVisibility: val });
								if (val !== 'logged_in') setAttributes({ restrictlyRoles: [] });
							},
							__next40pxDefaultSize: true,
							__nextHasNoMarginBottom: true
						}),
						roleCheckboxes
					)
				)
			);
		};
	}, 'withRestrictlyNavigationControl');

	// ─────────────────────────────────────────────
	// Attach BlockEdit filter once editor context is initialized.
	// ─────────────────────────────────────────────
	if (!window.RestrictlyNavFilterInit && wp.data) {
		const select = wp.data.select('core/editor');
		const unsubscribe = wp.data.subscribe(() => {
			let postType = null;

			if (select && typeof select.getCurrentPostType === 'function') {
				postType = select.getCurrentPostType();
			}

			// Once any editor context is ready, attach exactly one filter.
			if (postType || window.wp.editSite) {
				if (window.RestrictlyNavFilterInit) return;
				window.RestrictlyNavFilterInit = true;

				addFilter(
					'editor.BlockEdit',
					'restrictly/navigation/with-visibility-control',
					withVisibilityControl
				);

				if (typeof unsubscribe === 'function') {
					unsubscribe();
				}
			}
		});
	}

	// ─────────────────────────────────────────────
	// Add Restrictly™ visibility indicator badge.
	// ─────────────────────────────────────────────
	(() => {
		const el = wp.element.createElement;

		wp.hooks.addFilter(
			'editor.BlockListBlock',
			'restrictly/navigation/visibility-indicator',
			(BlockListBlock) =>
				function (props) {
					// Skip if nav pills disabled or block not targeted.
					if (
						!window.RestrictlySettings ||
						(!window.RestrictlySettings.showNavPills &&
							props.name &&
							props.name.startsWith('core/navigation'))
					) {
						return el(BlockListBlock, props);
					}

					if (!TARGET_BLOCKS.includes(props.name)) {
						return el(BlockListBlock, props);
					}

					const vis = props.attributes.restrictlyVisibility || 'everyone';
					let roles = [];

					if (Array.isArray(props.attributes.restrictlyRoles)) {
						roles = props.attributes.restrictlyRoles;
					}

					let label = 'Visible to: Everyone';
					let short = 'Public';

					if (vis === 'logged_in') {
						label = 'Visible to: Logged-in Users';
						short = 'Logged-in';
						if (roles.length) {
							const nice = roles.map((r) => r.replace(/^role_/, '')).join(', ');
							label += ` (${nice})`;
							short += ' • ' + nice;
						}
					} else if (vis === 'logged_out') {
						label = 'Visible to: Logged-out Users';
						short = 'Logged-out';
					} else if (vis && vis.startsWith('role_')) {
						const one = vis.replace(/^role_/, '');
						label = `Visible to: ${one}`;
						short = one;
					}

					const cls =
						(props.className || '') +
						(vis !== 'everyone' ? ' strictly-has-restrictly-vis' : '') +
						(vis ? ` strictly-vis-${vis}` : '');

					const mergedWrapper = Object.assign({}, props.wrapperProps || {}, {
						className: [(props.wrapperProps && props.wrapperProps.className) || '', cls]
							.join(' ')
							.trim(),
						'data-restrictly-short': short,
						'data-restrictly-label': label
					});

					const vnode = el(BlockListBlock, { ...props, wrapperProps: mergedWrapper });

					setTimeout(() => {
						const root = document.querySelector(`[data-block="${props.clientId}"]`);
						if (root && vis !== 'everyone') {
							root.classList.add('strictly-has-restrictly-vis', `strictly-vis-${vis}`);
							root.setAttribute('data-restrictly-short', short);
							root.setAttribute('data-restrictly-label', label);
							root.setAttribute('title', label);
						}
					}, 0);

					return vnode;
				}
		);
	})();

	// Corresponding styles: assets/css/admin-block-visibility.css
})(window.wp);

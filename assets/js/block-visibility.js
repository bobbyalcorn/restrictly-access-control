/* jshint esversion: 11 */

/**
 * Restrictly™ Block Visibility (Core Blocks Only).
 *
 * Adds block-level visibility controls to core Gutenberg blocks.
 * Navigation blocks are handled separately.
 *
 * Free version intentionally ignores:
 * - WooCommerce blocks.
 * - Third-party plugin blocks.
 *
 * @package Restrictly
 * @since 0.1.0
 */

(function (wp) {
	// Abort if navigation script is active (handled elsewhere).
	if (window.RestrictlyNavScriptActive) return;

	const { addFilter } = wp.hooks;
	const { createHigherOrderComponent } = wp.compose;
	const { createElement, Fragment } = wp.element;
	const { InspectorControls } = wp.blockEditor;
	const { PanelBody, SelectControl, CheckboxControl } = wp.components;

	// Navigation blocks excluded from this script.
	const RESTRICTLY_NAV_BLOCKS = [
		'core/navigation',
		'core/navigation-link',
		'core/navigation-submenu',
		'core/page-list',
		'core/page-list-item'
	];

	// ---------------------------------------------------------------------
	// 1. Register Restrictly attributes (core blocks only).
	// ---------------------------------------------------------------------
	addFilter(
		'blocks.registerBlockType',
		'restrictly/add-visibility-attributes',
		(settings, name) => {
			// Ignore non-core blocks.
			if (!name || !name.startsWith('core/')) {
				return settings;
			}

			// Ignore navigation blocks.
			if (RESTRICTLY_NAV_BLOCKS.includes(name)) {
				return settings;
			}

			settings.attributes = Object.assign(settings.attributes || {}, {
				restrictlyVisibility: {
					type: 'string',
					default: 'everyone'
				},
				restrictlyRoles: {
					type: 'array',
					default: []
				}
			});

			return settings;
		}
	);

	// ---------------------------------------------------------------------
	// 2. Inspector controls (sidebar UI).
	// ---------------------------------------------------------------------
	const withVisibilityControl = createHigherOrderComponent((BlockEdit) => {
		return (props) => {
			// Core blocks only.
			if (!props.name || !props.name.startsWith('core/')) {
				return createElement(BlockEdit, props);
			}

			// Skip navigation blocks.
			if (RESTRICTLY_NAV_BLOCKS.includes(props.name)) {
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

			let roleOptions = [];

			if (window.RestrictlyBlockData && Array.isArray(window.RestrictlyBlockData.roles)) {
				roleOptions = window.RestrictlyBlockData.roles;
			}

			let roleCheckboxes = null;

			if (visibility === 'logged_in' && roleOptions.length) {
				roleCheckboxes = roleOptions.map((role) =>
					createElement(CheckboxControl, {
						key: role.value,
						label: role.label,
						checked: selectedRoles.includes(role.value),
						onChange: (checked) => {
							const updated = new Set(selectedRoles);
							if (checked) {
								updated.add(role.value);
							} else {
								updated.delete(role.value);
							}
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
					null,
					createElement(
						PanelBody,
						{ title: 'Restrictly Visibility', initialOpen: false },
						createElement(SelectControl, {
							label: 'Show this block to:',
							value: visibility,
							options: baseOptions,
							onChange: (val) => {
								setAttributes({ restrictlyVisibility: val });
								if (val !== 'logged_in') {
									setAttributes({ restrictlyRoles: [] });
								}
							},
							__next40pxDefaultSize: true,
							__nextHasNoMarginBottom: true
						}),
						roleCheckboxes
					)
				)
			);
		};
	}, 'withRestrictlyVisibilityControl');

	addFilter('editor.BlockEdit', 'restrictly/with-visibility-control', withVisibilityControl);
})(window.wp);

// ---------------------------------------------------------------------
// 3. Non-intrusive visibility indicator (editor only).
// ---------------------------------------------------------------------
(function (wp) {
	const el = wp.element.createElement;

	const RESTRICTLY_NAV_BLOCKS = [
		'core/navigation',
		'core/navigation-link',
		'core/navigation-submenu',
		'core/page-list',
		'core/page-list-item'
	];

	// Detect FSE context (site editor vs post editor).
	const isFSEContext =
		window.location.pathname.includes('site-editor.php') ||
		(document.body && document.body.classList.contains('site-editor-php'));

	wp.hooks.addFilter(
		'editor.BlockListBlock',
		'restrictly/visibility-indicator',
		(BlockListBlock) =>
			function (props) {
				// Exit if settings are unavailable or pills are disabled in FSE.
				if (
					!window.RestrictlySettings ||
					(!window.RestrictlySettings.showNavPills && isFSEContext)
				) {
					return el(BlockListBlock, props);
				}

				// Core blocks only.
				if (!props.name || !props.name.startsWith('core/')) {
					return el(BlockListBlock, props);
				}

				// Skip navigation blocks.
				if (RESTRICTLY_NAV_BLOCKS.includes(props.name)) {
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

				const vnode = el(BlockListBlock, {
					...props,
					wrapperProps: mergedWrapper
				});

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
})(window.wp);

<?php
/**
 * Handles Restrictly™ visibility for Full Site Editor (FSE) Navigation Menus.
 *
 * Adds Restrictly™ role and visibility enforcement to the Full Site Editor's
 * Navigation system, ensuring restricted items are hidden across both
 * frontend rendering and editor experiences.
 *
 * @package Restrictly
 * @since   0.1.0
 */

namespace Restrictly\Core\Admin;

use Restrictly\Core\Common\Enforcement;
use Restrictly\Core\Common\Base;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Adds Restrictly™ role and visibility enforcement to FSE Navigation systems.
 *
 * @since 0.1.0
 */
class FSENavigation {

	/**
	 * Bootstraps the controller.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function init(): void {
		new self();
	}

	/**
	 * Hooks everything in.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_navigation_meta' ) );

		// Intercept navigation post data before save.
		add_filter( 'rest_pre_insert_wp_navigation', array( $this, 'filter_navigation_before_save' ), 10, 2 );

		// Core enforcement filters.
		add_filter( 'render_block_core/navigation', array( $this, 'filter_navigation_render' ), 10, 2 );
		add_filter( 'render_block', array( $this, 'intercept_fse_navigation_block' ), 9, 2 );
		add_filter( 'block_core_navigation_render_fallback', array( $this, 'filter_navigation_fallback' ), 10, 1 );

		// Page list block and page queries.
		add_filter( 'get_pages', array( $this, 'filter_auto_nav_pages' ), 10, 2 );
		add_filter( 'render_block_core/page-list', array( $this, 'filter_page_list_visibility' ), 10, 2 );

		// Site editor integration.
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_nav_screen_controls' ) );
	}

	/**
	 * Registers Restrictly™ meta fields for FSE Navigation entities.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public function register_navigation_meta(): void {
		register_post_meta(
			'wp_navigation',
			'_restrictly_visibility',
			array(
				'type'          => 'string',
				'single'        => true,
				'default'       => 'everyone',
				'show_in_rest'  => array(
					'schema' => array(
						'type'    => 'string',
						'enum'    => array( 'everyone', 'logged_in', 'logged_out' ),
						'default' => 'everyone',
					),
				),
				'auth_callback' => fn() => current_user_can( 'edit_theme_options' ),
			)
		);

		register_post_meta(
			'wp_navigation',
			'_restrictly_roles',
			array(
				'type'          => 'array',
				'single'        => true,
				'default'       => array(),
				'show_in_rest'  => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'string' ),
					),
				),
				'auth_callback' => fn() => current_user_can( 'edit_theme_options' ),
			)
		);
	}

	/**
	 * Intercepts all block renders to catch FSE navigation output.
	 *
	 * @param string $block_content The rendered block content.
	 * @param array  $block {
	 *     Optional. The full block data array.
	 *
	 *     @type string|null       $blockName   The block name, e.g. 'core/navigation'.
	 *     @type array<string,mixed> $attrs      Block attributes.
	 *     @type array<mixed>      $innerBlocks Inner block data.
	 *     @type string|null       $innerHTML   Raw inner HTML.
	 *     @type array<mixed>      $innerContent Inner block content.
	 * }
	 * @phpstan-param array{
	 *     blockName?: string|null,
	 *     attrs?: array<string, mixed>,
	 *     innerBlocks?: array<mixed>,
	 *     innerHTML?: string|null,
	 *     innerContent?: array<mixed>
	 * } $block
	 *
	 * @return string Filtered block content.
	 *
	 * @since 0.1.0
	 */
	public function intercept_fse_navigation_block( string $block_content, array $block ): string {
		if ( isset( $block['blockName'] ) && 'core/navigation' === $block['blockName'] ) {
			return $this->filter_navigation_render( $block_content, $block );
		}
		return $block_content;
	}

	/**
	 * Filters rendered navigation HTML to remove restricted items.
	 * Reads page meta first, then falls back to the wp_navigation post meta.
	 *
	 * @param string $content The rendered block HTML content.
	 * @param array  $_block {
	 *     Optional. The block data array.
	 *
	 *     @type string|null         $blockName   The block name, e.g. 'core/navigation'.
	 *     @type array<string,mixed> $attrs       Block attributes.
	 *     @type array<mixed>        $innerBlocks Inner block data.
	 *     @type string|null         $innerHTML   Raw inner HTML.
	 *     @type array<mixed>        $innerContent Inner block content.
	 * }
	 * @phpstan-param array{
	 *     blockName?: string|null,
	 *     attrs?: array<string, mixed>,
	 *     innerBlocks?: array<mixed>,
	 *     innerHTML?: string|null,
	 *     innerContent?: array<mixed>
	 * } $_block
	 *
	 * @return string Filtered navigation content.
	 *
	 * @since 0.1.0
	 */
	public function filter_navigation_render( string $content, array $_block ): string {
		unset( $_block );

		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || trim( $content ) === '' ) {
			return $content;
		}

		$libxml_previous_state = libxml_use_internal_errors( true );
		$dom                   = new \DOMDocument();

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$dom->preserveWhiteSpace = true;
		$dom->formatOutput       = false;
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		$dom->loadHTML(
			'<?xml encoding="utf-8" ?><div id="restrictly-nav-wrapper">' . $content . '</div>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $libxml_previous_state );

		$xpath = new \DOMXPath( $dom );
		$links = $xpath->query( '//a[@href]' );

		$admin_override = get_option( 'restrictly_always_allow_admins', false );
		$current_user   = wp_get_current_user();
		$is_admin_user  = $current_user && in_array( 'administrator', (array) $current_user->roles, true );

		if ( $admin_override && $is_admin_user ) {
			return $content;
		}

		if ( $links instanceof \DOMNodeList ) {
			foreach ( $links as $link ) {
				if ( ! $link instanceof \DOMElement ) {
					continue;
				}

				$href = trim( $link->getAttribute( 'href' ) );
				if ( '' === $href || '#' === $href ) {
					continue;
				}

				// Normalize relative URLs.
				if ( str_starts_with( $href, '/' ) ) {
					$href = home_url( $href );
				} elseif ( ! str_starts_with( $href, 'http' ) ) {
					$href = home_url( '/' . ltrim( $href, '/' ) );
				}

				$clean = strtok( $href, '?#' );
				if ( empty( $clean ) ) {
					continue;
				}

				$post_id = url_to_postid( $clean );

				if ( ! $post_id ) {
					$path = wp_parse_url( $clean, PHP_URL_PATH );
					if ( is_string( $path ) ) {
						$path      = trim( $path, '/' );
						$home_path = wp_parse_url( home_url(), PHP_URL_PATH );
						if ( is_string( $home_path ) && str_starts_with( $path, trim( $home_path, '/' ) ) ) {
							$path = ltrim( substr( $path, strlen( trim( $home_path, '/' ) ) ), '/' );
						}

						$page = get_page_by_path( $path, 'OBJECT', array( 'page', 'post' ) );
						if ( ! $page ) {
							$page = get_page_by_path( untrailingslashit( $path ), 'OBJECT', array( 'page', 'post' ) );
						}

						if ( $page ) {
							$post_id = (int) $page->ID;
						}
					}
				}

				if ( ! $post_id ) {
					continue;
				}

				$vis_raw   = get_post_meta( $post_id, 'restrictly_page_access_by_login_status', true );
				$roles_raw = get_post_meta( $post_id, 'restrictly_page_access_by_role', true );

				switch ( $vis_raw ) {
					case 'logged_in_users':
						$vis = 'logged_in';
						break;
					case 'logged_out_users':
						$vis = 'logged_out';
						break;
					default:
						$vis = 'everyone';
				}

				$maybe_unserialized = is_string( $roles_raw ) ? maybe_unserialize( $roles_raw ) : $roles_raw;

				$roles = is_array( $maybe_unserialized )
					? array_values( array_map( 'strval', $maybe_unserialized ) )
					: array();

				// Determine if we should hide this link.
				$hide = ! Enforcement::can_view_by_visibility( $vis, $roles );

				// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				if ( $hide ) {
					$li = $link->parentNode;
					while ( $li && 'li' !== strtolower( $li->nodeName ) ) {
						$li = $li->parentNode;
					}
					if ( $li && $li->parentNode ) {
						$li->parentNode->removeChild( $li );
					}
				}
				// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			}
		}

		$wrapper = $dom->getElementById( 'restrictly-nav-wrapper' );
		$out     = '';
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		if ( $wrapper ) {
			foreach ( $wrapper->childNodes as $child ) {
				$out .= $dom->saveHTML( $child );
			}
		}
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		if ( $out ) {
			return $out;
		}

		return $content;
	}

	/**
	 * Handles WordPress's fallback navigation structure (6.5+ safe).
	 *
	 * Accepts either a string of HTML or an array of block data and filters
	 * out restricted navigation items before the fallback is rendered.
	 *
	 * @param string|array $fallback {
	 *     Optional. The fallback navigation structure.
	 *
	 *     @type int|string $id   The post or page ID.
	 *     @type string     $kind The kind of navigation item (e.g. 'page').
	 * }
	 * @phpstan-param string|array<int, array{
	 *     id?: int|string,
	 *     kind?: string
	 * }&array<string, mixed>> $fallback
	 *
	 * @return string|array<int, array{
	 *     id?: int|string,
	 *     kind?: string
	 * }&array<string, mixed>> Filtered fallback output.
	 *
	 * @since 0.1.0
	 */
	public function filter_navigation_fallback( string|array $fallback ): string|array {
		if ( is_string( $fallback ) ) {
			// String-based fallback (raw HTML output).
			return $this->filter_navigation_render( $fallback, array() );
		}

		// Array-based fallback (structured block data).
		foreach ( $fallback as $index => $item ) {
			if ( ! is_array( $item ) || ! isset( $item['id'], $item['kind'] ) || 'page' !== $item['kind'] ) {
				continue;
			}

			$post_id = (int) $item['id'];

			$vis_raw = get_post_meta( $post_id, '_restrictly_visibility', true );
			$vis     = $vis_raw ? (string) $vis_raw : 'everyone';
			$roles   = (array) get_post_meta( $post_id, '_restrictly_roles', true );

			if ( ! Enforcement::can_view_by_visibility( $vis, $roles ) ) {
				unset( $fallback[ $index ] );
			}
		}

		return array_values( $fallback );
	}

	/**
	 * Filters page list block output for restricted pages.
	 *
	 * @param string               $content The rendered block content.
	 * @param array<string, mixed> $_block  The block data array.
	 *
	 * @return string Filtered content.
	 *
	 * @since 0.1.0
	 */
	public function filter_page_list_visibility( string $content, array $_block ): string {
		unset( $_block );

		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || empty( $content ) ) {
			return $content;
		}

		$libxml_previous_state = libxml_use_internal_errors( true );
		$dom                   = new \DOMDocument();
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $content );
		libxml_clear_errors();
		libxml_use_internal_errors( $libxml_previous_state );

		foreach ( iterator_to_array( $dom->getElementsByTagName( 'a' ) ) as $link ) {
			$post_id = url_to_postid( $link->getAttribute( 'href' ) );
			if ( ! $post_id ) {
				continue;
			}

			$vis_raw = get_post_meta( $post_id, '_restrictly_visibility', true );

			if ( $vis_raw ) {
				$vis = $vis_raw;
			} else {
				$vis = 'everyone';
			}

			$roles = (array) get_post_meta( $post_id, '_restrictly_roles', true );

			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			if ( ! Enforcement::can_view_by_visibility( $vis, $roles ) ) {
				$li = $link->parentNode;
				if ( $li && $li->parentNode ) {
					$li->parentNode->removeChild( $li );
				}
			}
			// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}

		$ul = $dom->getElementsByTagName( 'ul' )->item( 0 );
		if ( $ul ) {
			$html = $dom->saveHTML( $ul );
			if ( false !== $html ) {
				return $html;
			}
		}

		return $content;
	}

	/**
	 * Filters pages returned by get_pages() to remove restricted ones.
	 *
	 * @param array<int, \WP_Post> $pages Array of page objects.
	 * @param array<string, mixed> $_args Query args.
	 *
	 * @return array<int, \WP_Post> Filtered pages.
	 *
	 * @since 0.1.0
	 */
	public function filter_auto_nav_pages( array $pages, array $_args ): array {
		unset( $_args );

		// Do not filter inside admin or REST requests.
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $pages;
		}

		return array_values(
			array_filter(
				$pages,
				function ( $page ) {
					$vis_raw = get_post_meta( $page->ID, '_restrictly_visibility', true );
					$vis     = $vis_raw ? (string) $vis_raw : 'everyone';

					$roles = (array) get_post_meta( $page->ID, '_restrictly_roles', true );

					// Use unified enforcement visibility check.
					return Enforcement::can_view_by_visibility( $vis, $roles );
				}
			)
		);
	}

	/**
	 * Enqueues Restrictly™ sidebar controls for Navigation editing in Site Editor.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public function enqueue_nav_screen_controls(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'site-editor' !== $screen->base ) {
			return;
		}

		$asset_url = Base::plugin_url() . 'assets/js/nav-screen-controls.js';
		wp_enqueue_script(
			'restrictly-nav-screen-controls',
			$asset_url,
			array( 'wp-plugins', 'wp-edit-site', 'wp-element', 'wp-components', 'wp-data', 'wp-core-data' ),
			'0.1.0',
			true
		);

		if ( function_exists( 'wp_roles' ) ) {
			wp_localize_script(
				'restrictly-nav-screen-controls',
				'RestrictlyBlockData',
				array(
					'roles' => array_map(
						static fn( $key, $label ) => array(
							'value' => $key,
							'label' => $label['name'],
						),
						array_keys( wp_roles()->roles ),
						wp_roles()->roles
					),
				)
			);
		}
	}

	/**
	 * Filters navigation content before it's saved.
	 * Removes restricted pages from 'Add all pages' results before storage.
	 *
	 * @param \stdClass        $prepared_post The prepared post object.
	 * @param \WP_REST_Request $request       The REST request object.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 *
	 * @return \stdClass Modified post object.
	 *
	 * @since 0.1.0
	 */
	public function filter_navigation_before_save( \stdClass $prepared_post, \WP_REST_Request $request ): \stdClass { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( empty( $prepared_post->post_content ) ) {
			return $prepared_post;
		}

		$blocks          = parse_blocks( $prepared_post->post_content );
		$filtered_blocks = $this->filter_blocks_recursive( $blocks );

		$prepared_post->post_content = serialize_blocks( $filtered_blocks );

		return $prepared_post;
	}

	/**
	 * Recursively filters navigation blocks to remove restricted pages.
	 *
	 * @param array $blocks {
	 *     Optional. Array of parsed blocks.
	 *
	 *     @type string|null         $blockName   The block name, e.g. 'core/navigation-link'.
	 *     @type array<string,mixed> $attrs       Block attributes.
	 *     @type array<int|string,array<string,mixed>> $innerBlocks Inner block data.
	 *     @type string|null         $innerHTML   Raw inner HTML.
	 *     @type array<int|string,string|null>    $innerContent Inner block content.
	 * }
	 * @phpstan-param array<int|string, array{
	 *     blockName?: string|null,
	 *     attrs?: array<string, mixed>,
	 *     innerBlocks?: array<int|string, array<string, mixed>>,
	 *     innerHTML?: string,
	 *     innerContent?: array<int|string, string|null>
	 * }> $blocks
	 *
	 * @return array<int|string, array{
	 *     blockName?: string|null,
	 *     attrs?: array<string, mixed>,
	 *     innerBlocks?: array<int|string, array<string, mixed>>,
	 *     innerHTML?: string,
	 *     innerContent?: array<int|string, string|null>
	 * }> Filtered block array.
	 *
	 * @since 0.1.0
	 */
	private function filter_blocks_recursive( array $blocks ): array {
		$filtered = array();

		foreach ( $blocks as $block ) {
			$should_include = true;

			if ( 'core/navigation-link' === ( $block['blockName'] ?? '' ) ) {
				$post_id = $this->extract_page_id_from_block( $block );

				if ( $post_id ) {
					$vis_raw = get_post_meta( $post_id, '_restrictly_visibility', true );

					if ( $vis_raw ) {
						$vis = $vis_raw;
					} else {
						$vis = 'everyone';
					}

					$roles = (array) get_post_meta( $post_id, '_restrictly_roles', true );

					if ( ! Enforcement::can_view_by_visibility( $vis, $roles ) ) {
						$should_include = false;
					}
				}
			}

			if ( $should_include ) {
				if ( ! empty( $block['innerBlocks'] ) ) {
					$block['innerBlocks'] = $this->filter_blocks_recursive( $block['innerBlocks'] );
				}
				$filtered[] = $block;
			}
		}

		return $filtered;
	}

	/**
	 * Extracts a page ID from a navigation-link block's attributes.
	 *
	 * @param array $block {
	 *     Optional. The block data.
	 *
	 *     @type string|null         $blockName   The block name, e.g. 'core/navigation-link'.
	 *     @type array<string,mixed> $attrs       Block attributes.
	 *     @type array<int|string,array<string,mixed>> $innerBlocks Inner block data.
	 *     @type string|null         $innerHTML   Raw inner HTML.
	 *     @type array<int|string,string|null>    $innerContent Inner block content.
	 * }
	 * @phpstan-param array{
	 *     blockName?: string|null,
	 *     attrs?: array<string, mixed>,
	 *     innerBlocks?: array<int|string, array<string, mixed>>,
	 *     innerHTML?: string,
	 *     innerContent?: array<int|string, string|null>
	 * } $block
	 *
	 * @return int Page ID or 0 if not found.
	 *
	 * @since 0.1.0
	 */
	private function extract_page_id_from_block( array $block ): int {
		$attrs = $block['attrs'] ?? array();

		if ( ! empty( $attrs['id'] ) && is_numeric( $attrs['id'] ) ) {
			return (int) $attrs['id'];
		}

		if ( isset( $attrs['type'] ) && 'page' === $attrs['type'] && isset( $attrs['id'] ) ) {
			return (int) $attrs['id'];
		}

		if ( isset( $attrs['kind'] ) && 'post-type' === $attrs['kind'] && isset( $attrs['id'] ) ) {
			return (int) $attrs['id'];
		}

		if ( ! empty( $attrs['url'] ) ) {
			return url_to_postid( $attrs['url'] );
		}

		return 0;
	}
}

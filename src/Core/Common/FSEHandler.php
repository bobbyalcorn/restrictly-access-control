<?php
/**
 * Handles Restrictly™ compatibility with Full Site Editing (FSE) block themes.
 *
 * Ensures Restrictly's access rules apply to Navigation, Query, and Post blocks
 * within block-based themes, maintaining consistent visibility control between
 * classic and FSE environments.
 *
 * @package Restrictly
 * @since   0.1.0
 */

namespace Restrictly\Core\Common;

defined( 'ABSPATH' ) || exit;

/**
 * Applies Restrictly™ visibility and access control inside Full Site Editing (FSE) block output.
 *
 * @since 0.1.0
 */
class FSEHandler {

	/**
	 * Initialize Restrictly™ FSE compatibility.
	 *
	 * Hooks into the block rendering system to apply access restrictions to
	 * Navigation, Query, and Post blocks.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function init(): void {
		add_filter( 'render_block', array( __CLASS__, 'filter_block_output' ), 10, 2 );
	}

	/**
	 * Emit a debug event for Restrictly FSE observability.
	 *
	 * @param string              $event   Event name.
	 * @param array<string,mixed> $context Optional contextual data.
	 *
	 * @return void
	 *
	 * @since 0.1.1
	 */
	private static function emit_debug_event( string $event, array $context = array() ): void {
		/**
		 * We intentionally do NOT check is_debug_enabled() here.
		 * The gate lives in Enforcement and Pro controls the listener.
		 */
		// phpcs:ignore WordPress.NamingConventions.ValidHookName
		do_action( 'restrictly/debug/event', $event, $context );
	}

	/**
	 * Filters block output to apply Restrictly™ access rules.
	 *
	 * Checks Navigation and Query-type blocks for Restrictly restrictions
	 * and removes or redacts content accordingly.
	 *
	 * @param string              $block_content The rendered block content.
	 * @param array<string,mixed> $block The block data array (name, attrs, etc.).
	 *
	 * @return string Filtered block content (could be empty if restricted).
	 *
	 * @since 0.1.0
	 */
	public static function filter_block_output( string $block_content, array $block ): string {
		// Skip filtering inside the Site Editor or admin UI.
		if ( is_admin() ) {
			return $block_content;
		}

		if ( empty( $block['blockName'] ) ) {
			return $block_content;
		}

		// Handle Navigation links.
		if ( 'core/navigation-link' === $block['blockName'] ) {
			$attrs = $block['attrs'] ?? array();
			$url   = $attrs['url'] ?? '';

			if ( ! empty( $url ) && ! self::user_can_view_url( $url ) ) {
				// Emit debug event.
				self::emit_debug_event(
					'fse_visibility_denied',
					array(
						'type'   => 'navigation_link',
						'url'    => $url,
						'block'  => 'core/navigation-link',
						'reason' => 'user_cannot_view_url',
					)
				);

				return '';
			}

			if ( ! empty( $url ) ) {
				// Emit debug event.
				self::emit_debug_event(
					'fse_visibility_allowed',
					array(
						'type'  => 'navigation_link',
						'url'   => $url,
						'block' => 'core/navigation-link',
					)
				);
			}
		}

		// Handle Query/Post blocks.
		if ( in_array( $block['blockName'], array( 'core/query', 'core/post-template', 'core/latest-posts' ), true ) ) {
			// Emit debug event.
			self::emit_debug_event(
				'fse_query_evaluated',
				array(
					'block' => $block['blockName'],
				)
			);

			return self::filter_restricted_posts( $block_content );
		}

		// Catch auto-generated navigation markup (no blockName).
		if (
			str_contains( $block_content, '<nav' )
			|| str_contains( $block_content, 'wp-block-navigation' )
			|| str_contains( $block_content, '<ul' )
		) {
			return self::filter_restricted_posts( $block_content );
		}

		return $block_content;
	}

	/**
	 * Determines whether the current user can view a given URL.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return bool True if user can view, false otherwise.
	 *
	 * @since 0.1.0
	 */
	private static function user_can_view_url( string $url ): bool {

		// Try url_to_postid first.
		$post_id = url_to_postid( $url );

		// If url_to_postid fails, try to resolve by slug.
		if ( ! $post_id ) {
			$path = wp_parse_url( $url, PHP_URL_PATH );

			if ( $path ) {
				$slug = trim( $path, '/' );

				$posts = get_posts(
					array(
						'name'           => $slug,
						'post_type'      => array( 'post', 'page' ),
						'posts_per_page' => 1,
						'post_status'    => 'any',
					)
				);

				if ( ! empty( $posts ) ) {
					$post_id = (int) $posts[0]->ID;
				}
			}
		}

		// External or non-WordPress links are always visible.
		if ( ! $post_id ) {
			return true;
		}

		// Defer ALL access decisions to the central enforcement engine.
		return Enforcement::can_access( $post_id );
	}

	/**
	 * Filters restricted posts out of rendered post lists.
	 *
	 * Removes entire list items containing links to restricted posts.
	 *
	 * @param string $content The rendered HTML of the post list.
	 *
	 * @return string Filtered HTML output.
	 *
	 * @since 0.1.0
	 */
	private static function filter_restricted_posts( string $content ): string {
		// Extract all URLs from links.
		preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $matches );
		if ( empty( $matches[1] ) ) {
			return $content;
		}

		foreach ( $matches[1] as $url ) {
			$can_view = self::user_can_view_url( $url );
			if ( ! $can_view ) {
				// Emit debug event.
				self::emit_debug_event(
					'fse_post_removed',
					array(
						'url'    => $url,
						'block'  => 'query_output',
						'reason' => 'restricted_post',
					)
				);

				// Try multiple patterns to remove the restricted item.
				$url_escaped = preg_quote( $url, '/' );

				// Pattern 1: Simple <li> with link.
				$pattern1 = '/<li[^>]*>\s*<a[^>]+href=["\']' . $url_escaped . '["\'][^>]*>.*?<\/a>\s*<\/li>/is';
				$result   = preg_replace( $pattern1, '', $content );
				if ( null !== $result ) {
					$content = $result;
				}

				// Pattern 2: <li> with nested elements.
				$pattern2 = '/<li[^>]*>.*?<a[^>]+href=["\']' . $url_escaped . '["\'].*?<\/li>/is';
				$result   = preg_replace( $pattern2, '', $content );
				if ( null !== $result ) {
					$content = $result;
				}

				// Pattern 3: Article/div with wp-block-post class.
				$pattern3 = '/<(article|div)[^>]*class="[^"]*wp-block-post[^"]*"[^>]*>.*?<a[^>]+href=["\']' . $url_escaped . '["\'].*?<\/\1>/is';
				$result   = preg_replace( $pattern3, '', $content );
				if ( null !== $result ) {
					$content = $result;
				}
			}
		}

		return $content;
	}
}

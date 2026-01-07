=== Restrictly - Access Control ===
Contributors: bobbyalcorn
Author URI: https://github.com/bobbyalcorn
Plugin URI: https://restrictlypro.com
Tags: content restriction, access control, user roles, visibility, rest api
Requires at least: 5.2
Tested up to: 6.9
Requires PHP: 7.4
PHP Tested Up To: 8.3
Stable tag: 0.1.1
Text Domain: restrictly-access-control
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.txt

Performance-focused access control for WordPress, enforcing rule-based visibility across content, menus, and blocks with a clean core.

== Description ==

Restrictly™ delivers professional-grade access control without compromising your site's speed or reliability.
It enforces rule-based visibility across **pages**, **posts**, **menus**, **Full Site Editing (FSE) blocks**, **search results**, and **REST API responses**, while maintaining full compatibility with themes and page builders.

In the free version, access rules are driven by user roles and login status, providing a clean and reliable foundation for content and navigation control.

Includes **Extended Visibility Filtering**, which automatically hides restricted content from search results, archives, and public listings.

For full documentation, setup guides, and developer notes, visit the [official Restrictly™ website](https://restrictlypro.com).

== Features ==

- **Full Site Editing (FSE) Integration** – Add block-level visibility directly inside the Site Editor.
  Choose who can see each block (Everyone, Logged-In, Logged-Out, or Specific Roles) with optional color-coded indicators.
- **Navigation Block Support (FSE Menus)** – Manage visibility for navigation links, submenus, and page lists directly within the Site Editor.
- **Extended Visibility Filtering** – Automatically hides restricted content from:
  - Search results
  - Category, tag, and author archives
  - Home listings and custom queries
- **REST API Enforcement** – Applies Restrictly™ visibility rules to REST API responses, automatically filtering restricted content for unauthorized users.
- **Dynamic Menu Visibility (Classic Menus)** – Control menu item visibility by login state or user role in the classic menu editor.
- **Full, Quick, and Bulk Edit Support** – Manage restrictions from any editor interface.
- **Sortable, Filterable Columns** – Instantly see and organize restricted items in list tables.
- **Administrator Override** – Allows administrators to bypass restrictions for testing.
- **Divi & Page Builder Compatibility** – Works perfectly with Divi and other builders.
- **Performance-Focused & Secure** – Built entirely on WordPress core APIs with strict sanitization and escaping.
- **Translation Ready** – Includes a `.pot` file for localization.
- **Clean Uninstall** – Removes all plugin data and options when uninstalled.

== Installation ==

= Requirements =
- **WordPress:** 5.2 or higher (tested up to 6.9)
- **PHP:** 7.4 or higher (tested up to 8.3)
- **Stable Version:** 0.1.1

= Automatic Installation =
1. Go to **Plugins → Add New** in your WordPress dashboard.
2. Search for **Restrictly**.
3. Click **Install Now**, then **Activate**.

= Manual Installation =
1. Download the latest version from [GitHub](https://github.com/bobbyalcorn/restrictly).
2. Upload it via **Plugins → Add New → Upload Plugin**.
3. Activate **Restrictly™** from the **Plugins** page.

= Quick Start =
1. Activate **Restrictly™** from the WordPress Plugins menu.
2. Configure global rules under **Restrictly → Settings**.
3. Apply restrictions in the page, post, or FSE block editor.
4. Control menu visibility under **Appearance → Menus**.

== Usage ==

= Restricting Content =
Restrictly™ lets you limit access to any **page** or **post** using intuitive controls:
- **Login-Based Restrictions** – Limit visibility to Logged-In or Logged-Out users.
- **Role-Based Restrictions** – Allow access only to specific user roles.
- **Enforcement Actions** – Display a custom message or redirect unauthorized users.
- **Administrator Override** – Admins can always access restricted content when enabled.

= Restricting Blocks (FSE) =
Restrictly™ integrates directly with the WordPress Site Editor (FSE) to provide per-block visibility:
- Choose **Everyone**, **Logged-In**, **Logged-Out**, or **Specific Roles**.
- Instantly preview restrictions using colored visibility indicators.
- Restrictions are enforced server-side for full security and consistency.

= Restricting Navigation (FSE Menus) =
Restrictly™ also controls FSE Navigation menu visibility:
- Show or hide individual links, submenus, and page lists by login status or role.
- Works natively inside the block-based Navigation editor.

= REST API Enforcement =
Restrictly™ applies identical access logic to REST API endpoints.
Unauthorized users see redacted content instead of restricted data — ensuring privacy across your site.

= Search & Archive Filtering =
Restricted content is automatically excluded from:
- **Search results**
- **Category, Tag, and Author archives**
- **Home and custom queries**

= Restricting Menu Items (Classic Menus) =
- **Login-Based Visibility** – Show menu items only to Logged-In or Logged-Out users.
- **Role-Based Visibility** – Display menu items only to selected roles.
- **Conflict Detection** – Highlights mismatched menu vs. page restrictions for easy correction.

== Frequently Asked Questions ==

= Can I restrict custom post types? =
Not yet. Restrictly™ currently supports **Pages**, **Posts**, and **Blocks (FSE)**.
Custom post type (CPT) restrictions will be available in **Restrictly™ Pro**.

= Does this work with Divi and Elementor? =
Yes. Restrictly™ uses native WordPress APIs and is fully compatible with popular page builders.

= Does it modify WordPress menus? =
No. Restrictly™ only manages menu **visibility**, never structure or markup.

= Will this slow down my website? =
No. Restrictly™ is optimized for performance and runs lightweight logic only when required.

= Can I restrict WooCommerce products? =
Not yet. WooCommerce and CPT restrictions will be part of **Restrictly™ Pro**.

== Screenshots ==
1. Access Control Settings – Manage global restriction behavior and user role options.
2. Pages List Columns – Instantly see which pages are restricted.
3. Quick Edit (All Logged-In Users) – Restrict access directly from the Quick Edit panel.
4. Quick Edit (Specific Role + Redirect) – Assign roles and custom redirects.
5. Standard Edit Screen – Restrict content via Restrictly's sidebar meta box.
6. Bulk Edit – Apply restriction settings to multiple items at once.
7. Menu Setup Notice – Prompt to assign a navigation menu for Restrictly to manage.
8. No Menus Found – Friendly onboarding message to create a menu.
9. Matched & Mismatched Menu Items – Visual markers for items with Restrictly data.
10. Menu Validation (All Good) – Confirms all menu items are synced.
11. FSE Visibility Indicator – Shows block-level visibility inside the Site Editor.
12. Theme Compatibility Notice – Appears when a theme does not support standard menus.

== Privacy ==
Restrictly™ does **not** collect user data, track activity, or transmit information externally.

== Changelog ==

= 0.1.1 =
Observability and internal lifecycle hooks added for Restrictly™ Pro debugging and diagnostics.

- Added gated debug event emission across all access enforcement paths.
- Added evaluation start, evaluation end, and final decision signals.
- Added administrator override observability (always-allow behavior).
- Added query influence detection for search, archive, and home queries.
- Added Full Site Editing (FSE) block, navigation, and query observability hooks.
- No behavior changes to access control logic.
- No overrides introduced.
- Fully backward compatible.

= 0.1.0 =
Initial public release of **Restrictly™**, a lightweight, performance-focused access control system for WordPress.

- Role-based and login-based restrictions for pages, posts, and FSE blocks.
- FSE Navigation visibility controls.
- Extended Visibility Filtering for search, archives, and queries.
- REST API content redaction for unauthorized users.
- Dynamic menu visibility (Classic & FSE).
- Administrator Access Override.
- Divi and builder compatibility.
- Full, Quick, and Bulk edit support.
- Sortable admin columns.
- Complete uninstall cleanup.
- Fully compliant with PHPCS/WPCS, PHPStan, ESLint, and Stylelint.

== Upgrade Notice ==

= 0.1.1 =
Adds internal observability hooks used by Restrictly™ Pro for debugging and access diagnostics. No behavior changes.

= 0.1.0 =
Initial public release of **Restrictly™**, including FSE block visibility, navigation visibility, REST API enforcement, and role-based content control.

== Contributing & Support ==
Development happens on GitHub — pull requests and issue reports welcome:
https://github.com/bobbyalcorn/restrictly

For full documentation and support, visit:
https://restrictlypro.com
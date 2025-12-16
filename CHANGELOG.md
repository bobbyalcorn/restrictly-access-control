All notable changes to **Restrictly™** are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) and this project adheres to [Semantic Versioning](https://semver.org/).

---

## **[0.1.0] - 2025-12-05**

### 🎉 Initial Public Release

The first public release of **Restrictly™**, a lightweight and performance-focused access control system for WordPress.
This version establishes the complete foundation for **role-based and login-based visibility**, covering pages, posts, menus, and blocks — all with zero bloat and full WordPress compliance.

---

### 🔍 Core Functionality

* **Full Site Editing (FSE) Integration** - Add block-level visibility controls directly inside the WordPress Site Editor.
  Choose who can see each block - **Everyone**, **Logged-In Users**, **Logged-Out Users**, or **Specific Roles** - with a clean, non-intrusive sidebar panel and visual indicator.
* **Navigation Block Integration** - Restrictly's visibility system extends to **FSE Navigation blocks**, letting you control who can see individual menu links or submenus inside the editor.
* **Visibility Indicator Toggle** - Option under *Restrictly → Settings → Block Editor Visibility* to enable or disable visibility badges inside FSE.
* **Extended Visibility Filtering** - Automatically hides restricted content from search results, archives, and feeds, ensuring unauthorized users never see restricted titles or excerpts.
* **REST API Enforcement** - Mirrors front-end restriction logic, redacting restricted content from REST API responses.
* **Dynamic Menu Visibility (Classic)** - Manage classic WordPress menu items by **login status** or **user roles**, with built-in mismatch highlighting.
* **Full, Quick, and Bulk Edit Support** - Configure restrictions seamlessly across standard WordPress edit modes.
* **Sortable and Filterable Columns** - Adds Restrictly™ columns to admin list tables for quick visibility and management.
* **Administrator Override** - Global setting in *Restrictly → Settings* allows Administrators to bypass restrictions for testing.
* **Divi and Page Builder Compatibility** - Works seamlessly with Divi and other page builders.
* **Translation Ready** - Includes `.pot`, `.po`, and `.mo` files for localization.
* **Clean Uninstallation** - Removes all Restrictly™ options and postmeta cleanly when uninstalled.

---

### 🛡️ Security Enhancements

* **Hardened Security Layer** - Every save action, AJAX call, settings update, and REST response is protected with strict nonce checks, capability checks, sanitized input, and escaped output.
* **No Frontend Metadata Leakage** - Restricted meta fields are redacted from REST and front-end output for unauthorized users.

---

### ⚡ Performance Improvements

* **Zero Frontend Overhead** - Restrictly only runs restriction logic on content that has restriction rules. Unrestricted content incurs **no performance cost**.
* **Optimized Query Rules** - Lightweight meta queries used only for logged-out REST requests; all logged-in queries bypass filtering entirely.
* **Admin Scripts Loaded Conditionally** - Scripts load only on screens using Restrictly fields.

---

### ⚙️ Architecture and Standards

* **WordPress Coding Standards (PHPCS/WPCS)** - Fully compliant across PHP 7.4-8.3.
* **Strict Type Checking** - Enforced across all PHP classes.
* **PHPStan Analysis** - Zero-level error score under static analysis.
* **Validated Codebase** - JS and CSS validated through **ESLint**, **JSHint**, **Stylelint**, and **Prettier**.
* **PSR-4 Namespacing** - All classes autoloaded under `Restrictly\\Core\\*` namespaces.
* **Automated Build System** - Includes clean build scripts for each target:

    * `build.sh` - Standard plugin ZIP
    * `build-github.sh` - GitHub release build (with docs and screenshots)
    * `build-repo.sh` - WordPress.org-ready build

---

### 🧠 Support and Contributions

Have questions, feedback, or feature requests?

* 🐛 **Report issues or request features:**
  [https://github.com/bobbyalcorn/restrictly/issues](https://github.com/bobbyalcorn/restrictly/issues)

* 💻 **View the source and contribute:**
  [https://github.com/bobbyalcorn/restrictly](https://github.com/bobbyalcorn/restrictly)

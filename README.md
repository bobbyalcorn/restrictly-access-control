# Restrictly™

[![Website](https://img.shields.io/badge/Official%20Site-RestrictlyPro.com-blue?style=plastic)](https://restrictlypro.com)
![Version](https://img.shields.io/badge/version-0.1.1-blue?style=plastic)
![GitHub release (latest by date)](https://img.shields.io/github/v/release/bobbyalcorn/restrictly?style=plastic)
![License](https://img.shields.io/badge/license-GPL--2.0%2B-green?style=plastic)

![PHP Compatibility](https://img.shields.io/badge/PHP-7.4%20--%208.3-8892BF.svg?style=plastic)
![PHPCompatibilityWP](https://img.shields.io/badge/PHPCompatibilityWP-Compliant-brightgreen?style=plastic)
![WordPress Compatibility](https://img.shields.io/badge/WordPress%20Compatibility-5.2+-0073aa.svg?style=plastic)

![GitHub issues](https://img.shields.io/github/issues/bobbyalcorn/restrictly?style=plastic)
![GitHub contributors](https://img.shields.io/github/contributors/bobbyalcorn/restrictly?style=plastic)
![GitHub last commit](https://img.shields.io/github/last-commit/bobbyalcorn/restrictly?style=plastic)
![GitHub code size in bytes](https://img.shields.io/github/languages/code-size/bobbyalcorn/restrictly?style=plastic)

![PHPCS Compliance](https://img.shields.io/badge/PHPCS-Compliant-brightgreen?style=plastic)
![WPCS Compliance](https://img.shields.io/badge/WPCS-Compliant-brightgreen?style=plastic)
![PHPStan](https://img.shields.io/badge/PHPStan-Passed-brightgreen?style=plastic)
![JavaScript Standards](https://img.shields.io/badge/ESLint%20%26%20JSHint-Passed-brightgreen?style=plastic)
![Stylelint](https://img.shields.io/badge/Stylelint-Passed-brightgreen?style=plastic)

---

## 💡 Overview

**Restrictly™** is a performance-focused **access control system for WordPress** that enforces rule-based visibility across content, menus, and blocks - without the bloat of traditional membership or paywall plugins.

Built for developers, site owners, and professionals who value **speed, clarity, and clean architecture**, Restrictly™ provides a reliable foundation for access enforcement that scales naturally as your needs grow.

---

## 🔒 Core Features

- **Full Site Editing (FSE) Integration** – Add block-level visibility directly inside the Site Editor.  
  Choose who can see each block — Everyone, Logged-In, Logged-Out, or Specific Roles — with a clean, non-intrusive sidebar panel and optional color-coded visibility indicators.

- **FSE Navigation Menu Support** – Control visibility for navigation links, submenus, and page lists directly inside the Site Editor.  
  Show or hide individual navigation items by login status or user role for fully dynamic menus.

- **Extended Visibility Filtering** – Automatically hides restricted content from:
    - Search results (`is_search()`)
    - Archives (`is_archive()`)
    - Home listings (`is_home()`)
    - Custom queries  
      Visitors only see what they are authorized to view.

- **REST API Enforcement** – Applies Restrictly™ visibility rules to REST API responses.  
  Restricted content is automatically filtered to prevent unauthorized data exposure.

- **Dynamic Menu Visibility (Classic Menus)** – Manage visibility for classic WordPress menus in the traditional Menu Editor.

- **Full, Quick, and Bulk Edit Support** – Modify access settings from any edit screen, including multi-post operations.

- **Sortable, Filterable Admin Columns** – View and organize restricted content directly in the admin list tables.

- **Administrator Override** – Optional global setting that lets administrators bypass restrictions for testing.

- **Centralized Role Management** – Unified role data handled by the `RoleService` class across all components.

- **Divi & Page Builder Compatibility** – Works seamlessly with Divi and similar builders without breaking layouts.

- **Lightweight & Secure** – 100% built on WordPress core APIs with strict sanitization, escaping, and type checking.

- **Translation Ready** – Includes `.pot`, `.po`, and `.mo` files for localization.

- **Clean Uninstallation** – Removes all Restrictly™ data and options automatically on uninstall.

---

## 🧠 Developer Notes

- **Query Filtering** – The `QueryFilter` class excludes restricted content from all public queries automatically.
- **FSE Enforcement** – The `BlockVisibility` and `FSEHandler` classes handle all block- and navigation-level visibility both in-editor and front-end.
- **Unified Role Logic** – The `RoleService` class centralizes role and login-based access checks across content, menus, blocks, and REST API enforcement.
- **Strict Validation** – Full compliance with **PHPCS**, **WPCS**, **PHPStan**, **ESLint**, **Stylelint**, and **Prettier**.
- **Build Automation** – Streamlined development and packaging:
    - `build.sh` → installable plugin ZIP
    - `build-github.sh` → GitHub release package (includes docs & screenshots)
    - `build-repo.sh` → WordPress.org repository package

---

## ⚙️ Settings Overview

- **Access Control** – Define default restriction behavior for posts, pages, and content types.
- **Menu Restrictions** – Manage visibility settings for traditional WordPress menus.
- **Block Editor Visibility** – Toggle FSE visibility indicators (colored badges) on or off for a cleaner editing experience.
- **Administrator Override** – Grant administrators universal visibility access for debugging and testing.

---

## 🧩 Optional Extension: Restrictly™ Pro

Restrictly™ Pro is an optional premium extension that builds on the free Restrictly™ core with advanced access-control capabilities, including:

- Custom post type (CPT) restrictions
- Custom roles and granular policy rules
- Taxonomy & media visibility (categories, tags, files, and downloads)
- WooCommerce product & pricing visibility
- Advanced reporting, logging, and automation
- Extended navigation logic and rule inheritance

## 📦 Installation

1. Download or clone this repository.
2. Upload the `/restrictly/` folder to `/wp-content/plugins/`.
3. Activate **Restrictly™** from the WordPress Plugins page.
4. Visit **Restrictly → Settings** to configure your preferred restrictions and defaults.

---

## 🧰 Development & Contributions

Want to help improve Restrictly™? Contributions are always welcome!

1. Fork this repository and create a new branch.
2. Follow WordPress Coding Standards (PHPCS/WPCS).
3. Run full code validation before committing.
4. Submit a pull request with a clear description of your improvements.

---

## 🧾 Changelog

### 0.1.1
- Added internal observability and lifecycle hooks for Restrictly™ Pro debugging and diagnostics.
- Added gated debug event emission across all access enforcement paths.
- Added evaluation start, evaluation end, and final decision signals.
- Added administrator override observability (always-allow behavior).
- Added query influence detection for search, archive, and home queries.
- Added Full Site Editing (FSE) block, navigation, and query observability events.
- No behavior changes to access control logic.
- Fully backward compatible.

### 0.1.0
- Initial public release of Restrictly™.
- Role-based and login-based access control for pages, posts, menus, and FSE blocks.
- REST API enforcement and extended visibility filtering.
- Full PHPCS, WPCS, PHPStan, ESLint, and Stylelint compliance.

---

## 🧾 License

**Restrictly™** is licensed under the [GPL-2.0+ License](https://www.gnu.org/licenses/gpl-2.0.html).  
This ensures freedom to use, modify, and distribute while preserving open-source integrity.

---

## 🌐 Resources

- [Restrictly™ Pro](https://restrictlypro.com)
- [GitHub Repository](https://github.com/bobbyalcorn/restrictly)
- [Official Wiki](https://github.com/bobbyalcorn/restrictly/wiki)

---

## 🏷️ Tags

**Tags:** access control, content restriction, visibility, wordpress plugin, user roles, login status, block editor, fse, navigation menus, menu visibility, rest api, pages, posts

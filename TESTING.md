# 🚀 **Restrictly™ – Full Testing Guide (QA Validation Manual)**

**Version:** 0.1.0
**Status:** Pre-Launch Verification
**Purpose:** Ensure Restrictly™ is secure, stable, performant, and fully compliant with WordPress coding and plugin-repository standards.

This document describes the complete test suite used to validate Restrictly™ before every release.

Restrictly™ provides professional-grade access control without heavy membership overhead. This guide ensures every feature, permission layer, REST behavior, and editor-level integration works flawlessly.

---

# **1. Functional Testing**

## **1.1 Installation & Activation**

* Install via **WordPress Plugin Manager**
* Upload ZIP manually
* Confirm no PHP errors / warnings / notices
* Validate activation hook execution
* Check PHP + WP version compatibility
* Confirm required functions/classes load
* Ensure text-domain loads correctly

## **1.2 Deactivation & Uninstallation**

* Settings removed from `wp_options`
* Postmeta removed from `wp_postmeta`
* No leftover transients
* All uninstall/deactivation hooks run cleanly

---

# **2. Meta Box & Quick Edit Validation**

## **2.1 Standard Edit Screen**

* Meta box displays on supported post types
* Login status, roles, and enforcement settings save properly
* Values persisted in `wp_postmeta`
* Autosave / revisions / drafts unaffected
* REST API redacts metadata for unauthorized users
* Authorized users receive correct metadata
* Capability checks enforced for UI + saving

## **2.2 Quick Edit**

* Quick Edit loads correct values
* AJAX updates save without errors
* Values persist after refresh
* Works across all registered post types

---

# **3. Content Restriction Enforcement**

## **3.1 Login Status Rules**

* Public users see unrestricted content
* Logged-in users bypass restrictions
* Logged-out users see guest content
* Direct URL access blocked appropriately
* Archives, search, and feeds hide restricted content
* Authorized users continue to see restricted content

## **3.2 Role-Based Rules**

* Restrict by specific roles
* Multi-role OR logic works correctly
* Users outside selected roles blocked

## **3.3 Enforcement Behaviors**

* Custom Messages displayed
* Redirects (internal/external) fully functional
* No infinite redirect loops
* Enforcement through REST API
* Admin override obeyed globally

## **3.4 REST Query Enforcement**

* `/wp-json/wp/v2/posts` hides restricted content
* Admin override exposes all content
* Query Monitor validation

---

# **4. Menu Restriction (Classic + FSE)**

## **4.1 Behavior (Both Systems)**

* Supports Everyone / Logged-In / Logged-Out / Role-based
* Hidden items removed from output
* Works in **Appearance → Menus**
* Persists after theme switch
* Admin override works globally
* Handles cases where roles change or are missing

## **4.2 Classic Menus**

* Correct visibility
* Correct synchronization with menu-item meta

## **4.3 FSE (Block Navigation)**

* Inspector panel options appear correctly
* Navigation items honor visibility rules
* No rendering issues during block rendering
* Query blocks exclude restricted content

---

# **5. Menu Mismatch Highlighter**

## **5.1 Visibility Logic**

* Flags page/menu mismatch
* Flags unrestricted pages with restricted menu links

## **5.2 Role Logic**

* Mixed-role menu items detected
* Matching-role states show green indicators

## **5.3 UI/Behavior**

* Duplicate menu items supported
* Custom links validated
* Fixing mismatches updates highlights instantly

---

# **6. Block Visibility (Gutenberg / FSE)**

* “Restrictly Visibility” panel appears for all supported blocks
* Options: Everyone / Logged-In / Logged-Out
* Role checkboxes appear when “Logged-In” selected
* Attributes stored (`restrictlyVisibility`, `restrictlyRoles`)
* Frontend-only restriction (editor unaffected)
* Compatibility with all core blocks
* No console errors
* No DOM conflicts

---

# **7. Full Site Editing (FSE) Compatibility**

* Tested with **Twenty Twenty-Five**
* Restricted Nav items hidden on frontend
* Restricted posts hidden from Query Loop blocks
* External links unaffected
* No errors in `render_block`
* Safe fallback for classic themes
* Reusable blocks & patterns behave correctly
* Admin override respected

---

# **8. Settings Page Validation**

* All settings save + persist
* Default enforcement action applied globally
* Menu restriction toggle functional
* Admin override toggle functional
* Capability + nonce checks validated
* Fresh install defaults load correctly

---

# **9. Security & Performance Testing**

## **9.1 Security**

* Nonces on all forms
* Sanitization on all input
* Escaping on all output
* `current_user_can()` checks everywhere
* AJAX endpoints nonce + capability protected
* No direct script access
* No exposure of restricted meta via REST

## **9.2 Performance**

* Minimal queries added
* No unnecessary hooks or listeners
* Admin scripts load only where required
* No frontend overhead when inactive
* Verified via Query Monitor

---

# **10. Compatibility Testing**

* Gutenberg
* Classic Editor
* Divi
* Elementor
* FSE Themes
* PHP 7.4 → 8.3
* `WP_DEBUG` + `SCRIPT_DEBUG` = no warnings
* No console errors

**Pending tests:**

* Extended caching plugin tests
* MemberPress / RCP / BuddyBoss compatibility
* Multisite network tests

---

# **11. Codebase Validation & Polish**

* Remove unused hooks / debug calls
* Ensure all docblocks include `@since 0.1.0`
* Confirm consistency of all `require_once` and namespaces
* Run:

    * `composer validate`
    * `composer phpstan`
    * `npm run check`
* Build clean `/dist`
* Final PHPCS run
* Final Plugin Check pass

---

# **12. GitHub Release & Packaging**

* Commit final version
* Update:

    * `CHANGELOG.md`
    * `README.md`
    * `readme.txt`
* Tag release: `v0.1.0`
* Run `composer install --no-dev`
* `.gitignore` excludes `/node_modules`, `/vendor`, `/dist`
* Run all lint + QA checks clean
* Sync `TESTING.md` + update Wiki

---

# ✅ **Final Sign-Off**

Once all tasks are checked and verified, Restrictly™ is approved for release.

Restrictly™ is secure, stable, high-performance, and engineered for professional usage.

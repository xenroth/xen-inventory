=== XEN Inventory ===
Contributors:       Xenroth
Tags:               inventory, management, borrow, tracking, departments
Requires at least:  6.0
Tested up to:       6.7
Requires PHP:       8.0
Stable tag:         1.5.3
License:            GPLv2 or later
License URI:        https://www.gnu.org/licenses/gpl-2.0.html

A robust inventory management system for WordPress. Manage departments, items,
borrow logs, and availability from a clean admin and frontend interface.

== Description ==

XEN Inventory turns your WordPress site into a full-featured inventory
management system. Built with an OOP architecture and a strict
separation between the WordPress admin and the public-facing frontend,
it is designed to scale from small department stores to multi-branch
institutional inventories.

= Core Features =

* **Custom Post Type** — `xen_item` with meta fields for status, quantity,
  and date added.
* **Departments** — `xen_department` custom taxonomy (hierarchical) for
  organising items by department or sub-department.
* **Borrow Log** — Dedicated `wp_xen_inventory_logs` SQL table tracks every
  borrow and return event with borrower name, dates, quantity, and notes.
* **Calendar View** — FullCalendar-powered frontend calendar showing
  date-by-date borrow history, colour-coded by status.
* **Shortcodes** — Three ready-to-use shortcodes for the frontend:
  * `[xen_inventory_display]` — Filterable item grid with borrow modal.
  * `[xen_inventory_calendar]` — Interactive borrow history calendar.
  * `[xen_inventory_login]` — Branded frontend login form.
* **Roles & Capabilities** — Custom `xen_staff` role with granular caps
  (`xen_borrow_items`, `xen_return_items`). Administrators receive full
  `xen_manage_inventory` and `xen_manage_departments` capabilities.
* **Clean URLs** — Rewrite rules provide pretty URLs:
  `/inventory/`, `/inventory/calendar/`, `/inventory/login/`.
* **Security** — Every form save and AJAX action is protected by nonce
  verification, capability checks, and strict sanitisation/validation.
* **i18n Ready** — Full text-domain support with a `.pot` file included.

= Shortcode Reference =

| Shortcode | Description |
|---|---|
| `[xen_inventory_display]` | Item grid. Optional attributes: `department`, `status`, `columns` (1–6), `per_page`. |
| `[xen_inventory_calendar]` | FullCalendar borrow history. |
| `[xen_inventory_login]` | Frontend login form. |

= Requirements =

* WordPress 6.0 or higher
* PHP 8.0 or higher
* MySQL 5.7+ / MariaDB 10.3+

== Installation ==

1. Upload the `xen-inventory` folder to the `/wp-content/plugins/` directory,
   or install directly through the WordPress Plugin admin.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. On activation the plugin will automatically:
   * Create the `wp_xen_inventory_logs` database table.
   * Register the `xen_staff` role and assign inventory capabilities to
     the Administrator role.
   * Flush rewrite rules so the clean URLs work immediately.
4. Navigate to **XEN Inventory → Settings** to configure the frontend login
   page, items-per-page count, and public calendar visibility.
5. Add items via **XEN Inventory → Add New Item**.
6. Create departments via **XEN Inventory → Departments**.
7. Place shortcodes on any page or post to build your frontend.

= Theme Override =

Plugin templates can be overridden by placing files inside your active theme:

    {theme}/xen-inventory/page-calendar.php
    {theme}/xen-inventory/page-login.php
    {theme}/xen-inventory/page-borrow.php

== Frequently Asked Questions ==

= Can I use this for multiple branches or locations? =

Yes. Use top-level departments for branches and child departments for
sub-categories within each branch.

= Does it support non-WordPress users (inventory-only profiles)? =

Yes. The `xen_staff` role is designed for staff who only need inventory access.
You can register WordPress accounts with that role. The borrow log stores the
borrower's display name at the time of the log entry so historical accuracy is
preserved even if a user account is later deleted.

= Can I show the calendar to non-logged-in visitors? =

Yes. Enable **Public Calendar** in **XEN Inventory → Settings**.

= Can I override the plugin's frontend templates with my theme? =

Yes — see the *Theme Override* section above.

= Where is the borrow history stored? =

In the `wp_xen_inventory_logs` custom table. This avoids postmeta bloat and
provides fast indexed queries on item, user, and date columns.

= Will my data be deleted if I deactivate the plugin? =

No. Deactivation only flushes rewrite rules. Your items, departments, and log
entries remain untouched. Data is only removed when you **delete** the plugin
and confirm the uninstall action.

== Changelog ==

= 1.5.3 — 2026-05-29 =
* New: "My Borrow History" section on the inventory page shows each logged-in user their own complete history (active + returned), never showing other users' records.
* New: Borrow history table on the single item detail page — Date Borrowed, Due, Returned, Borrower, Qty, Tags, Status.
* See CHANGELOG.txt for full details.

= 1.5.2 — 2026-05-28 =
* Fix: Show full item photo without cropping.
* Fix: /inventory/ page slug no longer conflicts with CPT archive.
* See CHANGELOG.txt for full details.

= 1.5.1 — 2026-05-28 =
* New: WooCommerce-style single item detail page.
* New: Purpose/Tags field on borrow forms.
* New: Calendar day-click scrollable borrow list modal.
* See CHANGELOG.txt for full details.

= 1.5.0 — 2026-05-28 =
* Bug fixes for the inventory calendar.
* See CHANGELOG.txt for full details.

= 1.4.0 — 2026-05-28 =
* Borrower identity fields added to borrow forms and new Borrowers admin page.
* See CHANGELOG.txt for full details.

= 1.3.0 — 2026-05-28 =
* Redesign inventory item cards to WooCommerce-style product cards.
* See CHANGELOG.txt for full details.

= 1.2.0 — 2026-05-28 =
* Add CSV export to Borrow Log (respects active filters, UTF-8 BOM for Excel).
* Add "Delete Data on Uninstall" checkbox in Settings → Advanced; uninstall
  is now safe by default (data preserved unless checkbox is enabled).
* See CHANGELOG.txt for full details.

= 1.1.0 — 2026-05-28 =
* Fix duplicate XEN Inventory entry in the WordPress admin sidebar.
* Add Return UI: Return buttons in admin Borrow History meta box and a
  "My Active Borrows" section on the frontend inventory display.
* Add GitHub auto/manual updater integrated with the WordPress update system.
* Add Shortcode Reference panel to the admin Dashboard.
* Add mobile-responsive CSS for admin and frontend.
* See CHANGELOG.txt for the full details.

= 1.0.0 — 2026-05-28 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release — no upgrade steps required.

== Screenshots ==

1. Admin dashboard with quick stats.
2. Item edit screen with Details and Borrow History meta boxes.
3. Frontend item grid with department/status filters.
4. Frontend FullCalendar borrow history view.
5. Frontend login card.

== Credits ==

Built and maintained by **Richard C. Cupal, LPT**
Xenroth Digital Innovations
+63 915 0388 448
me@xenroth.com
https://xenroth.com

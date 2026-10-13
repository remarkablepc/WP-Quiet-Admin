=== WP Quiet Admin ===
Contributors: remarkablepc, jdavidhewitt
Tags: admin notices, quiet admin, notice manager, snooze notices, clean admin, disable notices, notification center
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.11
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Tame the noisy WordPress dashboard by triaging admin notices by priority into a dedicated notification center with snooze controls.

== Description ==

Tired of the endless barrage of *"Leave us a 5-star review"*, *"Upgrade to Pro for 40% off"*, and aggressive upsell banners cluttering every single page of your WordPress admin?

**WP Quiet Admin** brings peace, focus, and productivity back to your WordPress dashboard.

Instead of crudely hiding all notices with CSS (which can dangerously bury vital security and fatal database alerts), WP Quiet Admin acts like an **Intelligent Priority Inbox** for your admin notices:

* 🔴 **Critical Notices (Red)**: Fatal errors, security advisories, broken database tables, and critical dependency failures. Active critical notices are permanently preserved and never auto-purged!
* 🟡 **Warnings & Updates (Yellow)**: Plugin and theme updates, recommended backups, deprecated calls, and configuration warnings.
* 🟢 **Reviews & Promos (Green)**: Promotions, review prompts, upsells, discount codes, newsletters, and general status notices with interactive sub-filters.
* 🔵 **WordPress Core**: Dedicated first tab specifically for official WordPress updates, maintenance notices, and core advisories.
* 🟣 **Snoozed**: Dedicated queue for scheduled sleeper notices ordered by wake-up date with early-wake controls.
* ⚪ **Archive**: Searchable repository of dismissed notices with one-click restoration, Ctrl+Z undo support, and permanent purge controls.

### Intelligent Classification Engine
Our semantic keyword and DOM parser analyzes notice structure, classes, and language to accurately distinguish between urgent technical errors and deceptive promotional banners disguised as alerts.

### Checkbox Multi-Selection & Floating Bulk Bar
Need to triage multiple notices at once? Select individual notice checkboxes or use the master Select All to reveal a sleek floating bulk actions bar with instant "Dismiss Selected" and "Snooze Selected" controls.

### Snooze Notices Like an Email Inbox
Busy working on your site and can't address a notice right now? Snooze individual notices or selected batches for Tomorrow (+24 hours), 1 Week (+7 days), 1 Month (+30 days), or choose a custom date with a lightweight native browser date picker. Snoozed notices disappear from active views and badge counts until their wake-up date arrives.

### Guaranteed Safety Pass-Through
Unlike blunt notice hiders, WP Quiet Admin keeps Critical/Red notices and WordPress Core alerts visible on live administrative screens by default, ensuring you never miss a site-breaking issue or security update while on the job.

### Traffic-Light Ergonomics
Monitor your site's alert status at a glance with color-coded pill counters integrated seamlessly into the top Admin Toolbar and left sidebar submenu, completely free of horizontal scrolling.

### Incident Recovery with Ctrl+Z
Did you accidentally dismiss an important notice out of habit? Press Ctrl+Z (or Cmd+Z on Mac) or click the floating Undo toast notification to immediately bring it back.

### Show in Admin & Mute Everywhere Rules
Take complete control over notice sources. Toggle "Show in Admin" on trusted plugins so their notices appear on regular screens too, or use "Mute Everywhere" to permanently purge and silence persistent spam offenders.

== Installation ==

1. Upload the `wp-quiet-admin` folder to the `/wp-content/plugins/` directory (or upload the `.zip` file via Plugins -> Add New -> Upload Plugin).
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Access your new notification hub under **Dashboard -> Quiet Admin** or via the **Quiet Admin** counter in the top toolbar.

== Frequently Asked Questions ==

= Will this hide critical errors or security warnings? =
No. By default, WP Quiet Admin has Critical Pass-Through enabled. Any high-priority notices (such as fatal errors, security alerts, or database warnings) will continue to display on standard admin pages so you can resolve them immediately. Furthermore, active critical notices and snoozed notices are permanently preserved and never auto-purged by the retention cleanup.

= How does the Snooze feature work? =
Click the Snooze button on any notice card (or select multiple notices with checkboxes) and choose whether to snooze until Tomorrow, 1 Week, 1 Month, or Pick a Date using your browser's native date picker. The notices are moved to the dedicated Snoozed tab and their badge counters are decremented. When their wake-up date arrives, they automatically awaken and re-appear in active priority tabs.

= What does "Show in Admin" do? =
When you set a plugin to "Show in Admin" (either from its notice card or in Rules & Settings), its notices will appear both on regular WordPress admin screens and inside the Quiet Admin hub.

= Can I completely mute a spammy plugin? =
Yes. Clicking "Mute Plugin" (or selecting "Mute Everywhere" in settings) completely purges its notices from storage and blocks any new notices from that plugin across the entire dashboard.

= Why doesn't WP Quiet Admin delete ads and sidebar cards inside plugin settings pages? =
It is tempting to think: "If it looks like an ad, why doesn't WP Quiet Admin delete it?"

There is a vital architectural reason why reputable notice managers never blindly scrape or delete elements from inside other plugins' settings bodies.

If WP Quiet Admin started injecting aggressive DOM scrapers to delete sidebars or layout containers from third-party settings pages, it would inevitably cause catastrophic layout breakage: collapsing responsive grids, deleting license key activation boxes, removing legitimate support links, hiding save buttons, and breaking JavaScript event handlers.

WP Quiet Admin is engineered as a safe, respectful traffic controller for the WordPress notification bus. It silences the floating banners that interrupt your workflow across the dashboard without tampering with the internal anatomy of third-party plugin screens.

= How does the Undo feature work? =
When you dismiss or snooze a notice (or batch of notices), an interactive toast notification appears in the lower corner with an Undo button and keyboard shortcut reminder (Ctrl+Z or Cmd+Z). You can also visit the Archive or Snoozed tab anytime to restore or wake up notices.

= Is WP Quiet Admin open source? =
Yes. WP Quiet Admin is free and open-source software by RemarkablePC / David Hewitt licensed under the GNU General Public License version 2 or later.

= Where can I get help or discuss WP Quiet Admin? =
Peer-to-peer discussions, questions, and feature ideas are hosted on our GitHub Discussions forum at https://github.com/remarkablepc/WP-Quiet-Admin/discussions. Please note that WP Quiet Admin is an independent, free open-source passion project provided as-is without individual, one-on-one technical support. Community members are warmly encouraged to collaborate and help one another in the discussion forums.

== Screenshots ==

1. The WP Quiet Admin notification hub with 6 streamlined tabs and traffic-light color coding.
2. Contextual floating Bulk Actions bar with multi-selection count and batch Snooze/Dismiss controls.
3. Dedicated Snoozed tab with wake-up timers and instant "Wake Up Now" triggers.
4. Reviews & Promos stream with interactive sub-filters for Promotions & Upsells and Informational notices.
5. Interactive Snooze dropdown with quick presets and native lightweight date picker.
6. Live color-coded pill counters in the top Admin Toolbar and adjacent sidebar badges.
7. Dedicated Rules & Settings panel for Show in Admin, Mute Everywhere, and pass-through configuration.

== Changelog ==

= 1.0.11 =
* Fix: Prevented notice icon collisions where plugins using pseudo-element icons (such as LiteSpeed Cache's lightning bolt badge) overlapped notice body text.
* Fix: Enhanced plugin source detection engine with signature dictionaries, underscore normalization, base slug stems, and distinctive token matching, resolving LiteSpeed Cache and other utilities being categorized as "Unknown Plugin / System".
* Feature: Added automatic self-healing to stored notices so previously unclassified notices are upgraded to their verified plugin names on the next page view.

= 1.0.10 =
* Feature: Added interactive Sub-Filter Pill Bar to Archive tab (All Dismissed, Critical Alerts, Updates & Warnings, Reviews & Promos, WordPress Core) with real-time counters and zero-reload instant filtering.
* UI: Enhanced Archive cards with color-coded dashed borders reflecting original notice priorities (Red dashed for Critical, Yellow dashed for Warnings, Green dashed for Promos, Blue dashed for Core) to easily identify dismissed critical alerts.
* UI: Added distinct color priority pill badges alongside the [Dismissed] badge on each archived notice card.
* UI: Replaced glyph fallbacks in WordPress Admin Bar dropdown with pure CSS luminous circular status orbs for guaranteed rendering across all environments.
* Community: Added tasteful "Sponsor on GitHub" and "Donate via PayPal / Venmo" support links to the Plugins list table metadata and Rules & Settings panel.
* Refinement: "Clear All Notices" in the header safely archives all active notices rather than destroying them, making it accessible to all administrators without risk of data loss.
* Security: Strictly enforced backend Super Admin / Chief Administrator authorization on permanent database purge operations.

= 1.0.9 =
* Feature: User Action Audit Trail tracking all dismissals, tab sweeps, snoozes, unsnoozes, and purges with user attribution, timestamp, and event summaries.
* Feature: Configurable Audit Trail retention threshold (7 to 180 days, default 30 days) with a capped rolling array ensuring zero database table bloat.
* Feature: Export & Import Configuration tool allowing instant backup and cross-site migration of "Show in Admin" pass-through rules and "Mute Everywhere" blacklists via JSON.
* Feature: Super Admin / Chief Administrator Purge Governance toggle locking permanent notice clearing and archive purging to Network Super Admins (Multisite) or Chief Administrators with user deletion rights (Single Site).
* Security: Gated AJAX handlers and UI purge buttons with visual lock indicators and contextual permission tooltips.

= 1.0.8 =
* Feature: Added dedicated "Snoozed" tab immediately before the Archive tab, separating active sleeper notices with wake-up countdowns from dismissed history.
* Feature: Introduced multi-select card checkboxes and a contextual sticky floating Bulk Actions bar supporting "Dismiss Selected" and "Snooze Selected".
* Feature: Added "Wake Up All" action in the Snoozed tab to instantly awaken all sleeping notices and return them to active views.
* UI: Unified indigo traffic-light theme for Snoozed notices in tab navigation, dropdowns, and toolbar nodes.
* Performance: Optimized batch AJAX handlers for multi-item snooze, dismiss, and atomic Ctrl+Z undo recovery.

= 1.0.7 =
* Feature: Added dedicated "Dismiss All" action button to each active tab header (WordPress Core, Critical, Warnings & Updates, and Reviews & Promos) matching the Archive tab's Purge button positioning.
* UX: Full batch undo support (Ctrl+Z / Cmd+Z and floating Undo toast) for tab-wide dismissals.
* Styling: Standardized tab header flex layouts with clean responsive alignment across all views.

= 1.0.6 =
* Feature: Full Snooze functionality (Tomorrow, 1 Week, 1 Month, and Custom Date Picker) with automatic wake-up and badge decrementing.
* UI: Renamed tab to "Reviews & Promos" for clear priority ergonomics.
* UX: Replaced confusing "Whitelist" terminology with "Show in Admin" live pass-through controls.
* Archive: Added sub-filters for Snoozed and Dismissed notices with one-click "Wake Up Now" restoration.
* Branding: Updated authorship and attribution for RemarkablePC / David Hewitt.

= 1.0.5 =
* Security: Exempt active Critical notices and WordPress Core alerts from the 7-day retention purge to permanently preserve site safety.
* UI: Unified green traffic-light theme across hub cards, sub-filter dots, tab badges, and toolbar pills.
* Enhancement: Blacklisting a plugin now immediately purges its stored notices and dynamically updates badge counts.
* Code Quality: Full code audit for robust input sanitization, nonces, and PHP 8.x compatibility.

= 1.0.4 =
* Streamlined navigation to 5 compact tabs eliminating horizontal scrolling.
* Relocated Rules & Settings to top header action area.

= 1.0.3 =
* Sizing and spacing refinements for left sidebar badges.
* Clean pill styling in top admin bar.

= 1.0.0 =
* Initial public release.

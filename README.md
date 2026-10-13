# WP Quiet Admin

[![License: GPL v2](https://img.shields.io/badge/License-GPLv2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-21759b.svg?logo=wordpress)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg?logo=php)](https://www.php.net/)
[![Release](https://img.shields.io/badge/version-1.0.11-green.svg)](https://github.com/remarkablepc/WP-Quiet-Admin/releases)
[![Sponsor @remarkablepc](https://img.shields.io/badge/Sponsor-GitHub%20Sponsors-ea4aaa?logo=githubsponsors)](https://github.com/sponsors/remarkablepc?utm_source=wp-quiet-admin&utm_medium=github-readme)
[![Donate PayPal](https://img.shields.io/badge/Donate-PayPal%20%2F%20Venmo-00457C?logo=paypal)](https://www.paypal.com/ncp/payment/EKH76RTYHH24S)

> **Quick Download**: [Download Latest wp-quiet-admin.zip (v1.0.11)](https://github.com/remarkablepc/WP-Quiet-Admin/releases/download/v1.0.11/wp-quiet-admin.zip)  
> *Always install using the official `wp-quiet-admin.zip` release asset. Do not use GitHub's green "Code → Download ZIP" button for WordPress installation, as GitHub names the inner folder `WP-Quiet-Admin-main`, which WordPress handles as an incompatible duplicate plugin.*

Tame the noisy WordPress dashboard. WP Quiet Admin acts like an **Intelligent Priority Inbox** for admin notices, triaging banners into clean priority tabs with snooze controls, a zero-table audit trail, and instant safety pass-through.

---

## Why WP Quiet Admin?

Tired of the endless barrage of *"Leave us a 5-star review"*, *"Upgrade to Pro for 40% off"*, and aggressive upsell banners cluttering every page of your WordPress administration?

Instead of crudely hiding notices with blunt CSS rules (which can dangerously bury vital security advisories or database errors), WP Quiet Admin parses, deduplicates, and organizes notices into dedicated priority streams:

* 🔴 **Critical Notices (Red)**: Fatal errors, security advisories, broken database tables, and critical dependency failures. Active critical notices are permanently preserved and never auto-purged.
* 🔵 **WordPress Core**: Dedicated tab for official WordPress updates, maintenance notices, and core advisories.
* 🟡 **Warnings & Updates (Yellow)**: Plugin/theme updates, recommended backups, deprecated calls, and configuration warnings.
* 🟢 **Reviews & Promos (Green)**: Promotions, review prompts, upsells, discount codes, newsletters, and general status notices with interactive sub-filters.
* 🟣 **Snoozed**: Dedicated queue for scheduled sleeper notices ordered by wake-up date with early-wake controls.
* ⚪ **Archive**: Searchable repository of dismissed notices with color-coded dashed priority borders and one-click restoration.

<details>
<summary><b>💡 Click to Expand: Why WP Quiet Admin Exists (The Creator's Story)</b></summary>

<br>

> *"I built WP Quiet Admin because I was tired of having all these ads, reviews, upsell banners, and constant noise in my dashboard every single day. But at the same time, I needed my critical notices — database repairs, fatal errors, core security updates. Blunt CSS blockers out there were hiding the good with the bad, dangerously burying alerts that could crash a site. WP Quiet Admin was born out of the need for an intelligent traffic controller that silences the junk while keeping what's essential."*  
> — **David @ RemarkablePC**

Managing multiple production WordPress sites shouldn't feel like navigating a Times Square billboard. Every time a plugin updates, a new sticky banner asks for a 5-star review, a discount code flashes, or a newsletter signup nag takes over the top 300 pixels of your screen. 

The traditional solution was crude: hide `#wpbody-content .notice` with CSS. But that approach is dangerous on client and production sites. When WooCommerce has an unmigrated database table, when a PHP version incompatibility triggers a fatal error, or when WordPress releases a critical zero-day security update, a blind CSS blocker hides it completely.

**WP Quiet Admin was engineered as an intelligent priority inbox for WordPress notices**: it captures and suppresses promotional noise, but categorizes and preserves critical system alerts so you stay productive without flying blind.

</details>

---

## Key Features

### 🧠 Semantic Heuristic Classifier
Our DOM and keyword parser distinguishes between genuine technical alerts and deceptive promotional banners disguised as error messages.

### ⏰ Snooze Like an Email Inbox
Busy working on client content and cannot address a notice right now? Snooze individual notices or selected batches for **Tomorrow (+24 hours)**, **1 Week (+7 days)**, **1 Month (+30 days)**, or choose a **Custom Date** with your browser's native date picker. Snoozed notices automatically awaken when their wake-up date arrives.

### ☑️ Multi-Selection & Floating Bulk Bar
Select multiple notice checkboxes or check the master **Select All** box to reveal a floating bulk actions bar with instant batch **Dismiss Selected** and **Snooze Selected** actions.

### 🛡️ Guaranteed Safety Pass-Through
WP Quiet Admin keeps Critical/Red notices and WordPress Core alerts visible directly on live administrative screens by default, ensuring you never miss a site-breaking issue while on the job.

### 📋 Zero-Table Audit Trail
Track which administrator dismissed, snoozed, awakened, or purged notices. The audit log resides in `wp_options` as a rolling 150-record array with automated 30-day retention pruning, guaranteeing **zero custom database tables and zero database bloat**.

### 🔒 Super Admin Purge Governance
Restrict permanent incident deletion and archive purging to Network Super Administrators (Multisite) or Chief Administrators with user deletion privileges (Single Site), preventing secondary administrators from erasing historical records.

### 📦 Settings Portability
Export your entire configuration (including "Show in Admin" pass-through rules and "Mute Everywhere" blacklists) to a clean JSON document for instant backup or cross-site deployment.

### ↩️ Incident Recovery with Ctrl+Z
Accidentally dismissed a notice? Press **Ctrl+Z** (or **Cmd+Z** on Mac) or click the floating Undo toast notification to immediately restore it.

---

## Installation

### From WordPress Admin
1. Download `wp-quiet-admin.zip` from the latest release.
2. In your WordPress admin, navigate to **Plugins → Add New → Upload Plugin**.
3. Choose `wp-quiet-admin.zip` and click **Install Now**.
4. Activate the plugin.

### Manual Installation
1. Clone this repository into `/wp-content/plugins/wp-quiet-admin/`:
   ```bash
   git clone https://github.com/remarkablepc/WP-Quiet-Admin.git wp-quiet-admin
   ```
2. Activate **WP Quiet Admin** from the WordPress **Plugins** screen.
3. Access the notification hub under **Dashboard → Quiet Admin** or via the traffic-light pill counters in the top toolbar.

---

## Supporting Open Source Development

WP Quiet Admin is 100% free and open-source software under the GPLv2 license. There are no paid feature paywalls, subscription nag screens, or telemetry tracking.

If this plugin saves you time and restores sanity to your WordPress workflow, please consider sponsoring development:

* [💖 Sponsor @remarkablepc on GitHub Sponsors](https://github.com/sponsors/remarkablepc?utm_source=wp-quiet-admin&utm_medium=github-readme)
* [☕ Donate via PayPal / Venmo](https://www.paypal.com/ncp/payment/EKH76RTYHH24S)

---

## ❓ Frequently Asked Questions

### Why Notice Managers Do Not Scrape Page Bodies (Why WP Quiet Admin Doesn't Delete In-Page Sidebars)

It is tempting to think: *"If it looks like an ad or upsell box, why doesn't WP Quiet Admin just delete it?"*

There is a vital architectural reason why reputable notice managers never blindly scrape or delete elements from inside other plugins' settings bodies.

If WP Quiet Admin started injecting aggressive DOM scrapers or arbitrary CSS rules to delete sidebars, cards, or layout columns from third-party settings pages, it would inevitably cause catastrophic layout breakage: collapsing responsive grids, deleting license key activation boxes, removing legitimate support links, hiding save buttons, and breaking JavaScript event handlers.

WP Quiet Admin is engineered as a safe, respectful traffic controller for the WordPress notification bus (`admin_notices`). It silences the floating banners that interrupt your workflow across the dashboard without tampering with the internal anatomy or page layouts of third-party plugin screens.

---

## 💬 Community & Peer Discussions

Have feedback, questions, or ideas to share with fellow WordPress administrators and site owners? You are warmly invited to join the peer-to-peer conversation on **[GitHub Discussions](https://github.com/remarkablepc/WP-Quiet-Admin/discussions)**.

*(Note: WP Quiet Admin is independent, free and open-source software provided as-is without individual, one-on-one technical support. Community members are encouraged to help one another in the discussion forums).*

---

## License & Copyright

Copyright (c) 2026 RemarkablePC / David Hewitt.

Licensed under the [GNU General Public License v2.0 or later](LICENSE).

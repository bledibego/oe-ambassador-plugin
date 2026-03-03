== OE Ambassador – Brand Ambassador Management ==

Contributors: optimumessence
Tags: ambassador, referral, commission, woocommerce, affiliate
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A complete brand ambassador management system for WooCommerce — configurable tiers, commissions, discount codes, portal, and email reports.

== Description ==

**OE Ambassador** turns your best customers and content creators into brand ambassadors. They apply online, you approve, and the plugin handles everything else — from coupon generation to commission tracking and monthly payouts.

= Features =

* **Application form** — Embedded via `[oe_amb_apply]` shortcode. Supports name, email, phone, social platform, handle, website, and motivation text.
* **Admin approval workflow** — Review applications in WP Admin, approve or reject with one click.
* **Automated coupon generation** — Each approved ambassador automatically gets a unique WooCommerce coupon for their audience AND a personal self-purchase code.
* **Configurable tiers** — Set as many tiers as you need, each with a min/max sales count and commission percentage. Default tiers: 7% / 10% / 15% / 20%.
* **Smart commission tracking** — When a customer uses an ambassador's code, the commission is automatically recorded. NET calculation: order total − tax − shipping = NET, then × tier %.
* **Ambassador portal** — Embedded via `[oe_amb_portal]` shortcode. Shows monthly stats, commission breakdown, codes, social share links, and payment history.
* **Monthly email reports** — Ambassadors receive a detailed monthly report with their order breakdown and commission summary. Admin receives an overview.
* **Free product allocation** — Assign specific products to ambassadors as complimentary items.
* **Social media sharing** — Built-in share buttons for Facebook, Twitter, WhatsApp, and LinkedIn. Includes a referral link with ambassador code.
* **Payout management** — Admin can mark batches of commissions as paid and notify ambassadors.
* **Auto-approve** — Optionally auto-approve commissions after N days.

= Shortcodes =

* `[oe_amb_apply]` — Application form with program benefits and tier overview.
* `[oe_amb_portal]` — Full ambassador dashboard (requires login).

= Requirements =

* WordPress 6.0+
* PHP 8.0+
* WooCommerce 8.0+

== Installation ==

1. Upload the `oe-ambassador` folder to `/wp-content/plugins/`.
2. Activate through the **Plugins** menu.
3. Two pages are created automatically: **Become an Ambassador** (with `[oe_amb_apply]`) and **Ambassador Portal** (with `[oe_amb_portal]`).
4. A setup checklist notice appears in WP Admin to guide you through the remaining steps.
5. Go to **Ambassadors → Settings** to configure tiers, email settings, and coupon percentages.

== Frequently Asked Questions ==

= How is commission calculated? =

NET = Order Total − Tax − Shipping. Commission = NET × Tier %. The tier is determined by how many approved sales the ambassador has made in the current calendar month.

= Do ambassadors need a WordPress account? =

Yes. On approval, a WP user account is created (or linked to an existing account) with the `ambassador` role. The portal is accessed by logging in.

= Can I customise the commission tiers? =

Yes. Go to **Ambassadors → Settings → Commission Tiers**. You can add, remove, and edit tiers at any time.

= Does it work with WooCommerce HPOS? =

Yes, HPOS (High Performance Order Storage) is declared as compatible.

== Changelog ==

= 1.0.0 =
* Initial release.

== Screenshots ==

1. Admin dashboard with stats
2. Ambassador list with status
3. Single ambassador detail with commission history
4. Settings page with configurable tiers
5. Frontend application form
6. Ambassador portal dashboard

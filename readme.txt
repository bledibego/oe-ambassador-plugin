== OE Brand Ambassador Management ==

Contributors: optimumessence
Tags: ambassador, referral, commission, woocommerce, affiliate
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A complete brand ambassador management system for WooCommerce — commission tracking, discount codes, ambassador portal, and more.

== Description ==

**OE Brand Ambassador Management** turns your best customers and content creators into brand ambassadors. They apply online, you approve, and the plugin handles everything else — from coupon generation to commission tracking.

= Free Plan =

* Up to 3 ambassadors
* Flat commission rate
* Ambassador application form (`[oe_amb_apply]` shortcode)
* Ambassador portal with monthly stats and coupon code (`[oe_amb_portal]` shortcode)
* Admin approval workflow
* Manual commission approval
* Basic admin dashboard

= Pro Plan =

* **Unlimited ambassadors**
* **Commission tiers** — reward more sales with higher percentages
* **Monthly email reports** — ambassadors get a full order breakdown every month
* **Payout management** — batch-mark commissions as paid and notify ambassadors
* **Self-purchase discount code** — ambassadors get their own personal discount code
* **Free product allocation** — assign complimentary products per ambassador
* **Auto-approve commissions** — optionally approve commissions after N days

= Shortcodes =

* `[oe_amb_apply]` — Application form with program benefits.
* `[oe_amb_portal]` — Full ambassador dashboard (requires login).

= Requirements =

* WordPress 6.0+
* PHP 8.0+
* WooCommerce 8.0+

== Installation ==

1. Upload the `oe-brand-ambassador-management` folder to `/wp-content/plugins/`.
2. Activate through the **Plugins** menu.
3. Two pages are created automatically: **Become an Ambassador** and **Ambassador Portal**.
4. A setup checklist notice appears in WP Admin to guide you through configuration.
5. Go to **Ambassadors → Settings** to configure commission rate, email settings, and more.

== Frequently Asked Questions ==

= How is commission calculated? =

NET = Order Total − Tax − Shipping. Commission = NET × Commission %. On Pro, the % is determined by the ambassador's monthly sales tier.

= Do ambassadors need a WordPress account? =

Yes. On approval, a WP user account is created (or linked) with the `ambassador` role. The portal is accessed by logging in.

= How do I upgrade to Pro? =

Visit the Upgrade link shown in the plugin settings or ambassador list.

= Does it work with WooCommerce HPOS? =

Yes, HPOS (High Performance Order Storage) is declared as compatible.

== Changelog ==

= 1.0.0 =
* Initial release.

== Screenshots ==

1. Admin dashboard with stats
2. Ambassador list with free-plan usage bar
3. Single ambassador detail with commission history
4. Settings page (Pro tiers locked on free plan)
5. Frontend application form
6. Ambassador portal dashboard

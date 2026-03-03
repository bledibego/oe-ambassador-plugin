# OE Ambassador – Brand Ambassador Management for WooCommerce

A complete, production-ready WordPress plugin that turns your best customers and content creators into brand ambassadors — with configurable commission tiers, discount codes, a self-service portal, and automated email reports.

## Features

- **Application form** — `[oe_amb_apply]` shortcode. Auto-created on activation.
- **Admin approval workflow** — Review, approve or reject applications with one click.
- **Automatic coupon generation** — Each approved ambassador gets a unique WooCommerce coupon for their audience + a personal self-purchase discount code.
- **Configurable commission tiers** — Set unlimited tiers with min/max sales and percentage. Default: 7% / 10% / 15% / 20% based on monthly sales.
- **Smart commission tracking** — Orders using an ambassador code automatically record a commission. Net formula: `Order Total − Tax − Shipping = NET`, then `NET × Tier %`.
- **Ambassador portal** — `[oe_amb_portal]` shortcode. Shows monthly stats, tier progress, discount codes, social share links, and payment history.
- **Monthly email reports** — Ambassadors get detailed reports. Admin gets a full program summary.
- **Payout management** — Mark commissions as paid in batch and notify ambassadors.
- **Free product allocation** — Assign complimentary products to individual ambassadors.
- **Social media sharing** — Built-in share buttons for Facebook, Twitter, WhatsApp, and LinkedIn.
- **WooCommerce HPOS compatible** — Declared compatible with High Performance Order Storage.

## Requirements

- WordPress 6.0+
- PHP 8.0+
- WooCommerce 8.0+

## Installation

1. Upload the `oe-ambassador` folder to `/wp-content/plugins/`
2. Activate through **Plugins → Installed Plugins**
3. Two pages are created automatically:
   - **Become an Ambassador** — with `[oe_amb_apply]` shortcode
   - **Ambassador Portal** — with `[oe_amb_portal]` shortcode
4. A setup checklist notice appears in WP Admin to guide you through the remaining steps
5. Go to **Ambassadors → Settings** to configure tiers, emails, and pages

## Shortcodes

| Shortcode | Description |
|---|---|
| `[oe_amb_apply]` | Application form with program benefits and tier overview |
| `[oe_amb_portal]` | Full ambassador dashboard (requires login) |

## Commission Calculation

```
NET = Order Total − Tax − Shipping
Commission = NET × Tier %
```

The tier is determined by the ambassador's cumulative sales count in the current calendar month.

## Database Tables

The plugin creates 3 custom tables on activation:

| Table | Purpose |
|---|---|
| `wp_oe_ambassadors` | Ambassador profiles and status |
| `wp_oe_amb_commissions` | One row per attributed order |
| `wp_oe_amb_payouts` | Monthly payout batches |

All tables are removed cleanly on plugin deletion.

## Development

```bash
git clone https://github.com/YOUR_USERNAME/oe-ambassador-plugin.git
# Copy oe-ambassador-plugin/ into your WP plugins directory
# or symlink it:
# ln -s /path/to/oe-ambassador-plugin /path/to/wp/wp-content/plugins/oe-ambassador
```

## License

GPL-2.0+ — the same license as WordPress itself. See [LICENSE](LICENSE).

## Contributing

Pull requests are welcome. Please open an issue first for major changes.

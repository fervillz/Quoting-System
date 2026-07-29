# WordPress setup

## Runtime requirements

- A supported WordPress release
- PHP compatible with the bundled Dompdf release
- WooCommerce when deposit or final-balance payment is required
- Pretty permalinks are recommended
- SMTP or another reliable `wp_mail()` transport is recommended

## Install the plugin

1. Copy the repository folder to `wp-content/plugins/quote-system/`.
2. Confirm `quote-system.php` is directly inside that folder.
3. Activate **Quote System** in WordPress.
4. Save **Settings → Permalinks** if frontend routes return 404.

Activation performs three operations:

1. Calls `qs_register_post_type()`.
2. Flushes rewrite rules.
3. Calls `qs_create_default_product_types()`.

The activation hook does not create frontend pages or import Quote Products.

## Confirm the data types

Activation/register hooks provide:

| Type | Slug | Notes |
|---|---|---|
| Quote CPT | `quote` | Private, admin UI enabled, supports title and author |
| Quote Product CPT | `quote_products` | Private, appears under Quote System, supports title |
| Product taxonomy | `quote_product_type` | Hierarchical, assigned to Quote Products |

Default Product Type terms:

- Door Profile
- Timber
- Finish
- Modifier
- Kickboard
- Paint
- Accessory

## Create frontend pages

Create the pages listed in [Pages and shortcodes](pages-and-shortcodes.md). The PHP code currently expects the suggested slugs in redirects, so use those slugs unless you also update the hard-coded `site_url()` calls.

## Import or create Quote Products

At minimum, publish products for:

- each selectable door profile;
- each selectable timber, including a Painted/paint-related choice;
- Finished and Raw finish choices;
- handle/accessory choices such as Finger Pull;
- kickboard materials;
- a `Painted` product under Paint;
- an `Evans` product under Door Profile, because end panels and fillers use its matrix.

See [Quote Products and pricing data](../data-model/quote-products.md) for exact keys.

## Configure users

Trade users need a normal logged-in WordPress account. Quotes are owned through `post_author`.

Administrator screens currently use WordPress capabilities:

- `edit_others_posts` permits the frontend Admin Dashboard and activates admin controls on Quote Review.
- `edit_post` permits quote actions and document access.
- `manage_options` permits the Quote Pricing overview page.

The plugin does not register a custom role. If a custom “Joiner” or “Quote Manager” role is created, map capabilities carefully and test ownership.

## Configure WooCommerce

1. Activate WooCommerce.
2. Enable the required payment gateways.
3. Confirm checkout/pay-for-order pages work over HTTPS.
4. Test deposit and final-balance orders with a sandbox gateway.

The integration creates fee-only orders, so no WooCommerce product ID is needed.

## Configure email

The admin notification address is WordPress **Settings → General → Administration Email Address**. Customer messages go to quote meta `_customer_email`.

Use an SMTP plugin or transactional mail provider and test both:

- new quote submitted → admin;
- deposit requested/approved → customer.

## Production smoke test

1. Log in as a trade user.
2. Create a multi-item quote.
3. Save and reopen the draft.
4. Confirm live subtotal.
5. Submit the quote.
6. Log in as an administrator.
7. Review pricing, generate both PDFs and request the deposit.
8. Pay the deposit with a test gateway.
9. Create and pay the final balance.
10. Confirm the final quote status is `paid_in_full`.

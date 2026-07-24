Exit code: 0
Wall time: 0.5 seconds
Output:
# Loughlin Furniture Quote System

This WordPress plugin lets approved trade customers build a joinery quote, submit it to Loughlin Furniture, and track its progress. The plugin is designed to work with WooCommerce for payment collection and Dompdf for downloadable documents.

## What a joiner can do

1. Start a quote and enter the project, contact, delivery and timber details.
2. Add any number of doors, drawers, drawer banks, end panels, fillers and kickboards.
3. Save the work as a draft, edit it later, review it, then submit it.
4. Download the quotation once it is generated.

## What the Loughlin team can do

1. View submitted quotes in the admin dashboard.
2. Check measurements, change pricing and leave internal notes.
3. Approve a quote, request its deposit, and generate a job sheet for production.
4. Track the quote through deposit, final balance and paid-in-full stages.

## The quote workflow

`Draft` -> `Pending Review` -> `Approved - Awaiting Deposit` -> `Deposit Paid` -> `Final Balance` -> `Paid In Full`

Only move a quote forward once the previous business step is genuinely complete. This keeps the joiner dashboard and the production paperwork accurate.

## Repeater fields - the important part

A repeater means â€œadd another row.â€ It is used because a real job can contain many items, not just one.

| Repeater | One row records |
|---|---|
| Doors, drawers and drawer banks | type, width, height, quantity, edge profile and drawer-bank measurements |
| End panels | height, width, quantity, faces seen and edges seen |
| Fillers | height, width, quantity, faces seen and edges seen |
| Kickboards | material, height, length and quantity |

The rows are saved as structured WordPress post meta. The exact storage keys start with `_qs_`, for example `_qs_doors_drawers`. Do not edit these values manually in the database.

## Setting prices

Open **Quote System â†’ Quote Pricing** in WordPress admin. Enter trade rates for
each component: doors, drawers, drawer banks, end panels and fillers are priced
per square metre; kickboards are priced per linear metre. You can also enter a
fixed amount for a selected profile, timber, finish and handle.

Saving a draft rebuilds its subtotal from the repeater rows. Select **Retail
pricing** on a quote when needed; the plugin applies the documented 22.22%
retail markup. The office adds shipping, discounts and any additional charges
before a deposit is requested.

## Main plugin files

| File | Plain-English purpose |
|---|---|
| `quote-system.php` | Starts the plugin and loads every feature file. |
| `repeaters.php` | Defines, cleans, saves and counts the repeatable quote rows. |
| `frontend/quote-builder.php` | The form the joiner uses to build and save a quote. |
| `frontend/quote-review.php` | The final check screen before a joiner submits the quote. |
| `pricing.php` | Calculates total, deposit and balance. |
| `admin/pricing-settings.php` | The office screen used to enter the reusable trade rates. |
| `statuses.php` | Defines the allowed quote stages. |
| `pdf.php` | Collects quote data and produces the quotation/job-sheet PDF output. |
| `integrations/woocommerce.php` | Creates WooCommerce payment orders from a quote. |
| `templates/` | HTML layouts used for PDFs and emails. |

## WordPress pages and shortcodes

Create these pages in WordPress and put the matching shortcode in each page:

| Suggested page | Shortcode |
|---|---|
| Quote Builder | `[quote_builder]` |
| Quote Review | `[quote_review]` |
| My Quotes | `[my_quotes]` |
| Admin Dashboard | `[admin_dashboard]` |
| Quote Thank You | `[quote_submitted]` |

## Before using this on a live site

- Set up WooCommerce payment gateways and replace the temporary product IDs in `integrations/woocommerce.php`.
- Configure email delivery (SMTP is recommended) and test with a real mailbox.
- Load the product pricing data and test every profile, timber, finish, paint and size band.
- Test a full quote journey as a joiner and as an administrator.
- Generate a quotation and job sheet from a multi-item quote, then check all measurements against the original job.

## Important safety notes

- Drafts and submitted quotes must only be editable by their owner or a WordPress administrator.
- Every form that changes a quote uses a WordPress security nonce. Do not remove these checks.
- Measurements are saved as positive whole millimetres. Empty repeater rows are discarded.
- A PDF is only as accurate as the saved quote data, so review the item rows before approval.


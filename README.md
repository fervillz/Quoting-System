# Loughlin Furniture Quote System

This WordPress plugin lets approved trade customers build a joinery quote, submit it to Loughlin Furniture, and track its progress. The plugin is designed to work with WooCommerce for payment collection and Dompdf for downloadable documents.

## What a joiner can do

1. Start a quote and enter the project, contact, delivery and timber details.
2. Add any number of doors, drawers, drawer banks, end panels, fillers and kickboards.
3. Add custom requests, upload supporting JPG/PNG/PDF documents, and record project notes.
4. Save the work as a draft, edit it later, review it, then submit it.
5. Download the quotation once it is generated.

## What the Loughlin team can do

1. View submitted quotes in the admin dashboard.
2. Check measurements, change pricing and leave internal notes.
3. Approve a quote, request its deposit, and generate a job sheet for production.
4. Track the quote through deposit, final balance and paid-in-full stages.
5. Expand any dashboard row to see actions specific to that quote's current stage.

## The quote workflow

`Draft` -> `Pending Review` -> `Approved - Awaiting Deposit` -> `Deposit Paid` -> `Final Balance` -> `Paid In Full`

Only move a quote forward once the previous business step is genuinely complete. This keeps the joiner dashboard and the production paperwork accurate.

## Repeater fields - the important part

A repeater means “add another row.” It is used because a real job can contain many items, not just one.

| Repeater | One row records |
|---|---|
| Doors, drawers and drawer banks | type, width, height, quantity, edge profile and drawer-bank measurements |
| End panels | height, width, quantity, faces seen and edges seen |
| Fillers | height, width, quantity, faces seen and edges seen |
| Kickboards | material, height, length and quantity |

The rows are saved as structured WordPress post meta. The exact storage keys start with `_qs_`, for example `_qs_doors_drawers`. Do not edit these values manually in the database.

Custom requests, project notes and supporting attachment IDs are also stored
on the Quote CPT as `_custom_requests`, `_project_notes` and
`_supporting_documents`. These are manual plugin fields, not ACF fields.

## Setting prices

Open **Quote System → Products** in WordPress admin. Each Quote Product stores
its own pricing method and values as normal post meta. ACF can provide the
editing interface, but it is not required by the calculation code.

The Quote Product ACF group should remain limited to pricing-product data.
Builder measurements, face/edge choices, custom requests and uploads belong to
the Quote CPT and must not be added to the Quote Product ACF export.

Door and drawer fronts use the selected profile matrix. Drawer banks price
every drawer front. End panels and fillers use the Evans matrix. Painted items
use the paint matrix, and kickboards use the selected material's linear metre
bands. Timber and finish products can apply fixed or percentage adjustments.
Selecting Painted Oak makes paint colour mandatory and removes the separate
finish choice, as specified in the supplied Joiner Log In workbook. The paint
colour is included in quote review, quotation PDFs and job sheets. Other timber
selections use the Finished or Raw finish products; Raw applies its configured
percentage discount. Painted Oak is presented as a Timber choice by the
builder, while its per-panel charge continues to come from the existing
`Painted` Quote Product assigned to the Paint product type.

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
| `frontend/quote-review.php` | Shared quote review for trade owners and administrators, with role-specific summary actions. |
| `pricing.php` | Calculates total, deposit and balance. |
| `admin/pricing-settings.php` | An overview linking the office to Quote Product price records. |
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
| Trade Login | `[quote_login]` |

Administrators and trade users open the same Quote Review page. The shortcode
checks the logged-in user's capabilities: a trade owner sees the normal
edit/submit controls, while an administrator sees pricing, PDF and workflow
actions in the same summary panel.

`[joiner_login]` is available as an alias for the Trade Login shortcode.
The quotation and job-sheet downloads are generated from the review and
dashboard buttons. Optional HTML preview pages can use `[quotation]` and
`[jobsheet]`; both require a permitted logged-in user and a `quote_id` in the
page URL.

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

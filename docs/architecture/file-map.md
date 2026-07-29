# File map

## Root files

| File | Responsibility | Main entry points |
|---|---|---|
| `quote-system.php` | Bootstrap, constants, includes, frontend CSS, activation | `qs_enqueue_assets()` |
| `post-type.php` | Registers `quote` and `quote_products` CPTs | `qs_register_post_type()` |
| `taxonomies.php` | Registers Product Type and creates default terms | `qs_create_default_product_types()` |
| `statuses.php` | Registers and changes workflow statuses | `qs_get_quote_statuses()`, `qs_update_quote_status()` |
| `quote-number.php` | Sequential `Q-YYYY-0001` identifiers | `qs_generate_quote_number()` |
| `repeaters.php` | Component schemas, sanitisation, reads/writes/counts | `qs_component_rows()`, `qs_save_component_rows()` |
| `pricing.php` | Quote Product compatibility readers and formulas | `qs_recalculate_quote_pricing()`, total/deposit/balance helpers |
| `meta-boxes.php` | Fallback WordPress-admin editing UI for Quotes | `qs_save_project_details()` |
| `template-functions.php` | Shared table, label and summary formatting | `qs_render_quote_component_table()` |
| `email.php` | Email transport and workflow notifications | `qs_email_quote_submitted()`, `qs_email_quote_approved()` |
| `pdf.php` | Quote data projection, HTML/PDF generation and download routes | `qs_get_quote_data()`, PDF functions |

## `frontend/`

| File | Screen/behavior |
|---|---|
| `quote-builder.php` | Project fields, product pickers, component editors, uploads, live summary and AJAX pricing |
| `quote-review.php` | Shared owner/admin review page, draft item removal, submission and role-specific actions |
| `my-quotes.php` | Trade dashboard, draft deletion, payment/PDF action selection |
| `admin-dashboard.php` | Search/grouping, expandable actions, admin workflow mutations |
| `quote-submitted.php` | Submission confirmation and next steps |
| `login.php` | Standalone login form and password visibility toggle |

## `admin/`

| File | Status |
|---|---|
| `quotes.php` | Custom Quote list columns |
| `pricing-settings.php` | Read-only pricing explanation and links to Quote Products |
| `quote-product-columns.php` | Empty placeholder; not loaded |
| `quote-products.php` | Empty placeholder; not loaded |

## `integrations/`

| File | Status |
|---|---|
| `woocommerce.php` | Active order creation, payment links and payment-complete callbacks |
| `woocommerce-emails.php` | Empty placeholder; not loaded |
| `woocommerce-orders.php` | Empty placeholder; not loaded |
| `woocommerce-status.php` | Empty placeholder; not loaded |

## `templates/`

| File | Used by |
|---|---|
| `quotation.php` | Quotation HTML preview and quotation PDF |
| `jobsheet.php` | Job Sheet HTML preview and PDF |
| `email-admin.php` | Admin notification after trade submission |
| `email-customer.php` | Customer message when deposit is requested |
| `email-header.php` | Included by both email bodies |
| `email-footer.php` | Included by both email bodies |

## `assets/css/`

| File | Enqueued/use |
|---|---|
| `base.css` | Shared tokens, typography, buttons, tables and status colors |
| `quote-builder.css` | Builder, pickers, components, upload, sticky summary, responsive behavior |
| `quote-review.css` | Shared review layout and role-specific summary actions |
| `my-quotes.css` | Trade dashboard |
| `admin-dashboard.css` | Admin dashboard, filters, grouped tables and expandable actions |
| `login.css` | Login shortcode |
| `quote-submitted.css` | Thank-you screen |
| `quotation.css` | Optional quotation HTML preview |
| `jobsheet.css` | Optional job-sheet HTML preview |
| `quotation-pdf.css` | Injected directly into quotation PDF HTML |
| `jobsheet-pdf.css` | Injected directly into job-sheet PDF HTML |
| `qs-*.min.css` | Legacy/stale minified files; currently not enqueued |

## `assets/images/`

| Asset | Use |
|---|---|
| `icon-pen.svg` | Summary edit action |
| `icon-trash.svg` | Summary remove action |
| `icon-image.svg` | JPG/PNG supporting document |
| `icon-pdf.svg` | PDF supporting document |

## Third-party

`dompdf/` contains Dompdf and its Composer dependencies. Treat it as vendored code:

- do not apply project formatting to it;
- do not edit its classes to fix plugin behavior;
- update it as one tested dependency;
- retest both A4 documents after any update.

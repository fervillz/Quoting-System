# Common development changes

This page is a change map: start with the feature you want to alter, then touch
all of the layers that own that feature. The Quote CPT and its post meta are the
source of truth. Quote Products supply pricing rules; they do not store a
customer's quote rows.

## Before changing anything

1. Create a branch from the current integration branch.
2. Copy a real staging quote or create a repeatable test quote.
3. Record its subtotal, total, PDF output and dashboard status.
4. Search the whole plugin for the affected meta key, function or CSS class.
5. Make the smallest compatible change, then run the relevant checks in
   [Testing checklist](testing.md).

Never rename an existing meta key or product title without a migration plan.
Old quotes must remain readable.

## Add a scalar Quote field

A scalar is one value such as a contact phone number or delivery instruction.

| Layer | Typical file | Required work |
|---|---|---|
| Form | `frontend/quote-builder.php` | Add the labelled input and conditional UI. |
| Save | `frontend/quote-builder.php` | Sanitize the submitted value and call `update_post_meta()`. |
| Load/edit | `frontend/quote-builder.php` | Read the existing value so a saved draft can be edited. |
| Review | `frontend/quote-review.php` | Display it to the quote owner and administrator. |
| Admin | `meta-boxes.php` or dashboard | Add an office-facing field only if the office must edit it. |
| Output | `pdf.php`, `templates/*.php` | Add it to the prepared data and the required document. |
| Email | `email.php`, `templates/email-*.php` | Include it only when the recipient needs it. |
| Docs | `reference/post-meta.md` | Document the key, format, writer and readers. |

Use the sanitiser that matches the value:

- short plain text: `sanitize_text_field()`;
- multiline plain text: `sanitize_textarea_field()`;
- email: `sanitize_email()`;
- integer ID/count: `absint()`;
- money: convert to a numeric value before storage;
- controlled choice: compare against a server-side allow-list.

If the value is required, validate it on the server. Browser `required`
attributes improve the interface but are not a security boundary.

## Add or change a repeatable component field

The component schema is centralized in `repeaters.php`. A field change normally
touches more places than a scalar change:

1. Add the key to the relevant schema/sanitizing path in `repeaters.php`.
2. Update the component form and its row template in
   `frontend/quote-builder.php`.
3. Update the inline builder JavaScript that creates, edits, summarizes and
   serializes the row.
4. Update `qs_calculate_component_subtotal()` or its component calculator in
   `pricing.php` if the new value affects price.
5. Update the shared review in `frontend/quote-review.php`.
6. Update `templates/quotation.php` and `templates/jobsheet.php`.
7. If office staff edit the field, update `meta-boxes.php`.
8. Add compatibility handling for rows saved before the field existed.
9. Update [Component repeaters](../data-model/component-repeaters.md) and the
   post-meta dictionary.

Keep a repeater as an array of associative arrays. Do not create a separate
post-meta key for every row or index.

### Adding a new component group

A completely new group also needs:

- a new `_qs_*` meta key;
- a count/summary renderer;
- pricing rules and a no-price behavior;
- quote review and both PDF layouts;
- duplication behavior in `qs_admin_dashboard_duplicate_quote()`;
- dashboard/detail behavior where applicable;
- save-draft and final-submit validation.

Decide whether its price is area, linear-metre, fixed, percentage or matrix
based before writing the form. The stored dimensions should stay in
millimetres; convert units only inside pricing.

## Change a choice such as profile, timber or finish

Profiles, timbers and finishes come from `quote_products` posts filtered by the
`quote_product_type` taxonomy. Prefer adding or editing a Quote Product in
WordPress rather than hard-coding another option.

When code depends on a special selection, the current implementation compares
the Quote Product title. Exact titles therefore matter:

- `Painted Oak` controls the paint-colour/finish behavior;
- `Painted` supplies the paint matrix;
- `Evans` supplies end-panel and filler matrix pricing;
- `Finger Pull` is restricted to the supported profiles.

If these names must become editable, first introduce a stable machine key on
the Quote Product and make the code fall back to the old title. Migrate existing
products only after the fallback is deployed.

## Add a pricing method

Pricing methods are implemented in `pricing.php` and stored on the
`quote_products` CPT.

1. Define the stored configuration and validation.
2. Add the calculation branch in the Quote Product pricing function.
3. Update the WordPress editor/ACF field group used to maintain the product.
4. Define behavior at size boundaries, for missing bands and for a zero price.
5. Test trade and retail modes.
6. Update the product/pricing documentation.

All money calculations should return numeric values. Format currency only at
the display boundary with the existing formatting helpers.

Do not silently substitute a nearby price band. A missing band should be
visible during testing and logging.

## Add or change a workflow status

The canonical status map is in `statuses.php`. A status change also affects:

- labels and counts on both dashboards;
- row badge classes in dashboard CSS;
- allowed buttons in `qs_admin_dashboard_quote_actions()`;
- shared review actions;
- WooCommerce callbacks;
- email triggers;
- filters and query arguments;
- the status diagrams and tables in these docs.

Store the machine value in `_quote_status`; render the human label through the
status helper. Never branch business logic on the displayed label.

## Add an administrator action

Use the existing action registry and request handler rather than placing a
one-off URL in a template.

1. Add the action only to the statuses where it is valid.
2. Render a nonce-protected form/button.
3. On submit, verify login, nonce, quote ID, quote type and
   `current_user_can( 'edit_others_posts' )`.
4. Re-check the current status on the server.
5. Apply the change, record any related IDs/meta and redirect back with a result
   notice.
6. Make repeated clicks safe. Payment-order creation in particular should not
   create duplicate active orders.

Destructive actions such as archive/delete should have a clear confirmation
and a recoverable policy.

## Add a shortcode or page

1. Implement a function that returns buffered HTML; do not directly echo before
   the buffer starts.
2. Register it once with `add_shortcode()`.
3. Check login and ownership/capability before loading quote data.
4. Escape output at render time.
5. Add the page slug to [Pages and shortcodes](../getting-started/pages-and-shortcodes.md).
6. If another feature redirects to it, replace hard-coded slug construction
   with a filterable page-ID/URL helper where practical.

## Change a PDF

The document data boundary is `pdf.php`; layout is in `templates/quotation.php`
and `templates/jobsheet.php`.

- Add normalized data in one place instead of calling `get_post_meta()` many
  times inside a template.
- Keep Dompdf-compatible HTML/CSS: tables and simple block layout are safer
  than modern browser-only layout.
- Test long project names, addresses, notes and multi-page component tables.
- Verify authorization on the download route.
- Keep quotation and job-sheet content intentionally different.

See [PDF documents](../output/pdfs.md).

## Change an email

Keep trigger logic in `email.php` and presentation in `templates/email-*.php`.
Escape customer content, use absolute URLs and test both HTML and plain fallback
behavior in the actual mail client used by the business.

Avoid sending an email merely because a page was refreshed. Tie delivery to a
successful state transition and make it idempotent where possible.

## Change CSS

Edit the unminified file under `assets/css/`; those are the files actually
enqueued. `quote-system.php` uses `filemtime()` for cache versions.

1. Use the page wrapper in every selector.
2. Reuse the brand variables/classes before adding another shade.
3. Test desktop, tablet and narrow mobile widths.
4. Check both default and expanded dashboard rows.
5. Do not edit the `qs-*.min.css` files unless the enqueue strategy is changed.

See [CSS and visual styling](../frontend/css.md).

## Change JavaScript

There are currently no standalone JavaScript bundles. The feature scripts live
inline with their PHP view:

- builder behavior: `frontend/quote-builder.php`;
- admin dashboard expansion/actions: `frontend/admin-dashboard.php`;
- review actions: `frontend/quote-review.php`;
- login behavior: `frontend/login.php`;
- admin component editor: `meta-boxes.php`.

Preserve the WordPress nonce and AJAX action names. Test an existing saved
quote, because adding rows is only half the implementation—loading and editing
old rows must also work.

## Completion checklist

- [ ] Old quotes still render and calculate.
- [ ] New and edited drafts persist after reload.
- [ ] Trade users cannot access another user's quote.
- [ ] Administrators receive the admin action set.
- [ ] Trade and retail totals are correct.
- [ ] Quotation and job sheet show the intended fields.
- [ ] Payment amounts remain correct.
- [ ] Email recipients and content are correct.
- [ ] Mobile and desktop layouts match.
- [ ] Documentation and version number are updated.

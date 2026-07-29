# Known issues and technical debt

This is an honest map of the current implementation, not a promise that the
items below are fixed. Re-evaluate and remove an entry only after code and
regression tests prove it obsolete.

## Security and privacy

### Thank-you page ownership check

**Risk:** high
**Location:** `frontend/quote-submitted.php`

`[quote_submitted]` displays quote reference/project/customer/status data from a
requested quote without applying the same owner/editor authorization used by
the PDF route. The page should require login and then allow only the quote
author or a user with `edit_others_posts`.

Until fixed, avoid exposing predictable quote IDs in public links and prevent
page caching.

### Supporting documents use Media Library attachments

**Risk:** depends on hosting configuration
**Location:** builder upload handling

The Quote page is permission protected, but WordPress attachment URLs can be
publicly reachable when someone knows the URL. If documents may contain
sensitive plans or addresses, serve them through an authorized download
endpoint or protected storage rather than direct public media URLs.

### Admin metabox capability check

**Risk:** medium
**Location:** `meta-boxes.php`

Nonce/post-type checks are present, but the save handler should also make an
explicit `current_user_can( 'edit_post', $post_id )` check before updating
quote data.

## Data and pricing

### Exact Quote Product title dependencies

Special rules currently depend on exact titles including `Painted Oak`,
`Painted`, `Evans` and `Finger Pull`. Renaming or translating those posts can
break availability or pricing. Introduce stable machine identifiers before
making titles freely editable.

### Quote number sequence

`qs_generate_quote_number()` uses one global option and produces
`Q-Y-NNNN`. The counter does not reset annually and simultaneous requests are
not guaranteed atomic. Decide whether annual reset is a business requirement
and use a locking/atomic allocation strategy for high concurrency.

### Stored legacy totals are not authoritative

Legacy/manual `_total`, `_deposit_amount` and `_balance_amount` values may exist,
but runtime helpers calculate from subtotal and adjustments. Developers can be
misled when inspecting only those fields. A future migration should remove or
clearly label redundant values.

### Shipping has no current metabox input

`_shipping` participates in the total formula, but the current office editing
surface focuses on discount and additional charges. Add a validated shipping
input if shipping must be routinely maintained in this screen.

### Product IDs are saved back as titles in one admin path

The admin component editor resolves some Quote Product IDs to labels and saves
text titles for compatibility with the frontend schema. This works only while
titles remain exact and unique. Standardize on stable product IDs plus
read-time compatibility for historical titles.

## Workflow and WooCommerce

### Existing payment order reuse

The payment creator reuses a stored WooCommerce order reference. It does not
fully define recovery behavior for cancelled, refunded, failed or already-paid
orders. Before creating another order, implement an explicit state policy and
retain an audit trail.

### No cancellation/refund rollback

WooCommerce completion advances the Quote, but cancellation or refund does not
automatically restore a prior Quote status or reconcile locked amounts. Office
staff must currently reconcile exceptional orders manually.

### Integration gaps

The current integration still needs business decisions/implementation for:

- final-balance customer email behavior;
- full billing-address mapping;
- tax-inclusive/exclusive policy;
- cancellation/refund rules;
- partial or alternative payment flows.

The files `integrations/woocommerce-emails.php`,
`integrations/woocommerce-orders.php` and
`integrations/woocommerce-status.php` are empty placeholders and are not loaded.

### Live builder saves create real drafts

After Project Name exists, the debounced AJAX flow creates/updates a Quote post
while the user works. Abandoned sessions therefore leave drafts. A cleanup or
retention policy may be needed, but it must not delete legitimate saved work.

## Frontend and maintainability

### CSS is globally enqueued

All feature stylesheets load on every frontend page. This increases payload and
the chance of theme conflicts. Enqueue each file only when its shortcode/page
is present, while retaining `base.css` where required.

### JavaScript is inline

Builder, review, dashboard, login and metabox scripts are embedded in PHP.
They assume one instance of the feature per page and are harder to lint/cache.
Move them into versioned asset files with localized configuration when the next
substantial JavaScript refactor occurs.

### Duplicate Quote Builder shortcode registration

`quote_builder` is registered in the feature file and again in the bootstrap.
WordPress tolerates replacement with the same callback, but there should be one
registration owner.

### Hard-coded page slugs

Redirects and links assume slugs such as `quote-builder`, `quote-review`,
`my-quotes` and `quote-submitted`. Sites with renamed/localized pages can break.
Store page IDs in settings or expose URL filters/helpers.

### Stale minified CSS

Files named `qs-*.min.css` remain in the asset folder but are not enqueued and
may not match the active unminified styles. Delete them or add a documented
build step; do not edit them as if they were live.

### Empty legacy/placeholder files

`admin/quote-product-columns.php`, `admin/quote-products.php` and several
WooCommerce integration files are empty and unloaded. Remove them if they are
not part of the roadmap, or add a clear owner/design before loading them.

### Taxonomy registration timing

`quote_product_type` is registered when `taxonomies.php` is included instead of
inside an `init` callback. It currently works but is less conventional and can
produce integration-order surprises. Move registration to `init` and retest
activation/rewrite behavior.

### Status transition logging

`qs_update_quote_status()` writes to the PHP error log for every attempted
transition. Routine success logging can create noisy or customer-identifying
logs. Make logging conditional on debug mode and keep it minimal.

## Output consistency

### Quotation PDF summary

The current quotation template emphasizes subtotal and does not present all
shipping, discount, additional-charge and final-total lines used by the runtime
formula. Confirm the commercial requirement, then make the customer document
and payment amount unambiguous.

### Legacy component keys remain in PDF data

The PDF preparation layer still carries older component meta alongside the
structured `_qs_*` arrays, while current templates use `_qs_*`. Remove old
fields only after proving no historical quote or custom template relies on
them.

### Archive behavior differs by dashboard

Archived quotes are intentionally hidden from the Admin Dashboard, while My
Quotes does not use exactly the same exclusion behavior. Define whether a trade
user should retain an archive/history view and make both screens explicit.

### Admin component editor is less complete

The admin metabox editor exposes fewer drawer-bank height controls than the
frontend builder. Editing such a quote in wp-admin can therefore lose nuance or
prevent a like-for-like correction. Align the schemas or make the admin editor
read-only for unsupported fields.

## Quality infrastructure

There is no automated PHP/JavaScript test suite, static analysis configuration
or coding-standard pipeline yet. High-value first additions are:

1. PHPUnit tests for subtotal, totals, status transitions and authorization;
2. fixtures for matrix boundaries and historical rows;
3. PHP_CodeSniffer with WordPress Coding Standards;
4. JavaScript linting after scripts are extracted;
5. end-to-end tests for trade/admin ownership and payment callbacks.

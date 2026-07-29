# Quote CPT and post meta

## Post record

Each quote is one `WP_Post`:

| Property | Meaning |
|---|---|
| `post_type` | `quote` |
| `post_title` | Project name |
| `post_author` | Trade user who owns the quote |
| `post_status` | Business workflow stage |
| `post_date` | Creation date |
| `post_modified` | Last update/submission date used by dashboards |

The CPT is private (`public => false`) but has a WordPress-admin UI.

## Field groups

### Identity and project

| Meta key | Type | Example |
|---|---|---|
| `_quote_number` | string | `Q-2026-0001` |
| `_project_name` | string | `Smith Kitchen Renovation` |
| `_company_name` | string | `Wilson Joinery` |
| `_customer_name` | string | `John Doe` |
| `_customer_email` | email string | `john@example.com` |
| `_customer_phone` | string | `02 ...` |
| `_delivery_address` | textarea string | Multi-line address |
| `_custom_requests` | textarea string | Non-standard work |
| `_project_notes` | textarea string | General notes |
| `_supporting_documents` | integer array | WordPress attachment IDs |

### Selected specifications

| Meta key | Current builder value | Compatibility |
|---|---|---|
| `_door_profile` | Quote Product ID | Product title is also accepted |
| `_timber` | Quote Product ID or `Painted Oak` fallback | Product title is also accepted |
| `_finish` | Quote Product ID; blank for painted timber | Product title is also accepted |
| `_handle_profile` | Quote Product ID | Product title is also accepted |
| `_paint_colour` | string | Blank for non-painted timber |
| `_paint_product` / `_paint` | optional Quote Product ID/title | Legacy/override lookup |

`qs_quote_product_label()` converts a numeric Product ID to a title for display. `qs_resolve_quote_product()` converts either an ID or old title back to a Product.

### Components

| Meta key | Type |
|---|---|
| `_qs_doors_drawers` | array of row arrays |
| `_qs_end_panels` | array of row arrays |
| `_qs_fillers` | array of row arrays |
| `_qs_kickboards` | array of row arrays |

See [Component repeaters](component-repeaters.md).

### Calculated pricing

| Meta key | Meaning |
|---|---|
| `_pricing_type` | `trade` or `retail` |
| `_trade_subtotal` | Component subtotal before retail markup |
| `_calculated_subtotal` | Calculated subtotal after pricing-mode markup |
| `_subtotal` | Subtotal consumed by review, totals and PDFs |
| `_pricing_breakdown` | Associative array of component/modifier amounts |
| `_shipping` | Office shipping adjustment |
| `_discount` | Amount subtracted from subtotal |
| `_additional_charges` | Amount added to subtotal |

Runtime total:

```text
subtotal + shipping - discount + additional charges
```

The following keys are still shown/saved by the WordPress metabox but are not the runtime source for the calculators:

```text
_total
_deposit_amount
_balance_amount
```

Use `qs_calculate_total()`, `qs_calculate_deposit()` and `qs_calculate_balance()` instead of reading those legacy/manual values.

### Workflow and payments

| Meta key | Meaning |
|---|---|
| `_internal_notes` | Admin-only notes in WordPress admin |
| `_qs_deposit_order_id` | WooCommerce deposit order |
| `_qs_deposit_payment_url` | Cached pay-for-order URL |
| `_qs_balance_order_id` | WooCommerce balance order |
| `_qs_balance_payment_url` | Cached pay-for-order URL |
| `_qs_locked_deposit_amount` | Deposit amount frozen when its order is created |
| `_qs_deposit_manually_paid_by` | Admin user ID |
| `_qs_deposit_manually_paid_at` | Site-local MySQL timestamp |
| `_qs_in_production` | Site-local MySQL timestamp |
| `_qs_in_production_by` | Admin user ID |
| `_qs_archived` | `1` hides quote from Admin Dashboard |
| `_qs_archived_at` | Site-local MySQL timestamp |
| `_qs_archived_by` | Admin user ID |

## Quote number sequence

`qs_generate_quote_number()` reads and increments WordPress option `qs_quote_sequence` and formats:

```text
Q-{current year}-{four digit sequence}
```

The current implementation does not reset the sequence when the year changes and is not an atomic counter under simultaneous inserts. See [Known issues](../development/known-issues.md).

## Ownership

The quote author is important. It controls:

- whether a trade user may edit a draft;
- which quotes appear in My Quotes;
- access to Quote Review and PDFs;
- WooCommerce order `customer_id`;
- the “Created By” display.

When importing or creating a quote programmatically, always set `post_author`.

## Direct database edits

Avoid manually editing serialized component arrays. Use:

```php
$rows = qs_component_rows( $quote_id, 'end_panels' );
// Modify $rows.
update_post_meta( $quote_id, '_qs_end_panels', qs_sanitise_component_rows( 'end_panels', $rows ) );
qs_recalculate_quote_pricing( $quote_id );
```

Prefer `qs_save_component_rows()` when replacing all component groups from a form or importer.

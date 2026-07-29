# Post-meta dictionary

## Quote meta

| Key | Type | Written by | Read by |
|---|---|---|---|
| `_quote_number` | string | first-save hook | all screens/documents/emails |
| `_project_name` | string | Builder/admin | review, PDF, email, filters |
| `_company_name` | string | Builder/admin | review, PDF, dashboards |
| `_customer_name` | string | Builder/admin | output, Woo billing |
| `_customer_email` | email | Builder/admin | email, Woo billing |
| `_customer_phone` | string | Builder/admin | data projection |
| `_delivery_address` | text | Builder/admin | review/PDF |
| `_custom_requests` | text | Builder/admin | review/PDF |
| `_project_notes` | text | Builder/admin | review/PDF |
| `_supporting_documents` | int array | Builder upload | Builder/admin/data projection |
| `_door_profile` | Product ID/title | Builder/admin | pricing/review/PDF |
| `_timber` | Product ID/title | Builder/admin | pricing/review/PDF |
| `_finish` | Product ID/title | Builder/admin | pricing/review/PDF |
| `_handle_profile` | Product ID/title | Builder/admin | pricing/review/PDF |
| `_paint_colour` | string | Builder/admin | paint lookup/review/PDF |
| `_paint_product` | Product ID/title | import/custom code | paint lookup |
| `_paint` | Product ID/title | legacy/import | paint lookup |
| `_qs_doors_drawers` | row array | Builder/admin | pricing/review/PDF |
| `_qs_end_panels` | row array | Builder/admin | pricing/review/PDF |
| `_qs_fillers` | row array | Builder/admin | pricing/review/PDF |
| `_qs_kickboards` | row array | Builder/admin | pricing/review/PDF |
| `_pricing_type` | `trade`/`retail` | Builder/admin | pricing/output |
| `_trade_subtotal` | float | pricing | diagnostics |
| `_calculated_subtotal` | float | pricing | diagnostics |
| `_subtotal` | float | pricing/admin | totals/review/PDF |
| `_pricing_breakdown` | amount map | pricing | diagnostics |
| `_shipping` | float | custom/admin data | total/output |
| `_discount` | float | admin metabox | total/output |
| `_additional_charges` | float | admin metabox | total/output |
| `_internal_notes` | text | admin metabox | data projection/admin |
| `_total` | float | admin metabox | legacy only |
| `_deposit_amount` | float | admin metabox | legacy only |
| `_balance_amount` | float | admin metabox | legacy only |
| `_qs_deposit_order_id` | int | Woo integration | payment/order links |
| `_qs_deposit_payment_url` | URL | Woo integration | cached reference |
| `_qs_balance_order_id` | int | Woo integration | payment/order links |
| `_qs_balance_payment_url` | URL | Woo integration | cached reference |
| `_qs_locked_deposit_amount` | float | deposit order | deposit/balance calculation |
| `_qs_deposit_manually_paid_by` | user ID | admin action | audit |
| `_qs_deposit_manually_paid_at` | datetime | admin action | audit |
| `_qs_in_production` | datetime | admin action | production marker |
| `_qs_in_production_by` | user ID | admin action | audit |
| `_qs_archived` | `1` | admin action | Admin Dashboard visibility |
| `_qs_archived_at` | datetime | admin action | audit |
| `_qs_archived_by` | user ID | admin action | audit |

### Legacy flat component keys

`qs_get_quote_data()` still returns raw values from:

```text
_doors_drawers
_end_panels
_fillers
_kickboards
```

Current templates use `component_rows` from `_qs_*`. Do not write new data to the flat keys.

## `_pricing_breakdown`

Associative array:

```php
array(
    'doors_drawers' => 0.00,
    'end_panels'    => 0.00,
    'fillers'       => 0.00,
    'kickboards'    => 0.00,
    'fixed'         => 0.00,
    'percentage'    => 0.00,
);
```

It stores the trade-side calculation before retail markup. The `percentage` value is the calculated currency adjustment, not the raw percent.

## Quote Product meta

| Key/base | Type | Meaning |
|---|---|---|
| `active` | boolean-like | Builder visibility |
| `pricing_method` | string | matrix/fixed/percentage/linear/square |
| `pricing_matrix_source` | relationship/ID | Source Product |
| `pricing_matrix_copy` | relationship/ID | Legacy source alias |
| `fixed_price` | float | Fixed amount |
| `price` | float | Legacy fixed/band alias |
| `percentage` | float | Overall percentage |
| `pricing_matrix` | count/array | Dimension bands |
| `pricing_matrix_{n}_height_min` | float | Inclusive mm |
| `pricing_matrix_{n}_height_max` | float | Inclusive mm |
| `pricing_matrix_{n}_width_min` | float | Inclusive mm |
| `pricing_matrix_{n}_width_max` | float | Inclusive mm |
| `pricing_matrix_{n}_price` | float | Per-panel band price |
| `linear_pricing` | count/array | Height bands |
| `linear_pricing_{n}_height_min` / `_min` | float | Inclusive height |
| `linear_pricing_{n}_height_max` / `_max` | float | Inclusive height |
| `linear_pricing_{n}_price_per_lm` / `_price` | float | Rate |
| `square_metre` | count/array | Area bands |
| `square_metre_pricing` | count/array | Compatible base alias |
| `{square base}_{n}_min` | float | Inclusive m² |
| `{square base}_{n}_max` | float | Inclusive m² |
| `{square base}_{n}_price_per_sqm` / `_price` | float | Rate |

ACF underscore “field key reference” meta may coexist. Pricing code ignores those reference keys.

## WooCommerce order meta

| Key | Type | Meaning |
|---|---|---|
| `_qs_quote_id` | Quote ID | Parent Quote |
| `_qs_payment_type` | string | `deposit` or `balance` |

## User meta

| Key | Use |
|---|---|
| `company_name` | My Quotes header only |

The Quote's `_company_name` is separate and is used for each Quote/dashboard group.

## WordPress options

| Option | Use |
|---|---|
| `qs_quote_sequence` | Next Quote number sequence source |
| `admin_email` | New submission recipient |

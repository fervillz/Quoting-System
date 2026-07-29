# Shortcodes and URL parameters

## Shortcodes

| Shortcode | Function | Notes |
|---|---|---|
| `[quote_builder]` | `qs_quote_builder_shortcode()` | Logged-in builder; registered twice harmlessly |
| `[quote_review]` | `qs_quote_review_shortcode()` | Shared trade/admin review |
| `[my_quote_review]` | same | Alias |
| `[my_quotes]` | `qs_my_quotes_shortcode()` | Trade dashboard; admin-capable users see all |
| `[admin_dashboard]` | `qs_admin_dashboard_shortcode()` | Requires `edit_others_posts` |
| `[quote_admin_dashboard]` | same | Alias |
| `[quote_submitted]` | `qs_quote_submitted_shortcode()` | Thank-you page |
| `[quote_login]` | `qs_quote_login_shortcode()` | Supports `redirect` attribute |
| `[joiner_login]` | same | Alias |
| `[quotation]` | `qs_quotation_shortcode()` | Protected HTML document preview |
| `[jobsheet]` | `qs_jobsheet_shortcode()` | Protected HTML document preview |

## GET parameters

| Parameter | Used by | Meaning |
|---|---|---|
| `quote_id` | Builder, Review, Thank You, document previews | Target Quote |
| `saved` | Builder | Displays Saved state |
| `download_quote_pdf` | Root/init handler | Stream quotation PDF |
| `download_jobsheet_pdf` | Root/init handler | Stream job-sheet PDF |
| `company` | Admin Dashboard | Exact company filter |
| `status` | Admin Dashboard | Stored status filter |
| `project` | Admin Dashboard | Exact project filter |
| `date` | Admin Dashboard | `today`, `week`, `month`, `last_month`, `year` |

## Builder POST parameters

### Commands

```text
qs_save_draft
qs_review_quote
qs_builder_nonce
quote_id (AJAX)
action=qs_builder_recalculate (AJAX)
```

### Scalar fields

```text
project_name
company_name
customer_name
customer_email
customer_phone
delivery_address
door_profile
timber
finish
handle_profile
paint_colour
pricing_type
custom_requests
project_notes
```

### Repeater fields

```text
components[component][row][field]
```

### Uploads

```text
supporting_documents[]
remove_supporting_documents[]
```

## Review POST parameters

Submission:

```text
qs_submit_quote
qs_submit_quote_nonce
quote_id
```

Remove item:

```text
qs_review_remove_item
qs_review_item_nonce
component
row_index
```

## Dashboard action POST parameters

```text
qs_dashboard_action
qs_dashboard_action_nonce
quote_id
```

Action values:

```text
delete_draft
mark_approved
request_deposit
resend_deposit
mark_deposit_paid
create_final_invoice
mark_in_production
duplicate_quote
archive_quote
```

## Login POST parameters

```text
qs_quote_login
qs_login_nonce
log
pwd
```

## URL-generation rule

Always generate with WordPress helpers:

```php
$url = add_query_arg(
    'quote_id',
    $quote_id,
    site_url( '/quote-review/' )
);
```

Escape only when outputting:

```php
echo esc_url( $url );
```

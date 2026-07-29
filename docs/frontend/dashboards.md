# Dashboards

## My Quotes

Source: `frontend/my-quotes.php`
Shortcode: `[my_quotes]`

### Query

Statuses:

```text
draft, pending_review, awaiting_deposit,
deposit_paid, final_balance, paid_in_full
```

Trade users are filtered by `author = current user`. Users with `edit_others_posts` see all queried Quotes.

### Groups

- Draft
- Pending Review
- Deposit Requested
- Approved (`deposit_paid` + `final_balance`)
- Completed (`paid_in_full`)

Saved drafts and submitted quotes render in separate tables.

### Row action

`qs_my_quotes_action()` chooses:

| Condition | Action |
|---|---|
| Draft table | Continue Quote |
| Awaiting Deposit + order | Pay Deposit |
| Final Balance + order | Pay Balance |
| Deposit Paid / Paid In Full | Download PDF |
| Otherwise | View |

### Draft deletion

The delete form checks a per-Quote nonce, Quote type, draft status, and ownership or `delete_post`. It moves the post to Trash.

## Admin Dashboard

Source: `frontend/admin-dashboard.php`
Shortcode: `[admin_dashboard]`

Requires `edit_others_posts`.

### Queries

The dashboard runs:

1. an unfiltered visible-quote query to calculate counts/filter choices;
2. a filtered query for displayed groups.

Quotes with `_qs_archived = 1` are excluded.

Filters:

- exact Company meta;
- exact Project meta;
- status;
- Today;
- Last 7 Days;
- This Month;
- Last Month;
- This Year.

### Grouping

Displayed Quotes are grouped by `_company_name`; blank values become `Unassigned`. The first non-empty customer name in the group is displayed as Company Contact.

### Expandable actions

Each main row is followed by one hidden action row. JavaScript:

- permits only one expanded Quote at a time;
- updates `aria-expanded`;
- rotates the chevron through CSS;
- confirms forms with `data-confirm`.

The action list depends on stored Quote status. See [Admin actions](../workflow/admin-actions.md).

### Status counts vs filters

Header counts always come from all visible, unarchived Quotes and do not change with the active filters. The table below them does change.

### Total display

The dashboard calls `qs_calculate_total()` at render time, then displays no decimal places. Quote Review/PDF use two decimal places.

## Shared dashboard styling

Both dashboards use:

- brand variables from `base.css`;
- `.qs-dashboard-page-header`;
- `.qs-status-*` classes;
- page-specific responsive tables.

Admin table actions use action-count classes (`qs-action-count-*`) for layout. Adding/removing actions may require CSS adjustment.

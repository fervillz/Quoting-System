# Admin actions

Admin actions are defined in `frontend/admin-dashboard.php` and reused by the shared Quote Review page.

## Security gate

Each mutating form includes a nonce for:

```text
qs_admin_quote_action_{quote_id}
```

`qs_admin_dashboard_handle_action()` verifies:

- Quote exists and has post type `quote`;
- nonce;
- current user can `edit_post` for that Quote;
- current status is valid for the requested action.

The Admin Dashboard itself also requires `edit_others_posts`. Quote Review only invokes the handler for users with that capability.

## Status-specific menu

### Draft

| Action | Result |
|---|---|
| Edit Quote | Builder URL |
| View Draft | Shared Review URL |
| Delete Draft | Moves draft to Trash |

### Pending Review

| Action | Result |
|---|---|
| Review / View Quote | Shared Review |
| Edit Pricing | WordPress Quote edit screen |
| Generate Quotation PDF | Inline PDF stream |
| Generate Job Sheet | Inline PDF stream |
| Request Deposit | Creates order, changes status, emails customer |
| Add Internal Notes | WordPress Quote edit screen |
| Mark as Approved | Changes to `awaiting_deposit`, no order/email |

### Awaiting Deposit

| Action | Result |
|---|---|
| View / View Quote | Shared Review |
| View Deposit Invoice | WooCommerce order edit URL |
| Resend Deposit Request | Reuses/creates order and sends customer email |
| Generate documents | Quotation and job sheet |
| Mark Deposit as Paid | Changes status and stores user/timestamp |
| Add Internal Notes | WordPress Quote edit screen |

### Deposit Paid or Final Balance

| Action | Result |
|---|---|
| Open / View Quote | Shared Review |
| Generate Job Sheet | PDF |
| Download Quotation PDF | PDF |
| Create Final Invoice | Creates/reuses balance order and sets `final_balance` |
| Mark as In Production | Stores timestamp and admin user; status is unchanged |
| Add Internal Notes | WordPress Quote edit screen |

### Paid In Full

| Action | Result |
|---|---|
| View / View Quote | Shared Review |
| Download Final Invoice | WooCommerce balance order edit URL |
| Duplicate Quote | Creates a new draft for the original trade owner |
| Download Job Sheet | PDF |
| Archive Quote | Adds archive meta and hides it from Admin Dashboard |

## Duplicate behavior

`qs_admin_dashboard_duplicate_quote()` copies most meta to a new draft and recalculates pricing.

It does not copy:

- quote number;
- shipping, discount, extra charges and stored totals;
- internal notes;
- deposit lock;
- production/archive markers;
- any `_qs_deposit_*` or `_qs_balance_*` payment links/order data.

The new post title receives ` (Copy)` and keeps the original `post_author`.

## Archive behavior

Archive does not trash the Quote and does not change its status. It sets `_qs_archived = 1`.

Admin Dashboard queries exclude it. My Quotes currently does not apply the archive filter, so the owning trade account can still see the completed Quote.

## Adding an action

1. Add a case to `qs_admin_dashboard_handle_action()`.
2. Define status and capability/nonce expectations.
3. Add the action to `qs_admin_dashboard_quote_actions()`.
4. Add it to `qs_review_admin_summary_actions()` if it belongs on shared Review.
5. Ensure the action is idempotent or clearly confirm destructive behavior.
6. Add CSS only if the existing action-grid rules are insufficient.
7. Test forged status/action combinations as well as the happy path.

# Review and submission

Source: `frontend/quote-review.php`
Primary shortcode: `[quote_review]`

## Shared role behavior

The same page is used by trade owners and administrators.

| User | Summary actions |
|---|---|
| Trade owner, draft | Edit Quote, Submit Quote; pen/trash item controls |
| Trade owner, submitted | My Quotes, Download PDF |
| Administrator | Admin Dashboard, Edit Pricing, documents, status-specific workflow forms |

Admin detection uses `current_user_can('edit_others_posts')`.

## Access

`qs_quote_review_can_access()` requires:

- logged-in user;
- valid Quote CPT ID;
- user can `edit_post`, or user is the Quote author.

Quote ID comes from GET first, then POST.

## Main content

The review page obtains a normalized projection from `qs_get_quote_data()` and renders:

- project details;
- featured-image specification cards;
- doors/drawers and drawer banks;
- End Panels and Fillers;
- Kickboards;
- optional Custom Requests;
- Project Notes;
- sticky summary with current subtotal.

## Draft item removal

Only a draft item can be removed from Review.

The form sends:

```text
qs_review_remove_item
qs_review_item_nonce
component
row_index
```

The handler checks nonce, access, draft status, component allow list and row existence. It then saves the reindexed row array and recalculates pricing.

The Edit icon links to the Builder but does not currently carry a component/row anchor; the user lands on the normal Builder.

## Submission

Trade Submit Quote:

1. verifies `qs_submit_quote_{quote_id}` nonce;
2. requires current status `draft`;
3. rejects administrators;
4. changes status to `pending_review`;
5. sends the admin notification;
6. redirects to `/quote-thank-you/`.

The form does not run another Builder save. The reviewed draft data is submitted as it was last saved.

## Admin controls

Admin forms reuse `qs_admin_dashboard_action_button()` and `qs_admin_dashboard_handle_action()`. This keeps status validation identical between Admin Dashboard and Review.

Current summary does not render editable shipping/discount/additional-charge fields. **Edit Pricing** opens the WordPress Quote edit screen.

## Thank-you page

Source: `frontend/quote-submitted.php`
Shortcode: `[quote_submitted]`

It displays Quote reference, Project Name, Submitted By, date and status, then links to:

- create another quote;
- download quotation PDF;
- My Quotes.

See the access concern in [Known issues](../development/known-issues.md#security-and-privacy).

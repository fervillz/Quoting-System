# Testing checklist

The plugin has no automated PHP test suite yet. Until one is introduced, use a
repeatable staging matrix and record the quote IDs and expected results for each
release.

## Recommended test environment

- a staging clone of production WordPress;
- the production PHP major/minor version;
- the active Loughlin theme and caching/optimization plugins;
- WooCommerce in test mode;
- working SMTP directed to test mailboxes;
- one administrator and at least two trade users;
- the same Quote Product/taxonomy configuration as production.

Keep two trade users so ownership boundaries can be tested.

## Fast smoke test

Run this after every change:

- [ ] Plugin activates without a fatal error.
- [ ] Trade login redirects correctly.
- [ ] Quote Builder loads its products and existing font/CSS.
- [ ] Adding one door updates the summary and subtotal.
- [ ] Saving and reopening a draft preserves all values.
- [ ] Quote Review shows the correct role-specific actions.
- [ ] Submitting changes status to Pending Review.
- [ ] Admin Dashboard displays and expands the quote.
- [ ] Quotation and job-sheet PDFs download.
- [ ] No new PHP or browser-console errors appear.

## Quote Builder regression matrix

### Project and pricing

- [ ] Required fields reject an incomplete final submission.
- [ ] Trade mode calculates the configured trade price.
- [ ] Retail mode is trade total × `1.2222`.
- [ ] Contact, delivery address, custom request and project notes survive reload.
- [ ] Live saving waits for Project Name, then creates only one continuing
      draft for that browser session.

### Door specifications

- [ ] All published Profile products appear.
- [ ] All published Timber products appear.
- [ ] Painted Oak shows a required Paint Colour input.
- [ ] Painted Oak hides the independent Finish selector.
- [ ] Other timbers show Finished and Raw.
- [ ] Finger Pull is available only with Evans, Valley and 30 Shaker.
- [ ] Selected values appear at the top of the custom selects and in the
      summary.

### Components

Test add, edit, delete and quantity for every group:

- [ ] Door
- [ ] Drawer
- [ ] Drawer bank with each supported drawer count
- [ ] End panel, every Face Seen option
- [ ] End panel, every Edge/s Seen diagram combination
- [ ] Filler, both Face Seen options and Edge/s Seen choices
- [ ] Kickboard, Solid Timber and Veneer

For every group:

- [ ] zero, negative, non-numeric and blank dimensions are rejected;
- [ ] valid dimensions and quantity survive reload;
- [ ] pen icon reopens the correct row;
- [ ] trash icon removes the correct row;
- [ ] count and `Qty.` values update;
- [ ] summary dimensions and special details are correct;
- [ ] subtotal updates after add/edit/delete.

### Uploads

- [ ] JPG, PNG and PDF under 10 MB can be selected and submitted.
- [ ] Correct icon, filename and size are shown.
- [ ] Local-read progress line appears.
- [ ] Removing a selected file removes it from the submission.
- [ ] Unsupported type and oversize file show a useful error.
- [ ] Submitted attachment IDs are saved and visible to authorized staff.

## Pricing fixtures

Create a spreadsheet or table of expected prices outside the plugin. Include:

- each profile matrix at its smallest, boundary and largest size;
- drawer front and multi-drawer-bank calculations;
- Evans end panel and filler calculations;
- Painted matrix plus paint color;
- each timber fixed/percentage adjustment;
- Finished and Raw adjustments;
- Solid Timber and Veneer kickboard length bands;
- quantities greater than one;
- trade and retail modes;
- no matching band/error behavior.

For a representative multi-item quote, verify:

```text
component subtotal
+ shipping
- discount
+ additional charges
= total
```

Then verify the deposit is 30% at order creation and the final balance is the
current total less the locked deposit.

## Draft and submission workflow

- [ ] Save Draft keeps status `draft`.
- [ ] A saved draft appears under the correct trade user.
- [ ] Edit Draft rehydrates all fields and component rows.
- [ ] Review Quote opens the shared review page.
- [ ] Final submit changes status to `pending_review`.
- [ ] Admin notification and customer confirmation send once.
- [ ] Thank-you page shows the intended quote.
- [ ] Repeated refresh does not create duplicate emails or posts.

## Permissions and security

Use Trade A, Trade B, logged-out browser and Administrator:

- [ ] Trade A can view/edit its own draft.
- [ ] Trade A cannot read or mutate Trade B's quote by changing `quote_id`.
- [ ] Trade user cannot invoke admin actions with a copied URL/form.
- [ ] Logged-out requests are rejected or redirected.
- [ ] Expired/invalid nonces are rejected.
- [ ] PDF routes enforce ownership or admin capability.
- [ ] AJAX requests enforce login, nonce and ownership.
- [ ] Uploaded HTML/script/executable files are rejected.
- [ ] User-entered content is escaped in HTML, email and PDFs.

The current thank-you and attachment-publicity limitations are documented in
[Known issues](known-issues.md); treat them as explicit risk tests.

## Dashboard tests

### My Quotes

- [ ] Counts match actual user-owned statuses.
- [ ] Draft and submitted sections contain the right records.
- [ ] Status colors/labels match the workflow.
- [ ] Only one row expands at a time if that is the intended behavior.
- [ ] Each status exposes the correct actions.
- [ ] Buttons remain usable on mobile.

### Admin Dashboard

- [ ] Counts match all non-archived quotes.
- [ ] Company, status, project and date filters work alone and together.
- [ ] Quotes group under the correct company/contact.
- [ ] Expanded rows show actions from `qs_admin_dashboard_quote_actions()`.
- [ ] Every action rejects an invalid current status.
- [ ] Archive removes the quote from the dashboard without deleting it.

## PDF checks

For quotation and job sheet:

- [ ] correct quote reference, project, company and pricing mode;
- [ ] all selected specifications and paint colour;
- [ ] every component row, measurement and quantity;
- [ ] face/edge selections and drawer-bank heights;
- [ ] delivery address and project notes where intended;
- [ ] correct monetary fields for the document type;
- [ ] long content wraps without clipping;
- [ ] headers/footers and page breaks work on multi-page output;
- [ ] file opens in browser and a standard PDF reader.

Compare the PDF against the saved Quote post meta, not only the browser summary.

## WooCommerce tests

Use test gateways only:

- [ ] Request Deposit creates one fee-only order.
- [ ] Repeating the action does not create a second active order.
- [ ] Order customer/email and quote references are correct.
- [ ] Completing the deposit order moves to `deposit_paid`.
- [ ] Create Final Invoice uses current total minus locked deposit.
- [ ] Completing the balance order moves to `paid_in_full`.
- [ ] Failed/pending payments do not advance the quote.
- [ ] Direct status changes in WooCommerce invoke the expected callback.

Also document current behavior for cancelled/refunded orders before release.

## Visual and accessibility checks

Test current Chrome, Safari and Firefox where possible at:

- 1440 px desktop;
- 1024 px tablet/compact desktop;
- 768 px tablet;
- 390 px mobile;
- 320 px narrow mobile.

Check keyboard access, visible focus, form labels, validation messages, color
contrast, screen-reader names on icon-only buttons and zoom at 200%.

## Release record

For each staged release, record:

| Item | Value |
|---|---|
| Plugin version | |
| Commit SHA | |
| WordPress/PHP/WooCommerce versions | |
| Test quote IDs | |
| Tester/date | |
| Known accepted limitations | |
| Production deployment/rollback owner | |

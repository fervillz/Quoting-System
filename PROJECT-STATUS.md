# Quote System Project Status

## Architecture

- Quote CPT = source of truth
- Frontend-first application
- WooCommerce handles payments only
- No ACF
- No form builder
- WordPress post meta

## Current Workflow

Draft
→ Pending Review
→ Awaiting Deposit
→ Deposit Paid
→ Final Balance
→ Paid In Full

## Completed

### Core

✓ quote-system.php
✓ post-type.php
✓ quote-number.php
✓ meta-boxes.php
✓ pricing.php
✓ statuses.php
✓ email.php

### Metaboxes

✓ Project Details
✓ Cabinet Specifications
✓ Components
✓ Pricing

### Frontend

✓ quote-builder shortcode
✓ quote-review shortcode
✓ my-quotes shortcode

### Admin

✓ admin-dashboard shortcode
✓ Quote approval
✓ Status updates
✓ Email notifications

### Pricing

✓ qs_calculate_total()
✓ qs_calculate_deposit()
✓ qs_calculate_balance()

### Statuses

draft
pending_review
awaiting_deposit
deposit_paid
final_balance
paid_in_full

## Current Admin Dashboard

- Summary boxes
- Company grouping
- Company contact
- Active quote count
- Status pills
- Approve button
- View button
- PDF placeholder
- Job Sheet placeholder

## Next Tasks

1. templates/quotation.php
2. templates/jobsheet.php
3. PDF generation
4. Deposit Request button
5. WooCommerce Deposit Order
6. Final Balance Order
7. Customer Dashboard styling
8. Admin Dashboard styling
9. Search filters
10. Company filters

## Important Functions

qs_get_quote_data()
qs_update_quote_status()

qs_calculate_total()
qs_calculate_deposit()
qs_calculate_balance()

qs_email_quote_submitted()
qs_email_quote_approved()
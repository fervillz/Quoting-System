# Quote System Project Status

## Architecture

- Quote CPT = source of truth
- Frontend-first application
- WooCommerce handles payments only
- No ACF runtime dependency
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
✓ quote-submitted shortcode
✓ quote-login shortcode
✓ Shared quote summary/review layout with role-specific actions
✓ Mockup-aligned tradesperson dashboard, quote tables and status cards
✓ Secure saved-draft deletion and quote workflow actions
✓ Mockup-aligned Quote Builder layout and controls
✓ Trade / Retail pricing toggle
✓ Quote Product visual selectors
✓ Expandable specification selectors with Painted Oak paint-colour rules
✓ Live sticky Quote Summary
✓ Supporting-document type icons and upload/read progress
✓ Responsive frontend layouts

### Admin

✓ admin-dashboard shortcode
✓ Admin workflow controls inside the shared quote-review shortcode
✓ Quote approval
✓ Status updates
✓ Email notifications
✓ Search and company filters
✓ Mockup-aligned dashboard styling
✓ Expandable per-quote action rows with accessible Bootstrap-style icons
✓ Status-specific dashboard CTA menus for Draft through Completed
✓ Manual deposit confirmation, final-balance order, production and archive actions
✓ Safe completed-quote duplication back to a new draft

### Pricing

✓ Quote Product matrix pricing
✓ Fixed, percentage, linear and square-metre pricing readers
✓ Evans matrix pricing for end panels and fillers
✓ Paint, Finger Pull, Walnut and Raw adjustments
✓ Live trade and retail builder subtotal
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

- Live workflow summary boxes
- Company grouping and company contact
- Company, status, project and date filters
- Status pills and calculated totals
- Expandable quote actions
- Quote review/edit links
- Quotation and job-sheet PDF actions
- Deposit request action

## Documents and Payments

✓ templates/quotation.php
✓ templates/jobsheet.php
✓ Dompdf generation
✓ Mockup-aligned A4 quotation and job-sheet layouts
✓ Deposit Request action
✓ WooCommerce deposit payment order
✓ Customer payment link

## Next Tasks

1. Complete browser testing inside the live WordPress theme
2. Test quotation and job-sheet output with several real multi-item quotes
3. Confirm production email and WooCommerce gateway settings

## Important Functions

qs_get_quote_data()
qs_update_quote_status()

qs_calculate_total()
qs_calculate_deposit()
qs_calculate_balance()

qs_email_quote_submitted()
qs_email_quote_approved()

# Functions and hooks

This is the current public-function inventory. All project functions use the `qs_` prefix.

## Bootstrap and content types

| Function | File | Purpose |
|---|---|---|
| `qs_enqueue_assets()` | `quote-system.php` | Enqueue all frontend CSS with filemtime versions |
| `qs_register_post_type()` | `post-type.php` | Register Quote and Quote Product CPTs |
| `qs_create_default_product_types()` | `taxonomies.php` | Insert default taxonomy terms |
| `qs_register_post_statuses()` | `statuses.php` | Register five custom Quote statuses |
| `qs_append_statuses_to_dropdown()` | `statuses.php` | Add statuses to classic editor dropdown |
| `qs_get_quote_statuses()` | `statuses.php` | Canonical status-label map |
| `qs_update_quote_status()` | `statuses.php` | Validate target and update post status |
| `qs_generate_quote_number()` | `quote-number.php` | Increment sequence and format Quote number |
| `qs_assign_quote_number()` | `quote-number.php` | Assign number on first Quote save |

## Repeaters and display helpers

| Function | Purpose |
|---|---|
| `qs_component_definitions()` | Canonical fields/sanitisers for four repeaters |
| `qs_component_rows()` | Read and re-sanitise one component group |
| `qs_sanitise_component_rows()` | Clean raw component rows |
| `qs_save_component_rows()` | Save all groups |
| `qs_quote_product_label()` | Product ID → title, with text compatibility |
| `qs_quote_component_count()` | Sum quantities |
| `qs_component_rows_by_type()` | Filter rows by Door/Drawer/Bank type |
| `qs_component_rows_with_indices()` | Add display-only original row indices |
| `qs_component_drawer_heights()` | Label saved Drawer Bank heights |
| `qs_component_display_value()` | Format table values |
| `qs_render_quote_component_table()` | Print escaped component table |
| `qs_pdf_component_table()` | Compatibility wrapper for table output |
| `qs_quote_product_image_url()` | Product featured-image URL |
| `qs_quote_summary_groups()` | Build six summary groups |
| `qs_quote_summary_primary()` | Main summary label |
| `qs_quote_summary_secondary()` | Drawer/kickboard detail label |

## Pricing

| Function | Purpose |
|---|---|
| `qs_pricing_meta()` | First existing value from compatible meta keys |
| `qs_pricing_repeater_rows()` | Read ACF-style raw rows or normal arrays |
| `qs_pricing_related_product_id()` | Relationship/object/array/scalar → Product ID |
| `qs_find_quote_product()` | Cached exact-title/type lookup |
| `qs_resolve_quote_product()` | Saved ID/title → Product ID |
| `qs_product_pricing_method()` | Normalized pricing method |
| `qs_product_pricing_source()` | Linked source matrix Product ID |
| `qs_product_matrix_rows()` | Normalized matrix rows |
| `qs_product_matrix_price()` | Inclusive dimension-band lookup |
| `qs_product_fixed_price()` | Fixed amount |
| `qs_product_percentage()` | Percentage adjustment |
| `qs_product_linear_price()` | Linear metre calculation |
| `qs_product_square_price()` | Square metre calculation |
| `qs_product_dimension_price()` | Dispatch by method |
| `qs_quote_paint_product()` | Resolve paint product for Quote |
| `qs_handle_applies_to_profile()` | Finger Pull applicability |
| `qs_price_panel()` | Base + paint + handle for one panel |
| `qs_drawer_bank_heights()` | Expand bank into priced fronts |
| `qs_kickboard_product()` | Resolve material Product |
| `qs_calculate_component_subtotal()` | Trade subtotal and breakdown |
| `qs_recalculate_quote_pricing()` | Save trade/calculated/current subtotal |
| `qs_apply_retail_markup()` | Add 22.22% |
| `qs_calculate_total()` | Apply office adjustments |
| `qs_calculate_deposit()` | Locked value or 30% |
| `qs_calculate_balance()` | Total minus deposit |

## Builder

| Function | Purpose |
|---|---|
| `qs_builder_quote_is_editable()` | New/existing ownership/status gate |
| `qs_builder_supporting_document_ids()` | Validate saved attachment IDs |
| `qs_supporting_document_name()` | Attachment filename/title |
| `qs_supporting_document_icon_url()` | Image/PDF icon |
| `qs_builder_save_supporting_documents()` | Remove associations and upload files |
| `qs_builder_save_quote()` | Validate and persist draft |
| `qs_builder_products()` | Query selectable active Products |
| `qs_builder_input()` | Render simple field |
| `qs_builder_product_picker()` | Render custom Product selection |
| `qs_builder_doors_drawers_editor()` | Render Door/Drawer/Bank editor |
| `qs_builder_stored_component_rows()` | Render hidden rows |
| `qs_builder_end_panels_editor()` | Render End Panel editor |
| `qs_builder_fillers_editor()` | Render Filler editor |
| `qs_builder_kickboards_editor()` | Render Kickboard editor |
| `qs_builder_component_table()` | Dispatch component renderer |
| `qs_builder_ajax_recalculate()` | Logged-in AJAX save/recalculate |
| `qs_quote_builder_shortcode()` | Builder request controller and page |

## Review, dashboards and login

| Function | Purpose |
|---|---|
| `qs_quote_review_can_access()` | Owner/editor review authorization |
| `qs_quote_review_handle_remove_item()` | Secure draft-row removal |
| `qs_review_specification_cards()` | Specification card renderer |
| `qs_review_component_sections()` | Main component review renderer |
| `qs_review_summary_items()` | Summary rows and draft controls |
| `qs_review_admin_summary_actions()` | Status-specific admin controls |
| `qs_quote_review_shortcode()` | Shared review controller/page |
| `qs_my_quotes_status_display()` | Trade-facing label |
| `qs_my_quotes_action()` | Select payment/view/PDF action |
| `qs_my_quotes_table()` | Trade table renderer |
| `qs_my_quotes_shortcode()` | Trade dashboard controller/page |
| `qs_admin_dashboard_status_display()` | Admin-facing label |
| `qs_admin_dashboard_status_bucket()` | Count grouping |
| `qs_admin_dashboard_filter_values()` | Unique meta values for filters |
| `qs_admin_dashboard_order_url()` | Woo order edit URL + filter |
| `qs_admin_dashboard_duplicate_quote()` | Completed Quote → clean draft copy |
| `qs_admin_dashboard_action_button()` | Nonced action form |
| `qs_admin_dashboard_visible_meta_query()` | Exclude archived Quotes |
| `qs_admin_dashboard_handle_action()` | Validate/execute workflow mutation |
| `qs_admin_dashboard_quote_actions()` | Status-specific action menu |
| `qs_admin_dashboard_shortcode()` | Admin dashboard controller/page |
| `qs_quote_submitted_shortcode()` | Thank-you page |
| `qs_quote_login_shortcode()` | Login controller/page |

## WordPress-admin functions

| Function | Purpose |
|---|---|
| `qs_register_metaboxes()` | Add four Quote metaboxes |
| `qs_project_details_metabox()` | Project/contact/attachment UI |
| `qs_cabinet_specifications_metabox()` | Text fallback for Product selections |
| `qs_components_metabox()` | Basic table editor for structured rows |
| `qs_pricing_workflow_metabox()` | Pricing/legacy totals/internal notes UI |
| `qs_save_project_details()` | Save all Quote metabox fields |
| `qs_quote_columns()` | Replace Quote list columns |
| `qs_quote_column_content()` | Quote number/status values |
| `qs_add_pricing_settings_page()` | Add Quote Pricing submenu |
| `qs_render_pricing_settings_page()` | Explain/link Product pricing |

## PDF and email

| Function | Purpose |
|---|---|
| `qs_get_quote_data()` | Common output data projection |
| `qs_can_view_quote_document()` | Owner/editor document gate |
| `qs_generate_quotation_html()` | Template + PDF CSS |
| `qs_generate_jobsheet_html()` | Template + PDF CSS |
| `qs_generate_jobsheet_pdf()` | Render/stream Job Sheet |
| `qs_download_jobsheet_pdf()` | GET route handler |
| `qs_quotation_shortcode()` | Protected HTML preview |
| `qs_generate_quotation_pdf()` | Return rendered Dompdf object |
| `qs_stream_quotation_pdf()` | Stream inline quotation |
| `qs_download_quotation_pdf()` | GET route handler |
| `qs_jobsheet_shortcode()` | Protected HTML preview |
| `qs_get_admin_email()` | WordPress admin email |
| `qs_send_email()` | HTML `wp_mail()` wrapper |
| `qs_send_admin_email()` | Admin recipient wrapper |
| `qs_send_customer_email()` | Quote email recipient wrapper |
| `qs_render_email_template()` | Render template file |
| `qs_email_quote_submitted()` | New submission notification |
| `qs_email_quote_approved()` | Deposit-ready notification |

## WooCommerce

| Function | Purpose |
|---|---|
| `qs_create_payment_order()` | Create/reuse fee-only deposit/balance order |
| `qs_get_quote_payment_url()` | Current pay-for-order URL |
| `qs_handle_payment_complete()` | Payment event → Quote status |

## Registered hooks

| Hook | Callback | Priority/args |
|---|---|---|
| plugin activation | anonymous | Register CPT, flush rules, seed terms |
| `init` | `qs_register_post_type` | default |
| `init` | `qs_register_post_statuses` | default |
| `init` | `qs_download_jobsheet_pdf` | default |
| `init` | `qs_download_quotation_pdf` | default |
| `save_post_quote` | `qs_assign_quote_number` | `10`, 3 args |
| `save_post_quote` | `qs_save_project_details` | default |
| `add_meta_boxes` | `qs_register_metaboxes` | default |
| `admin_footer-post.php` | `qs_append_statuses_to_dropdown` | default |
| `admin_footer-post-new.php` | same | default |
| `admin_menu` | `qs_add_pricing_settings_page` | default |
| `wp_enqueue_scripts` | `qs_enqueue_assets` | `9999` |
| `wp_ajax_qs_builder_recalculate` | `qs_builder_ajax_recalculate` | logged-in only |
| `manage_quote_posts_columns` | `qs_quote_columns` | filter |
| `manage_quote_posts_custom_column` | `qs_quote_column_content` | `10`, 2 args |
| `woocommerce_payment_complete` | `qs_handle_payment_complete` | default |
| `woocommerce_order_status_processing` | same | default |
| `woocommerce_order_status_completed` | same | default |

## Project filter

```php
apply_filters(
    'qs_admin_dashboard_invoice_url',
    $url,
    $order,
    $payment_type,
    $quote_id
);
```

Use it to replace the Admin Dashboard invoice URL without editing the plugin.

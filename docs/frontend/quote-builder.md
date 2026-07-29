# Quote Builder

Source: `frontend/quote-builder.php`
Shortcode: `[quote_builder]`

## Sections

1. Account/page header
2. Trade/Retail pricing mode
3. Project details
4. Door Specifications
5. Doors & Drawers
6. End Panels
7. Fillers
8. Kickboards
9. Custom Requests
10. Supporting Documents
11. Project Notes
12. Sticky Quote Summary

## Save behavior

`qs_builder_save_quote()` always saves the post as `draft`, writes scalar fields, writes all component groups, applies Painted Oak rules, saves pricing mode, optionally processes uploads, and recalculates pricing.

Required server-side conditions:

- valid `qs_save_quote` nonce;
- logged-in user;
- non-empty Project Name;
- access to an existing Quote.

Most other `required` behavior is currently browser validation.

## Edit permissions

For a new quote, any logged-in user can start.

For an existing quote:

- users with `edit_post` can edit;
- otherwise only its author can edit, and only while status is `draft`.

## Product pickers

The Profile, Timber, Handle Profile and Finish controls are custom expandable radio lists.

Each option contains:

- Product ID value;
- Product title/label;
- featured-image swatch;
- selected-state class.

The browser updates the top selection, closes the picker, refreshes summary and schedules pricing.

### Painted Oak

When the selected Timber label contains `paint`:

- Paint Colour appears, becomes enabled/required;
- Finish is hidden and its inputs disabled;
- server save clears `_finish`.

For non-painted timber, server save clears `_paint_colour`.

## Component editors

Visible component forms do not directly use the final WordPress field names. JavaScript creates hidden rows such as:

```html
components[end_panels][0][width]
```

The summary reads those same hidden rows. Editing loads values back into the visible editor; saving rewrites the hidden row.

Identical non-bank components are merged by a signature that excludes quantity. Doors/Drawers merge by exact type, width and height.

## Live pricing

Pricing begins after a Project Name exists. Relevant changes are debounced for 700 ms.

The AJAX request:

- excludes supporting-document files/removal inputs;
- saves a real draft;
- recalculates all pricing;
- returns formatted subtotal;
- updates browser `quote_id`.

If the subtotal stays zero, inspect the Network response and use [Pricing troubleshooting](../data-model/quote-products.md#pricing-troubleshooting).

## Supporting documents

Accepted:

- JPG/JPEG;
- PNG;
- PDF;
- maximum 10 MB per file;
- multiple files.

Browser behavior:

- validates file type/size;
- uses `FileReader` to show a thin **local-read** progress line;
- swaps image/PDF icons;
- supports drag and drop;
- allows staged and existing files to be removed.

Server behavior:

- validates again;
- uploads with `media_handle_upload()`;
- parents attachments to the Quote;
- stores attachment IDs in `_supporting_documents`;
- removing a file only removes its Quote association—it does not delete the Media Library attachment.

The animated line during form submit is an indeterminate visual state, not true network upload percentage.

## Summary controls

Each summary row shows:

- primary dimensions/material;
- optional drawer-bank height details;
- `Qty. n`;
- pen icon;
- trash icon.

Edit scrolls to and loads the correct visible editor. Trash removes the hidden row, reindexes names, refreshes summary and recalculates.

## Redirects

- Save Draft → `/quote-builder/?quote_id={id}&saved=1`
- Review Quote → `/quote-review/?quote_id={id}&saved=1`

## Extending the Builder

When adding a scalar field:

1. add it to the `$fields` sanitiser map in `qs_builder_save_quote()`;
2. add it to `$keys` in `qs_quote_builder_shortcode()`;
3. render its control;
4. add summary behavior if needed;
5. add it to `qs_get_quote_data()`;
6. add review/PDF/email output as required;
7. add WordPress-admin metabox support;
8. document the meta key.

When adding a component field, follow
[Add or change a repeatable component field](../development/common-changes.md#add-or-change-a-repeatable-component-field).

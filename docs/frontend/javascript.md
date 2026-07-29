# JavaScript behavior

There are no standalone JavaScript files. Scripts are printed inline by the PHP screen that owns them.

## Quote Builder script

Location: bottom of `frontend/quote-builder.php`

Responsibilities:

- visible editor ↔ hidden repeater rows;
- row creation, merging, editing, removal and reindexing;
- Drawer Bank field visibility;
- product picker open/close/selection;
- Painted Oak Finish/Paint Colour behavior;
- live summary and pen/trash actions;
- debounced AJAX subtotal;
- drag/drop supporting documents;
- local FileReader progress and staged-file removal.

### Data attributes are the API

Important attributes:

```text
data-component
data-editor-type
data-editor-field
data-component-field
data-select-type
data-edge-position
data-picker-toggle
data-option-label
data-summary-action
data-row-index
data-upload-file-list
```

Changing PHP markup names without updating JavaScript will silently break behavior.

### Hidden row naming

`reindex()` rewrites the first numeric bracket in each field name. Expected format:

```text
components[component_name][row_number][field_name]
```

Do not add another numeric bracket before the row index.

### Pricing request

- delay: 700 ms;
- transport: `fetch()` + `FormData`;
- action: `qs_builder_recalculate`;
- authentication: WordPress logged-in AJAX plus Builder nonce in the form;
- previous request: aborted with `AbortController`.

Calculation intentionally waits until Project Name is non-empty.

### Upload progress meaning

`FileReader.onprogress` measures the browser reading the local file. It does not report bytes uploaded to WordPress. On final submit, `.is-submitting` animates the line indefinitely.

## Admin Dashboard script

Location: bottom of `frontend/admin-dashboard.php`

- opens one expandable action row;
- closes any previously open row;
- maintains `aria-expanded` and `hidden`;
- asks `window.confirm()` for forms with `data-confirm`.

## Quote Review script

Location: bottom of `frontend/quote-review.php`

- asks `window.confirm()` for summary forms with `data-confirm`.

All action processing remains server-side.

## My Quotes script

No separate script block. Draft delete confirmation is currently an inline `onsubmit` attribute.

## Login script

Location: bottom of `frontend/login.php`

- toggles password input between `password` and `text`;
- updates `aria-pressed` and accessible label.

## WordPress-admin component script

Location: `meta-boxes.php`

- clones the first repeater table row;
- reindexes input names;
- removes/clears rows.

This admin editor exposes fewer Drawer Bank height fields than the frontend. Prefer the frontend for detailed component changes.

## Safely moving scripts to files

If extracting JavaScript later:

1. create one file per screen;
2. conditionally enqueue it where the shortcode is present;
3. pass `ajaxUrl`, Quote ID and icon URLs with `wp_add_inline_script()` or `wp_localize_script()`;
4. preserve data attributes and nonce fields;
5. load with `defer` or in the footer;
6. test repeated shortcodes/blocks on one page, because current code often begins with `document.querySelector()` and assumes one instance.

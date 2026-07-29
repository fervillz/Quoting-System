# CSS and visual styling

## What is actually loaded

`qs_enqueue_assets()` globally enqueues these unminified files on every frontend request:

1. `base.css`
2. `quote-builder.css`
3. `quote-review.css`
4. `quote-submitted.css`
5. `quotation.css`
6. `jobsheet.css`
7. `admin-dashboard.css`
8. `my-quotes.css`
9. `login.css`

Each dependency uses `filemtime()` as its version, so a changed file should bypass browser cache automatically.

The `qs-*.min.css` files are **not loaded** and many are stale. Editing them has no frontend effect.

PDF CSS is different: `pdf.php` reads `quotation-pdf.css` or `jobsheet-pdf.css` and injects it into the generated HTML.

## Brand variables

`base.css` defines:

```css
--qs-wine: #561724;
--qs-rose: #cfa096;
--qs-blush: #ead3cf;
--qs-paper: #f5f5f4;
--qs-ink: #40586b;
--qs-line: #d8d8d6;
--qs-serif: "Cormorant Garamond", "Times New Roman", serif;
--qs-sans: "GT Walsheim", "Montserrat", "Avenir Next", Arial, sans-serif;
```

`quote-builder.css` declares a second `:root` and currently uses `"GTWalsheim"` as its first sans font. The exact font-family naming must match the site theme's `@font-face`.

## Scope map

| Wrapper | CSS file |
|---|---|
| `.qs-builder-shell` | `quote-builder.css` |
| `.qs-review-page` | `quote-review.css` |
| `.qs-my-quotes` | `my-quotes.css` |
| `.qs-admin-dashboard` | `admin-dashboard.css` |
| `.qs-login-page` | `login.css` |
| `.quote-sub-wrap` | `quote-submitted.css` |
| `.qs-pdf` | PDF-specific CSS |

Keep new selectors under the page wrapper to avoid changing WooCommerce or the theme.

## Builder landmarks

| Feature | Selector |
|---|---|
| Two-column layout | `.qs-builder-form` |
| Product dropdown | `.qs-product-picker`, `.is-open`, `.qs-product-options` |
| Selected option | `.qs-product-option.is-selected` |
| Type buttons | `.qs-type-actions button.is-active` |
| Edge diagram | `.qs-edge-selector`, `.qs-edge-choice` |
| Upload dropzone | `.qs-upload-dropzone` |
| Upload progress | `.qs-upload-progress-value` |
| Sticky summary | `.qs-builder-summary` |
| Summary item/actions | `.qs-summary-item`, `.qs-summary-action` |
| Calculating subtotal | `[data-qs-subtotal].is-calculating` if styled |

## Responsive breakpoints

| File | Main breakpoints |
|---|---|
| Builder | 900px, 560px |
| Review | 1000px, 800px, 520px |
| My Quotes | 900px, 600px |
| Admin Dashboard | 1050px, 900px, 650px |
| Login | 900px, 700px |
| Thank You | 600px |

Tables generally retain a minimum width inside an overflow wrapper on small screens.

## PDF CSS rules

Dompdf does not support browser CSS as completely as Chromium. The templates intentionally use tables for major layout.

When editing PDF CSS:

- prefer points/pixels and simple table layout;
- avoid JavaScript, CSS Grid and complex Flexbox;
- keep external images accessible if `isRemoteEnabled` is needed;
- test long names, notes and many rows;
- render the actual PDF, not only the `[quotation]` preview.

## Debugging style conflicts

1. Confirm the element is inside the expected wrapper.
2. Confirm the unminified file appears in browser Network tools.
3. Check computed `font-family`, width, margin and `box-sizing`.
4. Look for page-builder/theme `!important` rules.
5. Check the site's real font-face family name.
6. Clear optimization/CDN caches even though filemtime changed.
7. Test at each documented breakpoint.

## Recommended future cleanup

- enqueue only the CSS needed by the current shortcode/page;
- consolidate duplicate variables;
- remove or regenerate stale minified assets;
- extract inline style attributes from `quote-submitted.php`;
- use one canonical spelling for GT Walsheim.

# PDFs

Quote System generates:

- a customer-facing quotation;
- an internal manufacturing job sheet.

## Data projection

`qs_get_quote_data($quote_id)` is the common data adapter. It returns:

- identity/project/contact fields;
- author and dates;
- human-readable pricing mode;
- Product labels and raw Product IDs;
- structured component groups;
- adjustments and calculated total/deposit/balance.

When a new field must appear in more than one output, add it here first.

## Quotation

| Part | File/function |
|---|---|
| HTML template | `templates/quotation.php` |
| PDF CSS | `assets/css/quotation-pdf.css` |
| HTML generator | `qs_generate_quotation_html()` |
| Dompdf generator | `qs_generate_quotation_pdf()` |
| Stream | `qs_stream_quotation_pdf()` |
| GET handler | `qs_download_quotation_pdf()` |
| Preview shortcode | `[quotation]` |

Download route:

```text
/?download_quote_pdf={quote_id}
```

Filename:

```text
quotation-{quote_number}.pdf
```

The PDF streams inline (`Attachment => false`), even where a button says “Download”.

Quotation includes pricing mode and subtotal. It does not currently display shipping, discount, additional charges or calculated total in the right-side summary.

## Job Sheet

| Part | File/function |
|---|---|
| HTML template | `templates/jobsheet.php` |
| PDF CSS | `assets/css/jobsheet-pdf.css` |
| HTML generator | `qs_generate_jobsheet_html()` |
| Stream/generator | `qs_generate_jobsheet_pdf()` |
| GET handler | `qs_download_jobsheet_pdf()` |
| Preview shortcode | `[jobsheet]` |

Download route:

```text
/?download_jobsheet_pdf={quote_id}
```

Filename:

```text
job-sheet-{quote_number}.pdf
```

## Authorization

Both documents require:

- a logged-in user;
- valid Quote CPT;
- user can `edit_post`, or is the Quote author.

Download routes do not use a nonce because they are read-only and capability/ownership protected.

## Dompdf configuration

Both use:

```php
$options->set( 'isRemoteEnabled', true );
$options->set( 'defaultFont', 'DejaVu Sans' );
$dompdf->setPaper( 'A4', 'portrait' );
```

Dompdf is loaded from bundled `dompdf/autoload.inc.php`.

## Template conventions

- Major layouts use HTML tables for renderer compatibility.
- Escape user values with `esc_html()`/`nl2br()`.
- Use shared component tables from `template-functions.php`.
- Do not read `$_GET` or post meta throughout templates; pass `$quote_id` and `$data`.
- Do not display `_internal_notes` in customer output.

## Add a field to PDFs

1. Save it on the Quote.
2. Add it to `qs_get_quote_data()`.
3. Add escaped output to one/both templates.
4. Add CSS to the corresponding PDF CSS, not only browser preview CSS.
5. Generate a PDF with an empty value, a normal value and an unusually long value.

## PDF debugging

If output is blank/corrupt:

1. Turn off visible PHP warnings/notices; bytes before PDF headers corrupt streams.
2. Confirm Dompdf classes load.
3. Call the matching HTML preview shortcode.
4. Inspect `qs_get_quote_data()` for the Quote.
5. Confirm CSS file exists/readable.
6. Check external image/font URLs if used.
7. Check PHP memory and execution-time logs.

If layout differs only in PDF, simplify CSS and use table layout. Browser preview is not proof that Dompdf can render a rule.

# Quote System PDF merger runtime

This folder contains third-party PDF import/merge runtime files used only after
Dompdf generates the Loughlin Furniture quotation.

- Setasign FPDI 2.6.9 — MIT — https://github.com/Setasign/FPDI
- Setasign FPDF 1.9.0 — permissive FPDF license — https://github.com/Setasign/FPDF

Dompdf remains the Quote System PDF generator. FPDI/FPDF concatenate the
generated quotation with uploaded PDF supporting documents, retaining each
imported page's original dimensions and orientation.

Only runtime files required by the FPDI + FPDF path are bundled here.

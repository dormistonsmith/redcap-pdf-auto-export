# PDF Auto Export
David Ormiston-Smith, The University of Melbourne https://www.unimelb.edu.au
https://github.com/dormistonsmith/redcap-pdf-auto-export

## Description

This module enables the automatic creation of custom PDF files when a REDCap form is saved (with optional trigger logic).

The module user must provide the PDF content as raw HTML/CSS, and as such is best suited to users who are at least passingly conversant in those languages. Piping of variable values is supported.

The supplied HTML/CSS is rendered as a PDF by the PHP dompdf library https://github.com/dompdf/dompdf.

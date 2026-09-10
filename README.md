# PDF Auto Export
David Ormiston-Smith, The University of Melbourne https://www.unimelb.edu.au

https://github.com/dormistonsmith/redcap-pdf-auto-export

## Description

This module enables the automatic creation of custom PDF files when a REDCap form is saved (with optional trigger logic).

The module user must provide the desired content as raw HTML/CSS, and as such is best suited to users who are at least passingly conversant in those languages. Piping of variable values is supported.

The supplied HTML/CSS is rendered as a PDF by the PHP dompdf library https://github.com/dompdf/dompdf.

To include images in the PDF, you can either
 * Convert them to Base-64 and then pop the encoded output in the src attribute of the <img> element.
 * Upload the image files either to a web server owned by your institution or to a third-party hosting service and then include the URL in the src attribute as normal.

Any warnings or errors encountered when trying to create the PDF are saved to the project audit log.

This EM has not been tested with repeating forms and may not function as expected if used in a project that features them.

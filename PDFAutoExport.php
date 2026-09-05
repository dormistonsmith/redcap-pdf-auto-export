<?php

namespace Unimelb\PDFAutoExport;

require_once("dompdf/autoload.inc.php");

use REDCap;
use Project;
use ExternalModules\AbstractExternalModule;
use Dompdf\Dompdf;

class PDFAutoExport extends AbstractExternalModule {

	public function redcap_save_record($project_id, $record = null, $instrument, $event_id, $group_id = null, $survey_hash = null, $response_id = null, $repeat_instance = 1) {

		$all_settings = $this->getSubSettings('instance-config');

		foreach ($all_settings as $i => $settings) {

			$trigger_logic_empty_or_satisifed =
				(empty(trim($settings['trigger-logic'])) ? true : \REDCap::evaluateLogic($settings['trigger-logic'], $project_id, $record, $event_id, $repeat_instance));

			// REDCap::evaluateLogic returns NULL if the logic is poorly formed or invalid
			if ($trigger_logic_empty_or_satisifed === null) {

				\REDCap::logEvent("PDFAutoExport module: ERROR (instance #" . ($i+1) . ")", "Invalid trigger logic supplied", null, $record, $event_id);
				continue;
			}

			if (
				$settings['instance-enabled'] === true &&
				array_search($instrument, $settings['trigger-form']) !== false &&
				$trigger_logic_empty_or_satisifed === true &&
				!empty($settings['pdf-file-field']) &&
				!empty($settings['content-markup'])
			) {

				$data_get_params = [
					"project_id" => $project_id,
					"return_format" => "array",
					"records" => $record,
					"fields" => [$settings['pdf-file-field']],
					"events" => $event_id
				];
				$record_data = \REDCap::getData($data_get_params);
				$edoc_id = $record_data[$record][$event_id][$settings['pdf-file-field']];

				// Depending on EM configuration, if a file has already been saved to the upload field, don't overwrite it.
				if (!empty($edoc_id) && $settings['do-not-overwrite'] === true) {

					 \REDCap::logEvent("PDFAutoExport module: WARNING (instance #" . ($i+1) . ")", "A file has already been uploaded to [{$settings['pdf-file-field']}] (the selected file upload field); skipping this instance.", null, $record, $event_id);
					continue;
				}

				$pdf_markup = trim($settings['content-markup']);

				 // The field containing the PDF content markup must have at least a small amount of meaningful content.
				if (strlen(trim($pdf_markup)) < 2) {

					\REDCap::logEvent("PDFAutoExport module: WARNING (instance #" . ($i+1) . ")", "No/insufficient content markup; can't render to PDF", null, $record, $event_id);
					continue;
				}

				// "Pipe" in any (smart) variables referenced in the markup.
				$pdf_markup = \Piping::replaceVariablesInLabel($pdf_markup, $record, $event_id, $instance, null, true, $project_id, false);

				// Wrap the markup in <html><body> .. </body></html> tags; REDCap strips these tags out of stored config field data.
				if (preg_match('/.*<html>.*<body>.*<\/body>.*<\/html>.*/i', $pdf_markup) === 0)
					$pdf_markup = "<html><body>{$pdf_markup}</body></html>";

				// Attempt to render the markup as a PDF using the DOMPDF extension.
				try {

					$tmpdir = sys_get_temp_dir();

					$page_size = (empty($settings['pdf-page-size']) ? "A4" : $settings['pdf-page-size']);
					$page_orientation = (empty($settings['pdf-page-orientation']) ? "portrait" : $settings['pdf-page-orientation']);

					$dompdf = new Dompdf([
						'isRemoteEnabled' => true,
						'fontDir' => $tmpdir,
						'fontCache' => $tmpdir,
						'tempDir' => $tmpdir,
						'chroot' => $tmpdir,
					]);
					$dompdf->setPaper($page_size, $page_orientation);
					$dompdf->loadHtml($pdf_markup);
					$dompdf->render();
				} catch (\Throwable $e) {
						
					\REDCap::logEvent("PDFAutoExport module: ERROR (instance #" . ($i+1) . ")", "DOMPDF failed to render the content markup as PDF data, returning the following error: " . $e->getMessage(), null, $record, $event_id);
 					continue;
				}

				// Write the PDF to a temporary file and then save it the file store.
				try {
					$tmpfile_handle = tmpfile();
					fwrite($tmpfile_handle, $dompdf->output());

					// Generate a unique but not identifying (as in, not linked to the record ID) filename for the PDF.
					$j = 0;

					$filename_prefix = (empty($settings['pdf-filename-prefix']) ? "pdfexport" : $settings['pdf-filename-prefix']);

					do {
						$filename = $filename_prefix . "_" . date("YmdHis") . mt_rand(1, PHP_INT_MAX) . ".pdf";
						$j++;
					}  while ($j < 100 && file_exists(EDOC_PATH . $filename));

					if ($j == 100)
						   throw new Exception("Unable to generate unique filename");	

					else
						$new_edoc_id = \REDCap::storeFile(stream_get_meta_data($tmpfile_handle)['uri'], $project_id, $filename);

					fclose($tmpfile_handle);

				} catch (\Throwable $e) {

					\REDCap::logEvent("PDFAutoExport module: ERROR (instance #" . ($i+1) . ")", "Failed to save rendered content to PDF File: " . $e->getMessage(), null, $record, $event_id);
					continue;
				}

				// Link the new PDF file to the listed file upload field.
				if (!empty($new_edoc_id)) {

					try {

						\REDCap::addFileToField($new_edoc_id, $project_id, $record, $settings['pdf-file-field'], $event_id);
					} catch (\Throwable $e) {

						\REDCap::logEvent("PDFAutoExport module: ERROR (instance #" . ($i+1) . ")", "Failed to upload new PDF file to file upload field '{$settings['pdf-file-field']}'", null, $record, $event_id);
						continue;
					}
				}
			}
		}
	}
}

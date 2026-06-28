<?php
namespace App\Helpers;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Parses uploaded .xlsx files for bulk geography import (Module 2).
 */
class SpreadsheetImport
{
    /**
     * Parse an uploaded geography .xlsx file.
     *
     * Expected columns (case-insensitive): State, District, Taluk (Taluk is optional).
     * Returns:
     *   [
     *     'rows'   => [['state'=>..,'district'=>..,'taluk'=>..,'rowNum'=>..], ...],
     *     'errors' => [['row'=>..,'error'=>..], ...],
     *   ]
     */
    public static function parseGeography(string $filePath): array
    {
        $rows   = [];
        $errors = [];

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet       = $spreadsheet->getActiveSheet();
            // toArray(nullValue, calculateFormulas, formatData, returnCellRef)
            $data = $sheet->toArray(null, true, true, true);

            $header = null;
            $rowNum = 0;

            foreach ($data as $row) {
                $rowNum++;

                if ($header === null) {
                    // First non-empty row becomes the header map.
                    $header = array_map('strtolower', array_map('trim', $row));
                    continue;
                }

                $mapped = array_combine($header, $row);

                $state    = trim((string)($mapped['state']    ?? ''));
                $district = trim((string)($mapped['district'] ?? ''));
                $taluk    = trim((string)($mapped['taluk']    ?? ''));

                // Skip entirely blank rows.
                if ($state === '' && $district === '' && $taluk === '') {
                    continue;
                }

                if ($state === '') {
                    $errors[] = ['row' => $rowNum, 'error' => 'State is required'];
                    continue;
                }

                $rows[] = compact('state', 'district', 'taluk', 'rowNum');
            }
        } catch (\Throwable $e) {
            $errors[] = ['row' => 0, 'error' => 'Could not read file: ' . $e->getMessage()];
        }

        return compact('rows', 'errors');
    }

    /**
     * Build a two-sheet .xlsx onboarding template.
     * Sheet 1 ("Upload Here"): column headers for staff to fill.
     * Sheet 2 ("Valid Values"): reference lists from master data.
     * Returns the path to a temp file that the caller must unlink after use.
     */
    public static function buildOnboardingTemplate(array $masterData): string
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        // ── Sheet 1: Upload Here ──────────────────────────────────────────
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Upload Here');
        $headers = [
            'A1' => 'First Name',
            'B1' => 'Last Name',
            'C1' => 'Date of Birth (DD/MM/YYYY)',
            'D1' => 'Mobile',
            'E1' => 'Gender',
            'F1' => 'Academic Year',
            'G1' => 'Class',
            'H1' => 'Section (optional)',
            'I1' => 'Admission Date (DD/MM/YYYY)',
        ];
        foreach ($headers as $cell => $label) {
            $sheet1->setCellValue($cell, $label);
        }
        // Bold headers
        $sheet1->getStyle('A1:I1')->getFont()->setBold(true);
        foreach (range('A', 'I') as $col) {
            $sheet1->getColumnDimension($col)->setAutoSize(true);
        }

        // ── Sheet 2: Valid Values ─────────────────────────────────────────
        $spreadsheet->createSheet();
        $sheet2 = $spreadsheet->getSheet(1);
        $sheet2->setTitle('Valid Values');

        $sheet2->setCellValue('A1', 'Gender (use exactly as shown)');
        $sheet2->getStyle('A1')->getFont()->setBold(true);
        $genders = ['Male', 'Female', 'Other'];
        foreach ($genders as $i => $g) {
            $sheet2->setCellValue('A' . ($i + 2), $g);
        }

        $sheet2->setCellValue('B1', 'Academic Year');
        $sheet2->getStyle('B1')->getFont()->setBold(true);
        foreach (($masterData['academic_years'] ?? []) as $i => $ay) {
            $sheet2->setCellValue('B' . ($i + 2), $ay['display'] ?? $ay['value']);
        }

        $sheet2->setCellValue('C1', 'Class');
        $sheet2->getStyle('C1')->getFont()->setBold(true);
        foreach (($masterData['classes'] ?? []) as $i => $cls) {
            $sheet2->setCellValue('C' . ($i + 2), $cls['display'] ?? $cls['value']);
        }

        $sheet2->setCellValue('D1', 'Section');
        $sheet2->getStyle('D1')->getFont()->setBold(true);
        foreach (($masterData['sections'] ?? []) as $i => $sec) {
            $sheet2->setCellValue('D' . ($i + 2), $sec['display'] ?? $sec['value']);
        }

        foreach (['A', 'B', 'C', 'D'] as $col) {
            $sheet2->getColumnDimension($col)->setAutoSize(true);
        }

        // Return to first sheet
        $spreadsheet->setActiveSheetIndex(0);

        $tmpFile = sys_get_temp_dir() . '/sis_onboarding_template_' . uniqid() . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tmpFile);
        return $tmpFile;
    }

    /**
     * Parse an onboarding .xlsx file.
     * Expects headers matching: first_name / "First Name", last_name, dob, mobile,
     * gender, academic_year, class, section, admission_date (case-insensitive, spaces→underscore).
     * Returns ['rows' => [...], 'errors' => [...]]
     * Each row has '_row_number' key with the spreadsheet row number.
     */
    public static function parseOnboarding(string $filePath): array
    {
        $rows   = [];
        $errors = [];

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $data  = $sheet->toArray(null, true, true, false);

            if (empty($data)) {
                return ['rows' => [], 'errors' => []];
            }

            // Normalise header row: trim, lowercase, spaces→underscore
            $rawHeaders = array_shift($data);
            $headers = array_map(function (string $h): string {
                return str_replace(' ', '_', strtolower(trim((string)$h)));
            }, $rawHeaders);

            $rowNum = 1; // header was row 1
            foreach ($data as $row) {
                $rowNum++;

                // Skip blank rows
                $vals = array_filter(array_map('trim', array_map('strval', $row)));
                if (empty($vals)) {
                    continue;
                }

                $mapped = array_combine($headers, $row);

                // Normalise common column aliases
                $aliases = [
                    'first_name'     => ['firstname', 'first_name'],
                    'last_name'      => ['lastname', 'last_name', 'surname'],
                    'dob'            => ['dob', 'date_of_birth_(dd/mm/yyyy)', 'date_of_birth'],
                    'mobile'         => ['mobile', 'mobile_number', 'phone'],
                    'gender'         => ['gender'],
                    'academic_year'  => ['academic_year', 'year'],
                    'class'          => ['class', 'class_name'],
                    'section'        => ['section_(optional)', 'section'],
                    'admission_date' => ['admission_date_(dd/mm/yyyy)', 'admission_date'],
                ];

                $out = ['_row_number' => $rowNum];
                foreach ($aliases as $canonical => $possible) {
                    foreach ($possible as $p) {
                        if (isset($mapped[$p]) && $mapped[$p] !== null && $mapped[$p] !== '') {
                            $out[$canonical] = trim((string)$mapped[$p]);
                            break;
                        }
                    }
                    if (!isset($out[$canonical])) {
                        $out[$canonical] = '';
                    }
                }

                // Normalise gender to lowercase
                $out['gender'] = strtolower($out['gender']);

                $rows[] = $out;
            }
        } catch (\Throwable $e) {
            $errors[] = ['row' => 0, 'error' => 'Could not read file: ' . $e->getMessage()];
        }

        return compact('rows', 'errors');
    }

    /**
     * Build a .xlsx error report from failed rows.
     * Each row gets the original data plus an "Errors" column.
     * Returns temp file path; caller must unlink.
     */
    public static function buildErrorReport(array $failedRows): string
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Upload Errors');

        $headers = ['Row', 'First Name', 'Last Name', 'DOB', 'Mobile', 'Gender',
                    'Academic Year', 'Class', 'Section', 'Admission Date', 'Errors'];
        foreach (array_values($headers) as $i => $h) {
            $sheet->setCellValueByColumnAndRow($i + 1, 1, $h);
        }
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);

        $rowIdx = 2;
        foreach ($failedRows as $fr) {
            $d = $fr['data'] ?? [];
            $errs = $fr['errors'] ?? [];
            $errStr = implode('; ', $errs);
            $sheet->setCellValueByColumnAndRow(1,  $rowIdx, $fr['row'] ?? '');
            $sheet->setCellValueByColumnAndRow(2,  $rowIdx, $d['first_name'] ?? '');
            $sheet->setCellValueByColumnAndRow(3,  $rowIdx, $d['last_name'] ?? '');
            $sheet->setCellValueByColumnAndRow(4,  $rowIdx, $d['dob'] ?? '');
            $sheet->setCellValueByColumnAndRow(5,  $rowIdx, $d['mobile'] ?? '');
            $sheet->setCellValueByColumnAndRow(6,  $rowIdx, $d['gender'] ?? '');
            $sheet->setCellValueByColumnAndRow(7,  $rowIdx, $d['academic_year'] ?? '');
            $sheet->setCellValueByColumnAndRow(8,  $rowIdx, $d['class'] ?? '');
            $sheet->setCellValueByColumnAndRow(9,  $rowIdx, $d['section'] ?? '');
            $sheet->setCellValueByColumnAndRow(10, $rowIdx, $d['admission_date'] ?? '');
            $sheet->setCellValueByColumnAndRow(11, $rowIdx, $errStr);
            $rowIdx++;
        }

        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $tmpFile = sys_get_temp_dir() . '/sis_error_report_' . uniqid() . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tmpFile);
        return $tmpFile;
    }
}

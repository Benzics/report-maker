<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\GeneratedReport;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Throwable;

class GenerateReportJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 300; // 5 minutes timeout

    public $tries = 3; // Retry up to 3 times

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $documentId,
        public int $userId,
        public array $selectedColumns,
        public ?string $filterColumn,
        public ?string $filterValue,
        public ?string $filterColumn2,
        public ?string $filterValue2,
        public ?string $filterColumn3,
        public ?string $filterValue3,
        public ?string $filterValueStart,
        public ?string $filterValueEnd,
        public ?string $filterValue2Start,
        public ?string $filterValue2End,
        public ?string $filterValue3Start,
        public ?string $filterValue3End,
        public string $tableStyle,
        public string $sessionId
    ) {
        //
    }

    /**
     * Get color scheme for the selected table style
     */
    private function getTableStyleColors(string $tableStyle): array
    {
        $styles = [
            // Light styles
            'table_style_light_1' => [
                'header_bg' => 'FFFFFF',
                'header_text' => '000000',
                'row1_bg' => 'FFFFFF',
                'row2_bg' => 'F2F2F2',
                'border' => 'D9D9D9',
            ],
            'table_style_light_2' => [
                'header_bg' => 'FFFFFF',
                'header_text' => '000000',
                'row1_bg' => 'FFFFFF',
                'row2_bg' => 'F8F9FA',
                'border' => 'DEE2E6',
            ],
            'table_style_light_3' => [
                'header_bg' => 'FFFFFF',
                'header_text' => '000000',
                'row1_bg' => 'FFFFFF',
                'row2_bg' => 'F1F3F4',
                'border' => 'E0E0E0',
            ],
            'table_style_light_4' => [
                'header_bg' => 'FFFFFF',
                'header_text' => '000000',
                'row1_bg' => 'FFFFFF',
                'row2_bg' => 'F5F5F5',
                'border' => 'CCCCCC',
            ],
            'table_style_light_5' => [
                'header_bg' => 'FFFFFF',
                'header_text' => '000000',
                'row1_bg' => 'FFFFFF',
                'row2_bg' => 'F0F0F0',
                'border' => 'B3B3B3',
            ],
            
            // Medium styles
            'table_style_medium_1' => [
                'header_bg' => '4472C4',
                'header_text' => 'FFFFFF',
                'row1_bg' => 'FFFFFF',
                'row2_bg' => 'E7E6F7',
                'border' => '8EA9DB',
            ],
            'table_style_medium_2' => [
                'header_bg' => '366092',
                'header_text' => 'FFFFFF',
                'row1_bg' => 'FFFFFF',
                'row2_bg' => 'D9E2F3',
                'border' => '8FAADB',
            ],
            'table_style_medium_3' => [
                'header_bg' => '70AD47',
                'header_text' => 'FFFFFF',
                'row1_bg' => 'FFFFFF',
                'row2_bg' => 'E2EFDA',
                'border' => 'A9D18E',
            ],
            'table_style_medium_4' => [
                'header_bg' => 'C55A11',
                'header_text' => 'FFFFFF',
                'row1_bg' => 'FFFFFF',
                'row2_bg' => 'FCE4D6',
                'border' => 'E2A76F',
            ],
            'table_style_medium_5' => [
                'header_bg' => '5B9BD5',
                'header_text' => 'FFFFFF',
                'row1_bg' => 'FFFFFF',
                'row2_bg' => 'DEEBF7',
                'border' => '9CC2E5',
            ],
            'table_style_medium_6' => [
                'header_bg' => 'A5A5A5',
                'header_text' => 'FFFFFF',
                'row1_bg' => 'FFFFFF',
                'row2_bg' => 'F2F2F2',
                'border' => 'D9D9D9',
            ],
            'table_style_medium_7' => [
                'header_bg' => '7F7F7F',
                'header_text' => 'FFFFFF',
                'row1_bg' => 'FFFFFF',
                'row2_bg' => 'F2F2F2',
                'border' => 'D9D9D9',
            ],
            'table_style_medium_8' => [
                'header_bg' => 'FFC000',
                'header_text' => '000000',
                'row1_bg' => 'FFFFFF',
                'row2_bg' => 'FFF2CC',
                'border' => 'FFD966',
            ],
            'table_style_medium_9' => [
                'header_bg' => 'E74C3C',
                'header_text' => 'FFFFFF',
                'row1_bg' => 'FFFFFF',
                'row2_bg' => 'FADBD8',
                'border' => 'F1948A',
            ],
            'table_style_medium_10' => [
                'header_bg' => '9B59B6',
                'header_text' => 'FFFFFF',
                'row1_bg' => 'FFFFFF',
                'row2_bg' => 'EBD4F4',
                'border' => 'C39BD3',
            ],
            
            // Dark styles
            'table_style_dark_1' => [
                'header_bg' => '2F2F2F',
                'header_text' => 'FFFFFF',
                'row1_bg' => 'FFFFFF',
                'row2_bg' => 'F2F2F2',
                'border' => 'D9D9D9',
            ],
            'table_style_dark_2' => [
                'header_bg' => '1F4E79',
                'header_text' => 'FFFFFF',
                'row1_bg' => 'FFFFFF',
                'row2_bg' => 'D9E2F3',
                'border' => '8FAADB',
            ],
            'table_style_dark_3' => [
                'header_bg' => '4F6228',
                'header_text' => 'FFFFFF',
                'row1_bg' => 'FFFFFF',
                'row2_bg' => 'E2EFDA',
                'border' => 'A9D18E',
            ],
            'table_style_dark_4' => [
                'header_bg' => '7C3A00',
                'header_text' => 'FFFFFF',
                'row1_bg' => 'FFFFFF',
                'row2_bg' => 'FCE4D6',
                'border' => 'E2A76F',
            ],
            'table_style_dark_5' => [
                'header_bg' => '1F4E79',
                'header_text' => 'FFFFFF',
                'row1_bg' => 'FFFFFF',
                'row2_bg' => 'DEEBF7',
                'border' => '9CC2E5',
            ],
        ];

        // Return the style or default to medium_2 if not found
        return $styles[$tableStyle] ?? $styles['table_style_medium_2'];
    }

    /**
     * Apply table styling to the worksheet
     */
    private function applyTableStyling($worksheet, array $data, string $tableStyle): void
    {
        $colors = $this->getTableStyleColors($tableStyle);
        $highestRow = count($data) + 1; // +1 for header row
        $highestColumn = $worksheet->getHighestColumn();

        // Style the header row (row 1)
        $headerRange = 'A1:' . $highestColumn . '1';
        $worksheet->getStyle($headerRange)->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $colors['header_bg']],
            ],
            'font' => [
                'color' => ['rgb' => $colors['header_text']],
                'bold' => true,
                'size' => 11,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => $colors['border']],
                ],
            ],
        ]);

        // Style data rows with alternating colors
        for ($row = 2; $row <= $highestRow; $row++) {
            $isEvenRow = ($row % 2 === 0);
            $rowRange = 'A' . $row . ':' . $highestColumn . $row;
            
            $backgroundColor = $isEvenRow ? $colors['row2_bg'] : $colors['row1_bg'];
            
            $worksheet->getStyle($rowRange)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $backgroundColor],
                ],
                'font' => [
                    'size' => 10,
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => $colors['border']],
                    ],
                ],
            ]);
        }

        // Set column widths to auto-size
        foreach (range('A', $highestColumn) as $column) {
            $worksheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $user = User::find($this->userId);
            if (! $user) {
                Log::error('User not found for report generation', ['user_id' => $this->userId]);

                return;
            }

            $document = Document::where('id', $this->documentId)
                ->where('user_id', $this->userId)
                ->first();

            if (! $document) {
                Log::error('Document not found for report generation', [
                    'document_id' => $this->documentId,
                    'user_id' => $this->userId,
                ]);

                return;
            }

            // Process the report data
            $reportData = $this->processReportData($document);

            if (empty($reportData)) {
                $this->dispatchError('No data found matching your criteria.');

                return;
            }

            // Create the Excel file
            $filePath = $this->createExcelFile($reportData, $document);

            // Generate a unique filename
            $fileName = 'report_'.$document->id.'_'.time().'.xlsx';

            // Store the file
            $storedPath = 'reports/'.$fileName;
            Storage::disk('local')->put($storedPath, file_get_contents($filePath));

            // Clean up temporary file
            unlink($filePath);

            // Create the generated report record
            $generatedReport = GeneratedReport::create([
                'document_id' => $document->id,
                'user_id' => $this->userId,
                'name' => 'Report from '.$document->original_name,
                'description' => $this->getReportDescription($document),
                'selected_columns' => $this->selectedColumns,
                'filter_column' => $this->filterColumn,
                'filter_value' => $this->filterValue,
                'filter_column2' => $this->filterColumn2,
                'filter_value2' => $this->filterValue2,
                'filter_column3' => $this->filterColumn3,
                'filter_value3' => $this->filterValue3,
                'filter_value_start' => $this->filterValueStart,
                'filter_value_end' => $this->filterValueEnd,
                'filter_value2_start' => $this->filterValue2Start,
                'filter_value2_end' => $this->filterValue2End,
                'filter_value3_start' => $this->filterValue3Start,
                'filter_value3_end' => $this->filterValue3End,
                'table_style' => $this->tableStyle,
                'file_path' => $storedPath,
                'file_name' => $fileName,
                'file_size' => Storage::disk('local')->size($storedPath),
                'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'is_saved' => true,
            ]);

            // Dispatch success event
            $this->dispatchSuccess($generatedReport);

        } catch (Throwable $exception) {
            Log::error('Failed to generate report in job', [
                'document_id' => $this->documentId,
                'user_id' => $this->userId,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            $this->dispatchError('Failed to generate report: '.$exception->getMessage());
        }
    }

    /**
     * Process the report data based on selected columns and filters
     */
    private function processReportData(Document $document): array
    {
        try {
            // Get file contents from storage
            $fileContents = Storage::disk($document->disk)->get($document->path);
            if ($fileContents === null) {
                return [];
            }

            // Create a temporary file for PhpSpreadsheet
            $tempPath = tempnam(sys_get_temp_dir(), 'excel_');
            file_put_contents($tempPath, $fileContents);

            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(false); // We need to read formatting for dates
            $reader->setReadEmptyCells(false);

            $spreadsheet = $reader->load($tempPath);
            $worksheet = $spreadsheet->getActiveSheet();
            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();

            // Get all data while preserving original formatting
            $allData = [];
            for ($row = 1; $row <= $highestRow; $row++) {
                $rowData = [];
                for ($col = 'A'; $col <= $highestColumn; $col++) {
                    $cell = $worksheet->getCell($col.$row);
                    $rawValue = $cell->getValue();
                    $formattedValue = $cell->getFormattedValue();

                    // For date columns, we need to handle both raw Excel serial numbers and formatted dates
                    if (is_numeric($rawValue) && $rawValue >= 1 && $rawValue <= 2958465) {
                        // This could be an Excel date serial number
                        try {
                            $excelDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($rawValue);
                            if ($excelDate && $excelDate->format('Y') >= 1900 && $excelDate->format('Y') <= 9999) {
                                // It's a valid Excel date, use the formatted value if available, otherwise format it
                                $cellValue = ! empty($formattedValue) ? $formattedValue : $excelDate->format('Y-m-d');
                            } else {
                                // Not a valid date, use the raw value
                                $cellValue = $rawValue;
                            }
                        } catch (\Exception $e) {
                            // Not a valid Excel date, use the raw value
                            $cellValue = $rawValue;
                        }
                    } else {
                        // Use formatted value if available, otherwise use raw value
                        $cellValue = ! empty($formattedValue) ? $formattedValue : $rawValue;
                    }

                    $rowData[] = $cellValue;
                }
                $allData[] = $rowData;
            }

            // Clean up temporary file
            unlink($tempPath);
            unset($spreadsheet);
            unset($worksheet);

            if (empty($allData)) {
                return [];
            }

            // Filter data if filter is applied
            $filteredData = $this->applyFilters($allData);

            // Select only the chosen columns
            $selectedData = $this->selectColumns($filteredData);

            return $selectedData;

        } catch (Throwable $exception) {
            Log::error('Failed to process report data in job', [
                'document_id' => $document->id,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Apply filters to the data
     */
    private function applyFilters(array $data): array
    {
        // Collect all active filters
        $filters = [];

        // Filter 1
        if (! empty($this->filterColumn)) {
            $hasSingleValue = ! empty(trim($this->filterValue));
            $hasRangeValues = ! empty(trim($this->filterValueStart)) || ! empty(trim($this->filterValueEnd));

            if ($hasSingleValue || $hasRangeValues) {
                $filters[] = [
                    'column' => (int) $this->filterColumn,
                    'singleValue' => $hasSingleValue ? trim($this->filterValue) : null,
                    'rangeStart' => ! empty(trim($this->filterValueStart)) ? trim($this->filterValueStart) : null,
                    'rangeEnd' => ! empty(trim($this->filterValueEnd)) ? trim($this->filterValueEnd) : null,
                    'isDate' => $hasSingleValue ? $this->isDateValue(trim($this->filterValue)) :
                               ($hasRangeValues ? $this->isDateValue(trim($this->filterValueStart ?: $this->filterValueEnd)) : false),
                ];
            }
        }

        // Filter 2
        if (! empty($this->filterColumn2)) {
            $hasSingleValue = ! empty(trim($this->filterValue2));
            $hasRangeValues = ! empty(trim($this->filterValue2Start)) || ! empty(trim($this->filterValue2End));

            if ($hasSingleValue || $hasRangeValues) {
                $filters[] = [
                    'column' => (int) $this->filterColumn2,
                    'singleValue' => $hasSingleValue ? trim($this->filterValue2) : null,
                    'rangeStart' => ! empty(trim($this->filterValue2Start)) ? trim($this->filterValue2Start) : null,
                    'rangeEnd' => ! empty(trim($this->filterValue2End)) ? trim($this->filterValue2End) : null,
                    'isDate' => $hasSingleValue ? $this->isDateValue(trim($this->filterValue2)) :
                               ($hasRangeValues ? $this->isDateValue(trim($this->filterValue2Start ?: $this->filterValue2End)) : false),
                ];
            }
        }

        // Filter 3
        if (! empty($this->filterColumn3)) {
            $hasSingleValue = ! empty(trim($this->filterValue3));
            $hasRangeValues = ! empty(trim($this->filterValue3Start)) || ! empty(trim($this->filterValue3End));

            if ($hasSingleValue || $hasRangeValues) {
                $filters[] = [
                    'column' => (int) $this->filterColumn3,
                    'singleValue' => $hasSingleValue ? trim($this->filterValue3) : null,
                    'rangeStart' => ! empty(trim($this->filterValue3Start)) ? trim($this->filterValue3Start) : null,
                    'rangeEnd' => ! empty(trim($this->filterValue3End)) ? trim($this->filterValue3End) : null,
                    'isDate' => $hasSingleValue ? $this->isDateValue(trim($this->filterValue3)) :
                               ($hasRangeValues ? $this->isDateValue(trim($this->filterValue3Start ?: $this->filterValue3End)) : false),
                ];
            }
        }

        // If no filters are active, return all data
        if (empty($filters)) {
            return $data;
        }

        // Apply all filters (AND logic - all conditions must be met)
        return array_filter($data, function ($row) use ($filters) {
            foreach ($filters as $filter) {
                if (! isset($row[$filter['column']])) {
                    return false;
                }

                $cellValue = $row[$filter['column']];

                if ($filter['isDate']) {
                    // Handle date filtering
                    if ($filter['singleValue']) {
                        // Single date match
                        if (! $this->matchDateValue($cellValue, $filter['singleValue'])) {
                            return false;
                        }
                    } else {
                        // Range date filtering
                        if (! $this->matchDateRange($cellValue, $filter['rangeStart'], $filter['rangeEnd'])) {
                            return false;
                        }
                    }
                } else {
                    // Regular text filtering
                    if ($filter['singleValue']) {
                        // Single text match
                        $cellValue = strtolower(trim($cellValue));
                        $filterValue = strtolower($filter['singleValue']);

                        if (strpos($cellValue, $filterValue) === false) {
                            return false;
                        }
                    } else {
                        // Range text filtering (alphabetical range)
                        if (! $this->matchTextRange($cellValue, $filter['rangeStart'], $filter['rangeEnd'])) {
                            return false;
                        }
                    }
                }
            }

            return true; // All filters passed
        });
    }

    /**
     * Check if a value looks like a date or datetime
     */
    private function isDateValue(string $value): bool
    {
        // Common date formats
        $dateFormats = [
            'm/d/Y', 'm-d-Y', 'Y-m-d', 'd/m/Y', 'd-m-Y',
            'm/d/y', 'm-d-y', 'd/m/y', 'd-m-y',
            'n/j/Y', 'n-j-Y', 'j/n/Y', 'j-n-Y',
        ];

        // Common datetime formats
        $datetimeFormats = [
            'm/d/Y H:i', 'm-d-Y H:i', 'Y-m-d H:i', 'd/m/Y H:i', 'd-m-Y H:i',
            'm/d/y H:i', 'm-d-y H:i', 'd/m/y H:i', 'd-m-y H:i',
            'n/j/Y H:i', 'n-j-Y H:i', 'j/n/Y H:i', 'j-n-Y H:i',
            'm/d/Y G:i', 'm-d-Y G:i', 'Y-m-d G:i', 'd/m/Y G:i', 'd-m-Y G:i',
            'm/d/y G:i', 'm-d-y G:i', 'd/m/y G:i', 'd-m-y G:i',
            'n/j/Y G:i', 'n-j-Y G:i', 'j/n/Y G:i', 'j-n-Y G:i',
            // ISO datetime formats (from HTML5 datetime-local inputs)
            'Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i:s.u', 'Y-m-d\TH:i:s.u\Z',
        ];

        // Try date formats first
        foreach ($dateFormats as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date && $date->format($format) === $value) {
                return true;
            }
        }

        // Try datetime formats
        foreach ($datetimeFormats as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date && $date->format($format) === $value) {
                return true;
            }
        }

        // Also check for common date and datetime patterns with regex
        $datePatterns = [
            '/^\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}$/',  // MM/DD/YYYY, MM-DD-YYYY
            '/^\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}$/',    // YYYY/MM/DD, YYYY-MM-DD
        ];

        $datetimePatterns = [
            '/^\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}\s+\d{1,2}:\d{2}$/',  // MM/DD/YYYY HH:MM, MM-DD-YYYY HH:MM
            '/^\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}\s+\d{1,2}:\d{2}$/',    // YYYY/MM/DD HH:MM, YYYY-MM-DD HH:MM
            '/^\d{4}-\d{1,2}-\d{1,2}T\d{1,2}:\d{2}$/',               // YYYY-MM-DDTHH:MM (ISO datetime)
            '/^\d{4}-\d{1,2}-\d{1,2}T\d{1,2}:\d{2}:\d{2}$/',         // YYYY-MM-DDTHH:MM:SS (ISO datetime)
        ];

        foreach ($datePatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        foreach ($datetimePatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match date values while preserving original formatting
     */
    private function matchDateValue($cellValue, string $filterValue): bool
    {
        try {
            // Convert filter value to DateTime for comparison
            $filterDate = $this->parseDateValue($filterValue);
            if (! $filterDate) {
                // If filter value is not a valid date, fall back to string comparison
                $cellValueStr = strtolower(trim($cellValue));
                $filterValueStr = strtolower($filterValue);

                return strpos($cellValueStr, $filterValueStr) !== false;
            }

            // If cell value is a string (formatted date), try to parse it as date
            if (is_string($cellValue)) {
                $cellDate = $this->parseDateValue($cellValue);
                if ($cellDate) {
                    return $this->datesMatch($cellDate, $filterDate);
                }

                // If parsing fails, try string comparison for partial matches
                $cellValueStr = strtolower(trim($cellValue));
                $filterValueStr = strtolower($filterValue);

                return strpos($cellValueStr, $filterValueStr) !== false;
            }

            // If cell value is a DateTime object (shouldn't happen with new approach, but keep for safety)
            if ($cellValue instanceof \DateTime) {
                return $this->datesMatch($cellValue, $filterDate);
            }

            // If cell value is a numeric value (Excel serial date)
            if (is_numeric($cellValue)) {
                // Check if it's a valid Excel date serial number
                if ($cellValue >= 1 && $cellValue <= 2958465) {
                    try {
                        $cellDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($cellValue);
                        if ($cellDate && $cellDate->format('Y') >= 1900 && $cellDate->format('Y') <= 9999) {
                            return $this->datesMatch($cellDate, $filterDate);
                        }
                    } catch (\Exception $e) {
                        Log::debug('Failed to convert Excel serial number to date in matchDateValue', [
                            'cell_value' => $cellValue,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Fallback to string comparison
            $cellValueStr = strtolower(trim($cellValue));
            $filterValueStr = strtolower($filterValue);

            return strpos($cellValueStr, $filterValueStr) !== false;

        } catch (\Exception $e) {
            // If date parsing fails, fall back to string comparison
            Log::warning('Date matching failed, falling back to string comparison', [
                'cell_value' => $cellValue,
                'filter_value' => $filterValue,
                'error' => $e->getMessage(),
            ]);

            $cellValueStr = strtolower(trim($cellValue));
            $filterValueStr = strtolower($filterValue);

            return strpos($cellValueStr, $filterValueStr) !== false;
        }
    }

    /**
     * Parse a date or datetime value using multiple formats
     */
    private function parseDateValue(string $value): ?\DateTime
    {
        // Clean the value first
        $value = trim($value);

        $dateFormats = [
            'm/d/Y', 'm-d-Y', 'Y-m-d', 'd/m/Y', 'd-m-Y',
            'm/d/y', 'm-d-y', 'd/m/y', 'd-m-y',
            'n/j/Y', 'n-j-Y', 'j/n/Y', 'j-n-Y',
        ];

        $datetimeFormats = [
            'm/d/Y H:i', 'm-d-Y H:i', 'Y-m-d H:i', 'd/m/Y H:i', 'd-m-Y H:i',
            'm/d/y H:i', 'm-d-y H:i', 'd/m/y H:i', 'd-m-y H:i',
            'n/j/Y H:i', 'n-j-Y H:i', 'j/n/Y H:i', 'j-n-Y H:i',
            'm/d/Y G:i', 'm-d-Y G:i', 'Y-m-d G:i', 'd/m/Y G:i', 'd-m-Y G:i',
            'm/d/y G:i', 'm-d-y G:i', 'd/m/y G:i', 'd-m-y G:i',
            'n/j/Y G:i', 'n-j-Y G:i', 'j/n/Y G:i', 'j-n-Y G:i',
            'm/d/Y H:i:s', 'm-d-Y H:i:s', 'Y-m-d H:i:s', 'd/m/Y H:i:s', 'd-m-Y H:i:s',
            'm/d/y H:i:s', 'm-d-y H:i:s', 'd/m/y H:i:s', 'd-m-y H:i:s',
            'n/j/Y H:i:s', 'n-j-Y H:i:s', 'j/n/Y H:i:s', 'j-n-Y H:i:s',
            // ISO datetime formats (from HTML5 datetime-local inputs)
            'Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i:s.u', 'Y-m-d\TH:i:s.u\Z',
        ];

        // Try date formats first
        foreach ($dateFormats as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date && $date->format($format) === $value) {
                return $date;
            }
        }

        // Try datetime formats
        foreach ($datetimeFormats as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date && $date->format($format) === $value) {
                return $date;
            }
        }

        // Try to parse as a general date/datetime string
        try {
            $date = new \DateTime($value);
            return $date;
        } catch (\Exception $e) {
            // If all else fails, return null
            return null;
        }
    }

    /**
     * Check if two dates/datetimes match
     * For datetime values, compare the full datetime. For date-only values, compare just the date.
     */
    private function datesMatch(\DateTime $date1, \DateTime $date2): bool
    {
        // Check if both dates have time components (not just date)
        $date1HasTime = $date1->format('H:i:s') !== '00:00:00';
        $date2HasTime = $date2->format('H:i:s') !== '00:00:00';
        
        // If either date has time, compare the full datetime
        if ($date1HasTime || $date2HasTime) {
            return $date1->format('Y-m-d H:i:s') === $date2->format('Y-m-d H:i:s');
        }
        
        // If both are date-only, compare just the date
        return $date1->format('Y-m-d') === $date2->format('Y-m-d');
    }

    /**
     * Match a cell value against a date range
     */
    private function matchDateRange($cellValue, ?string $rangeStart, ?string $rangeEnd): bool
    {
        try {
            $cellDate = $this->parseDateValue($cellValue);
            
            // If parsing as string failed, try Excel serial number conversion
            if (! $cellDate && is_numeric($cellValue)) {
                if ($cellValue >= 1 && $cellValue <= 2958465) {
                    try {
                        $cellDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($cellValue);
                        if ($cellDate && $cellDate->format('Y') >= 1900 && $cellDate->format('Y') <= 9999) {
                            // Valid Excel date
                        } else {
                            $cellDate = null;
                        }
                    } catch (\Exception $e) {
                        Log::debug('Failed to convert Excel serial number to date in matchDateRange', [
                            'cell_value' => $cellValue,
                            'error' => $e->getMessage(),
                        ]);
                        $cellDate = null;
                    }
                }
            }
            
            if (! $cellDate) {
                return false;
            }

            $startDate = $rangeStart ? $this->parseDateValue($rangeStart) : null;
            $endDate = $rangeEnd ? $this->parseDateValue($rangeEnd) : null;

            // If only start date is provided, check if cell date is >= start
            if ($startDate && ! $endDate) {
                return $cellDate >= $startDate;
            }

            // If only end date is provided, check if cell date is <= end
            if (! $startDate && $endDate) {
                return $cellDate <= $endDate;
            }

            // If both dates are provided, check if cell date is between them
            if ($startDate && $endDate) {
                return $cellDate >= $startDate && $cellDate <= $endDate;
            }

            return false;

        } catch (\Exception $e) {
            Log::warning('Date range matching failed', [
                'cell_value' => $cellValue,
                'range_start' => $rangeStart,
                'range_end' => $rangeEnd,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Match a cell value against a text range (alphabetical)
     */
    private function matchTextRange($cellValue, ?string $rangeStart, ?string $rangeEnd): bool
    {
        $cellValue = strtolower(trim($cellValue));

        // If only start value is provided, check if cell value is >= start
        if ($rangeStart && ! $rangeEnd) {
            return strcasecmp($cellValue, strtolower($rangeStart)) >= 0;
        }

        // If only end value is provided, check if cell value is <= end
        if (! $rangeStart && $rangeEnd) {
            return strcasecmp($cellValue, strtolower($rangeEnd)) <= 0;
        }

        // If both values are provided, check if cell value is between them
        if ($rangeStart && $rangeEnd) {
            return strcasecmp($cellValue, strtolower($rangeStart)) >= 0 &&
                   strcasecmp($cellValue, strtolower($rangeEnd)) <= 0;
        }

        return false;
    }

    /**
     * Select only the chosen columns from the data
     */
    private function selectColumns(array $data): array
    {
        if (empty($data)) {
            return [];
        }

        $selectedData = [];
        foreach ($data as $row) {
            $selectedRow = [];
            foreach ($this->selectedColumns as $columnIndex) {
                $selectedRow[] = $row[$columnIndex] ?? '';
            }
            $selectedData[] = $selectedRow;
        }

        return $selectedData;
    }

    /**
     * Create Excel file from processed data
     */
    private function createExcelFile(array $data, Document $document): string
    {
        $spreadsheet = new Spreadsheet;
        $worksheet = $spreadsheet->getActiveSheet();

        // Get column names from the original document
        $columns = $this->getDocumentColumns($document);

        // Add headers
        $headers = [];
        foreach ($this->selectedColumns as $columnIndex) {
            $headers[] = $columns[$columnIndex]['name'] ?? "Column {$columnIndex}";
        }
        $worksheet->fromArray($headers, null, 'A1');

        // Add data rows
        $rowIndex = 2;
        foreach ($data as $row) {
            $worksheet->fromArray($row, null, "A{$rowIndex}");
            $rowIndex++;
        }

        // Apply table styling
        $this->applyTableStyling($worksheet, $data, $this->tableStyle);

        // Create temporary file
        $tempPath = tempnam(sys_get_temp_dir(), 'generated_report_').'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        // Clean up
        unset($spreadsheet);
        unset($worksheet);
        unset($writer);

        return $tempPath;
    }

    /**
     * Get document columns (simplified version)
     */
    private function getDocumentColumns(Document $document): array
    {
        try {
            $fileContents = Storage::disk($document->disk)->get($document->path);
            if ($fileContents === null) {
                return [];
            }

            $tempPath = tempnam(sys_get_temp_dir(), 'excel_');
            file_put_contents($tempPath, $fileContents);

            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(false);
            $reader->setReadEmptyCells(false);

            $spreadsheet = $reader->load($tempPath);
            $worksheet = $spreadsheet->getActiveSheet();

            $highestColumn = $worksheet->getHighestColumn(1);
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

            $headers = [];
            for ($colIndex = 1; $colIndex <= $highestColumnIndex; $colIndex++) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $cellValue = $worksheet->getCell($columnLetter.'1')->getFormattedValue();
                if (! empty($cellValue)) {
                    $headers[] = [
                        'column' => $columnLetter,
                        'name' => $cellValue,
                        'index' => count($headers),
                    ];
                }
            }

            unlink($tempPath);
            unset($spreadsheet, $worksheet, $reader);

            return $headers;

        } catch (Throwable $exception) {
            Log::error('Failed to get document columns in job', [
                'document_id' => $document->id,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get report description based on filters and columns
     */
    private function getReportDescription(Document $document): string
    {
        $description = 'Generated report with '.count($this->selectedColumns).' selected columns';

        $filters = [];

        // Filter 1
        if (! empty($this->filterColumn)) {
            $hasSingleValue = ! empty(trim($this->filterValue));
            $hasRangeValues = ! empty(trim($this->filterValueStart)) || ! empty(trim($this->filterValueEnd));

            if ($hasSingleValue) {
                $filters[] = "column {$this->filterColumn} containing '{$this->filterValue}'";
            } elseif ($hasRangeValues) {
                $rangeDesc = $this->getRangeDescription($this->filterValueStart, $this->filterValueEnd);
                $filters[] = "column {$this->filterColumn} {$rangeDesc}";
            }
        }

        // Filter 2
        if (! empty($this->filterColumn2)) {
            $hasSingleValue = ! empty(trim($this->filterValue2));
            $hasRangeValues = ! empty(trim($this->filterValue2Start)) || ! empty(trim($this->filterValue2End));

            if ($hasSingleValue) {
                $filters[] = "column {$this->filterColumn2} containing '{$this->filterValue2}'";
            } elseif ($hasRangeValues) {
                $rangeDesc = $this->getRangeDescription($this->filterValue2Start, $this->filterValue2End);
                $filters[] = "column {$this->filterColumn2} {$rangeDesc}";
            }
        }

        // Filter 3
        if (! empty($this->filterColumn3)) {
            $hasSingleValue = ! empty(trim($this->filterValue3));
            $hasRangeValues = ! empty(trim($this->filterValue3Start)) || ! empty(trim($this->filterValue3End));

            if ($hasSingleValue) {
                $filters[] = "column {$this->filterColumn3} containing '{$this->filterValue3}'";
            } elseif ($hasRangeValues) {
                $rangeDesc = $this->getRangeDescription($this->filterValue3Start, $this->filterValue3End);
                $filters[] = "column {$this->filterColumn3} {$rangeDesc}";
            }
        }

        if (! empty($filters)) {
            $description .= ' filtered by '.implode(' AND ', $filters);
        }

        return $description;
    }

    /**
     * Get range description for report
     */
    private function getRangeDescription(?string $start, ?string $end): string
    {
        if ($start && $end) {
            return "between '{$start}' and '{$end}'";
        } elseif ($start) {
            return "from '{$start}' onwards";
        } elseif ($end) {
            return "up to '{$end}'";
        }

        return '';
    }

    /**
     * Dispatch success event
     */
    private function dispatchSuccess(GeneratedReport $generatedReport): void
    {
        // Dispatch event to notify the frontend
        event(new \App\Events\ReportGenerated($generatedReport, $this->sessionId));
    }

    /**
     * Dispatch error event
     */
    private function dispatchError(string $message): void
    {
        // Dispatch event to notify the frontend
        event(new \App\Events\ReportGenerationFailed($message, $this->sessionId));
    }
}

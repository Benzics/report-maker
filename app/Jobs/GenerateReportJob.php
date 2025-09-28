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
        public string $sessionId
    ) {
        //
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

            // Get all data with proper date handling
            $allData = [];
            for ($row = 1; $row <= $highestRow; $row++) {
                $rowData = [];
                for ($col = 'A'; $col <= $highestColumn; $col++) {
                    $cell = $worksheet->getCell($col.$row);
                    $cellValue = $cell->getValue();

                    // Check if the cell contains a date
                    if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
                        $cellValue = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($cell->getCalculatedValue());
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
        if (empty($this->filterColumn) || empty($this->filterValue)) {
            return $data;
        }

        $filterColumnIndex = (int) $this->filterColumn;
        $filterValue = trim($this->filterValue);

        // Check if the filter value looks like a date
        $isDateFilter = $this->isDateValue($filterValue);

        return array_filter($data, function ($row) use ($filterColumnIndex, $filterValue, $isDateFilter) {
            if (! isset($row[$filterColumnIndex])) {
                return false;
            }

            $cellValue = $row[$filterColumnIndex];

            if ($isDateFilter) {
                return $this->matchDateValue($cellValue, $filterValue);
            } else {
                // Regular text filtering
                $cellValue = strtolower(trim($cellValue));
                $filterValue = strtolower($filterValue);

                return strpos($cellValue, $filterValue) !== false;
            }
        });
    }

    /**
     * Check if a value looks like a date
     */
    private function isDateValue(string $value): bool
    {
        // Common date formats
        $dateFormats = [
            'm/d/Y',     // 09/15/2025
            'm-d-Y',     // 09-15-2025
            'Y-m-d',     // 2025-09-15
            'd/m/Y',     // 15/09/2025
            'd-m-Y',     // 15-09-2025
            'm/d/y',     // 09/15/25
            'm-d-y',     // 09-15-25
            'd/m/y',     // 15/09/25
            'd-m-y',     // 15-09-25
        ];

        foreach ($dateFormats as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date && $date->format($format) === $value) {
                return true;
            }
        }

        // Also check for common date patterns with regex
        $datePatterns = [
            '/^\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}$/',  // MM/DD/YYYY, MM-DD-YYYY
            '/^\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}$/',    // YYYY/MM/DD, YYYY-MM-DD
        ];

        foreach ($datePatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match date values using PhpSpreadsheet date detection
     */
    private function matchDateValue($cellValue, string $filterValue): bool
    {
        try {
            // Convert filter value to DateTime
            $filterDate = $this->parseDateValue($filterValue);
            if (! $filterDate) {
                return false;
            }

            // If cell value is already a DateTime object from PhpSpreadsheet
            if ($cellValue instanceof \DateTime) {
                return $this->datesMatch($cellValue, $filterDate);
            }

            // If cell value is a numeric value (Excel serial date)
            if (is_numeric($cellValue)) {
                $cellDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($cellValue);

                return $this->datesMatch($cellDate, $filterDate);
            }

            // If cell value is a string, try to parse it as date
            if (is_string($cellValue)) {
                $cellDate = $this->parseDateValue($cellValue);
                if ($cellDate) {
                    return $this->datesMatch($cellDate, $filterDate);
                }
            }

            // Fallback to string comparison for partial matches
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
     * Parse a date value using multiple formats
     */
    private function parseDateValue(string $value): ?\DateTime
    {
        $dateFormats = [
            'm/d/Y',     // 09/15/2025
            'm-d-Y',     // 09-15-2025
            'Y-m-d',     // 2025-09-15
            'd/m/Y',     // 15/09/2025
            'd-m-Y',     // 15-09-2025
            'm/d/y',     // 09/15/25
            'm-d-y',     // 09-15-25
            'd/m/y',     // 15/09/25
            'd-m-y',     // 15-09-25
            'n/j/Y',     // 9/15/2025 (no leading zeros)
            'n-j-Y',     // 9-15-2025
            'j/n/Y',     // 15/9/2025
            'j-n-Y',     // 15-9-2025
        ];

        foreach ($dateFormats as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date && $date->format($format) === $value) {
                return $date;
            }
        }

        return null;
    }

    /**
     * Check if two dates match (same day, ignoring time)
     */
    private function datesMatch(\DateTime $date1, \DateTime $date2): bool
    {
        return $date1->format('Y-m-d') === $date2->format('Y-m-d');
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

        // Auto-size columns
        foreach (range('A', $worksheet->getHighestColumn()) as $column) {
            $worksheet->getColumnDimension($column)->setAutoSize(true);
        }

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
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);

            $spreadsheet = $reader->load($tempPath);
            $worksheet = $spreadsheet->getActiveSheet();

            $highestColumn = $worksheet->getHighestColumn(1);
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

            $headers = [];
            for ($colIndex = 1; $colIndex <= $highestColumnIndex; $colIndex++) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $cellValue = $worksheet->getCell($columnLetter.'1')->getValue();
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

        if (! empty($this->filterColumn) && ! empty($this->filterValue)) {
            $description .= " filtered by column {$this->filterColumn} containing '{$this->filterValue}'";
        }

        return $description;
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

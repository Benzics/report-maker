<?php

namespace App\Livewire\Reports;

use App\Models\Document;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class Generate extends Component
{
    public $document;

    public $columns = [];

    public $selectedColumns = [];

    public $filterColumn = '';

    public $filterValue = '';

    public $filterColumn2 = '';

    public $filterValue2 = '';

    public $filterColumn3 = '';

    public $filterValue3 = '';

    // Range filter properties
    public $filterValueStart = '';

    public $filterValueEnd = '';

    public $filterValue2Start = '';

    public $filterValue2End = '';

    public $filterValue3Start = '';

    public $filterValue3End = '';

    public $isLoading = false;

    public $error = '';

    public $validationError = '';

    public $sessionId;

    public $isDateColumn = false;

    public $isDateColumn2 = false;

    public $isDateColumn3 = false;

    public $tableStyle = 'table_style_medium_2';

    // Cache key for processed columns
    private $cacheKey;

    /**
     * Get available table styles
     */
    public function getTableStyles()
    {
        return [
            'table_style_light_1' => 'Table Style Light 1',
            'table_style_light_2' => 'Table Style Light 2',
            'table_style_light_3' => 'Table Style Light 3',
            'table_style_light_4' => 'Table Style Light 4',
            'table_style_light_5' => 'Table Style Light 5',
            'table_style_light_6' => 'Table Style Light 6',
            'table_style_light_7' => 'Table Style Light 7',
            'table_style_light_8' => 'Table Style Light 8',
            'table_style_light_9' => 'Table Style Light 9',
            'table_style_light_10' => 'Table Style Light 10',
            'table_style_light_11' => 'Table Style Light 11',
            'table_style_light_12' => 'Table Style Light 12',
            'table_style_light_13' => 'Table Style Light 13',
            'table_style_light_14' => 'Table Style Light 14',
            'table_style_light_15' => 'Table Style Light 15',
            'table_style_light_16' => 'Table Style Light 16',
            'table_style_light_17' => 'Table Style Light 17',
            'table_style_light_18' => 'Table Style Light 18',
            'table_style_light_19' => 'Table Style Light 19',
            'table_style_light_20' => 'Table Style Light 20',
            'table_style_light_21' => 'Table Style Light 21',
            'table_style_medium_1' => 'Table Style Medium 1',
            'table_style_medium_2' => 'Table Style Medium 2 (Blue)',
            'table_style_medium_3' => 'Table Style Medium 3',
            'table_style_medium_4' => 'Table Style Medium 4',
            'table_style_medium_5' => 'Table Style Medium 5',
            'table_style_medium_6' => 'Table Style Medium 6',
            'table_style_medium_7' => 'Table Style Medium 7',
            'table_style_medium_8' => 'Table Style Medium 8',
            'table_style_medium_9' => 'Table Style Medium 9',
            'table_style_medium_10' => 'Table Style Medium 10',
            'table_style_medium_11' => 'Table Style Medium 11',
            'table_style_medium_12' => 'Table Style Medium 12',
            'table_style_medium_13' => 'Table Style Medium 13',
            'table_style_medium_14' => 'Table Style Medium 14',
            'table_style_medium_15' => 'Table Style Medium 15',
            'table_style_medium_16' => 'Table Style Medium 16',
            'table_style_medium_17' => 'Table Style Medium 17',
            'table_style_medium_18' => 'Table Style Medium 18',
            'table_style_medium_19' => 'Table Style Medium 19',
            'table_style_medium_20' => 'Table Style Medium 20',
            'table_style_medium_21' => 'Table Style Medium 21',
            'table_style_dark_1' => 'Table Style Dark 1',
            'table_style_dark_2' => 'Table Style Dark 2',
            'table_style_dark_3' => 'Table Style Dark 3',
            'table_style_dark_4' => 'Table Style Dark 4',
            'table_style_dark_5' => 'Table Style Dark 5',
            'table_style_dark_6' => 'Table Style Dark 6',
            'table_style_dark_7' => 'Table Style Dark 7',
            'table_style_dark_8' => 'Table Style Dark 8',
            'table_style_dark_9' => 'Table Style Dark 9',
            'table_style_dark_10' => 'Table Style Dark 10',
            'table_style_dark_11' => 'Table Style Dark 11',
        ];
    }

    public function mount($documentId)
    {
        $this->document = Document::where('id', $documentId)
            ->where('user_id', Auth::id())
            ->first();

        if (! $this->document) {
            abort(404, 'Document not found.');
        }

        // Create cache key based on document ID and last modified time
        $this->cacheKey = 'document_columns_'.$this->document->id.'_'.$this->document->updated_at->timestamp;
        $this->sessionId = session()->getId();

        // Always load columns synchronously for reliability
        $this->loadDocumentColumns();
    }

    /**
     * Load document columns asynchronously for better performance
     */
    public function loadDocumentColumnsAsync()
    {
        // Check cache first - this is fast
        $cachedColumns = Cache::get($this->cacheKey);
        if ($cachedColumns !== null) {
            $this->columns = $cachedColumns;
            $this->isLoading = false;

            // Set date column properties based on cached data
            $this->updateDateColumnProperties();

            return;
        }

        // For small files, load immediately to avoid unnecessary async complexity
        $fileSize = Storage::disk($this->document->disk)->size($this->document->path);
        if ($fileSize < 500 * 1024) { // Less than 500KB
            $this->loadDocumentColumns();

            return;
        }

        // If not cached, show loading and process in background
        $this->isLoading = true;
        $this->error = '';

        // Use JavaScript to trigger the actual loading after page render
        $this->dispatch('loadColumns');
    }

    /**
     * Load document columns synchronously
     */
    public function loadDocumentColumns()
    {
        try {
            $this->isLoading = true;
            $this->error = '';

            // Check cache first
            $cachedColumns = Cache::get($this->cacheKey);
            if ($cachedColumns !== null) {
                $this->columns = $cachedColumns;
                $this->isLoading = false;

                // Set date column properties based on cached data
                $this->updateDateColumnProperties();

                return;
            }

            // Check if file exists in storage
            if (! Storage::disk($this->document->disk)->exists($this->document->path)) {
                $this->error = __('File not found in storage.');
                $this->isLoading = false;

                return;
            }

            // Get file contents from storage
            $fileContents = Storage::disk($this->document->disk)->get($this->document->path);
            if ($fileContents === null) {
                $this->error = __('Could not read file contents.');
                $this->isLoading = false;

                return;
            }

            // Create a temporary file for PhpSpreadsheet
            $tempPath = tempnam(sys_get_temp_dir(), 'excel_');
            file_put_contents($tempPath, $fileContents);

            try {
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
                    $cellValue = $worksheet->getCell($columnLetter.'1')->getValue();
                    if (! empty($cellValue)) {
                        // Detect if this column contains date values
                        $isDateColumn = $this->detectColumnDateType($worksheet, $columnLetter, $colIndex);

                        $headers[] = [
                            'column' => $columnLetter,
                            'name' => $cellValue,
                            'index' => count($headers),
                            'is_date' => $isDateColumn,
                        ];
                    }
                }

                $this->columns = $headers;

                // Cache the processed columns
                Cache::put($this->cacheKey, $headers, 3600); // 1 hour cache

            } finally {
                // Clean up temporary file
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }
                unset($spreadsheet, $worksheet, $reader);
            }

        } catch (Throwable $exception) {
            Log::error('Failed to load document columns', [
                'document_id' => $this->document->id,
                'document_path' => $this->document->path,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            $this->error = __('Failed to read Excel file: ').$exception->getMessage();
        } finally {
            $this->isLoading = false;
        }
    }

    public function toggleColumn($index)
    {
        if (in_array($index, $this->selectedColumns)) {
            $this->selectedColumns = array_filter($this->selectedColumns, fn ($i) => $i !== $index);
        } else {
            $this->selectedColumns[] = $index;
            sort($this->selectedColumns);
        }

        // Clear validation error when user starts selecting columns
        if (! empty($this->selectedColumns)) {
            $this->validationError = '';
        }
    }

    public function updatedFilterColumn()
    {
        // Clear validation error when user changes filter column
        $this->validationError = '';

        // Check if the selected column contains date values using cached data
        $this->isDateColumn = $this->isColumnDateType((int) $this->filterColumn);
    }

    public function updatedFilterValue()
    {
        // Clear validation error when user starts typing filter value
        $this->validationError = '';
    }

    public function updatedFilterColumn2()
    {
        // Clear validation error when user changes filter column
        $this->validationError = '';

        // Check if the selected column contains date values
        $this->isDateColumn2 = $this->isColumnDateType((int) $this->filterColumn2);
    }

    public function updatedFilterValue2()
    {
        // Clear validation error when user starts typing filter value
        $this->validationError = '';
    }

    public function updatedFilterColumn3()
    {
        // Clear validation error when user changes filter column
        $this->validationError = '';

        // Check if the selected column contains date values
        $this->isDateColumn3 = $this->isColumnDateType((int) $this->filterColumn3);
    }

    public function updatedFilterValue3()
    {
        // Clear validation error when user starts typing filter value
        $this->validationError = '';
    }

    public function updatedFilterValueStart()
    {
        // Clear validation error when user starts typing filter value
        $this->validationError = '';
    }

    public function updatedFilterValueEnd()
    {
        // Clear validation error when user starts typing filter value
        $this->validationError = '';
    }

    public function updatedFilterValue2Start()
    {
        // Clear validation error when user starts typing filter value
        $this->validationError = '';
    }

    public function updatedFilterValue2End()
    {
        // Clear validation error when user starts typing filter value
        $this->validationError = '';
    }

    public function updatedFilterValue3Start()
    {
        // Clear validation error when user starts typing filter value
        $this->validationError = '';
    }

    public function updatedFilterValue3End()
    {
        // Clear validation error when user starts typing filter value
        $this->validationError = '';
    }

    public function generateReport()
    {
        try {
            // Clear any previous validation errors
            $this->validationError = '';

            // Validate that at least one column is selected
            if (empty($this->selectedColumns)) {
                $this->validationError = __('Please select at least one column to include in your report.');
                $this->dispatch('showValidationError', $this->validationError);

                return;
            }

            // Validate filter fields if filter columns are selected
            if (! empty($this->filterColumn)) {
                $hasSingleValue = ! empty(trim($this->filterValue));
                $hasRangeValues = ! empty(trim($this->filterValueStart)) || ! empty(trim($this->filterValueEnd));

                if (! $hasSingleValue && ! $hasRangeValues) {
                    $this->validationError = __('Please enter a filter value or range when a filter column is selected.');
                    $this->dispatch('showValidationError', $this->validationError);

                    return;
                }

                // Validate range if both start and end are provided
                if (! empty(trim($this->filterValueStart)) && ! empty(trim($this->filterValueEnd))) {
                    if ($this->isDateColumn && ! $this->validateDateRange($this->filterValueStart, $this->filterValueEnd)) {
                        $this->validationError = __('Start date must be before or equal to end date.');
                        $this->dispatch('showValidationError', $this->validationError);

                        return;
                    }
                }
            }

            if (! empty($this->filterColumn2)) {
                $hasSingleValue = ! empty(trim($this->filterValue2));
                $hasRangeValues = ! empty(trim($this->filterValue2Start)) || ! empty(trim($this->filterValue2End));

                if (! $hasSingleValue && ! $hasRangeValues) {
                    $this->validationError = __('Please enter a filter value or range for the second filter column.');
                    $this->dispatch('showValidationError', $this->validationError);

                    return;
                }

                // Validate range if both start and end are provided
                if (! empty(trim($this->filterValue2Start)) && ! empty(trim($this->filterValue2End))) {
                    if ($this->isDateColumn2 && ! $this->validateDateRange($this->filterValue2Start, $this->filterValue2End)) {
                        $this->validationError = __('Start date must be before or equal to end date for filter 2.');
                        $this->dispatch('showValidationError', $this->validationError);

                        return;
                    }
                }
            }

            if (! empty($this->filterColumn3)) {
                $hasSingleValue = ! empty(trim($this->filterValue3));
                $hasRangeValues = ! empty(trim($this->filterValue3Start)) || ! empty(trim($this->filterValue3End));

                if (! $hasSingleValue && ! $hasRangeValues) {
                    $this->validationError = __('Please enter a filter value or range for the third filter column.');
                    $this->dispatch('showValidationError', $this->validationError);

                    return;
                }

                // Validate range if both start and end are provided
                if (! empty(trim($this->filterValue3Start)) && ! empty(trim($this->filterValue3End))) {
                    if ($this->isDateColumn3 && ! $this->validateDateRange($this->filterValue3Start, $this->filterValue3End)) {
                        $this->validationError = __('Start date must be before or equal to end date for filter 3.');
                        $this->dispatch('showValidationError', $this->validationError);

                        return;
                    }
                }
            }

            // Show loading dialog
            $this->dispatch('showLoading', __('Generating report...'));

            // Always use queued job for consistent behavior
            $this->generateReportAsync();
            Log::info('Generating report...', [
                'document_id' => $this->document->id,
                'selected_columns' => $this->selectedColumns,
                'filter_column' => $this->filterColumn,
                'filter_value' => $this->filterValue,
                'filter_column2' => $this->filterColumn2,
                'filter_value2' => $this->filterValue2,
                'filter_column3' => $this->filterColumn3,
                'filter_value3' => $this->filterValue3,
            ]);

        } catch (Throwable $exception) {
            Log::error('Failed to generate report', [
                'document_id' => $this->document->id,
                'user_id' => Auth::id(),
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            $this->dispatch('hideLoading');
            $this->dispatch('showError', __('Failed to generate report: ').$exception->getMessage());
        }
    }

    /**
     * Clear cache for this document
     */
    public function clearCache()
    {
        // Show loading dialog for refresh
        $this->dispatch('showRefreshLoading', __('Refreshing document data...'));

        Cache::forget($this->cacheKey);
        $this->loadDocumentColumns();

        // Hide loading dialog
        $this->dispatch('hideRefreshLoading');
    }

    /**
     * Load columns with better error handling and performance
     */
    public function loadColumns()
    {
        $this->loadDocumentColumns();
    }

    /**
     * Detect if a column contains date values during initial load
     */
    private function detectColumnDateType($worksheet, $columnLetter, $columnIndex): bool
    {
        Log::info('detecting column date type', [
            'column_letter' => $columnLetter,
            'column_index' => $columnIndex,
            'worksheet' => $worksheet,
        ]);
        try {
            $highestRow = min($worksheet->getHighestRow(), 50); // Sample first 100 rows
            $dateCount = 0;
            $sampleCount = 0;

            // Sample the first few rows to determine if column contains dates
            for ($row = 2; $row <= $highestRow; $row++) { // Skip header row
                $cell = $worksheet->getCell($columnLetter.$row);
                $cellValue = $cell->getFormattedValue();

                if (! empty($cellValue)) {
                    $sampleCount++;

                    // Check if PhpSpreadsheet detects this as a date
                    if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
                        Log::info('date detected', [
                            'column_letter' => $columnLetter,
                            'column_index' => $columnIndex,
                            'cell_value' => $cellValue,
                        ]);
                        $dateCount++;
                    } else {
                        Log::info('date not detected', [
                            'column_letter' => $columnLetter,
                            'column_index' => $columnIndex,
                            'cell_value' => $cellValue,
                        ]);
                        // Also check if the string value looks like a date
                        if (is_string($cellValue) && $this->looksLikeDate($cellValue)) {
                            $dateCount++;
                        }
                    }

                    // If we've sampled enough, break
                    if ($sampleCount >= 10) {
                        break;
                    }
                }
            }

            return $dateCount > 0 && ($dateCount / max($sampleCount, 1)) > 0.5; // More than 50% are dates

        } catch (Throwable $exception) {
            Log::warning('Failed to detect column date type during load', [
                'column_letter' => $columnLetter,
                'column_index' => $columnIndex,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check if a column contains date values using cached data
     */
    private function isColumnDateType($columnIndex): bool
    {
        if ($columnIndex === null || $columnIndex === '' || empty($this->columns)) {
            return false;
        }

        // Convert to integer and check bounds
        $index = (int) $columnIndex;
        if ($index < 0 || $index >= count($this->columns)) {
            return false;
        }

        // Return the cached date type information
        return $this->columns[$index]['is_date'] ?? false;
    }

    /**
     * Update date column properties based on current filter selections
     */
    private function updateDateColumnProperties(): void
    {
        // Update date column properties for all filter columns
        $this->isDateColumn = $this->isColumnDateType((int) $this->filterColumn);
        $this->isDateColumn2 = $this->isColumnDateType((int) $this->filterColumn2);
        $this->isDateColumn3 = $this->isColumnDateType((int) $this->filterColumn3);
    }

    /**
     * Validate that start date is before or equal to end date
     */
    private function validateDateRange(string $startDate, string $endDate): bool
    {
        try {
            $start = new \DateTime($startDate);
            $end = new \DateTime($endDate);

            return $start <= $end;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if a string value looks like a date or datetime
     */
    private function looksLikeDate(string $value): bool
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
     * Generate report asynchronously using queue
     */
    private function generateReportAsync()
    {
        Log::info('Generating report asynchronously...');
        try {
            // Dispatch the queued job
            \App\Jobs\GenerateReportJob::dispatch(
                $this->document->id,
                Auth::id(),
                $this->selectedColumns,
                $this->filterColumn,
                $this->filterValue,
                $this->filterColumn2,
                $this->filterValue2,
                $this->filterColumn3,
                $this->filterValue3,
                $this->filterValueStart,
                $this->filterValueEnd,
                $this->filterValue2Start,
                $this->filterValue2End,
                $this->filterValue3Start,
                $this->filterValue3End,
                $this->tableStyle,
                $this->sessionId
            );

        } catch (Throwable $exception) {
            Log::error('Failed to dispatch report generation job', [
                'document_id' => $this->document->id,
                'user_id' => Auth::id(),
                'message' => $exception->getMessage(),
            ]);

            $this->dispatch('hideLoading');
            $this->dispatch('showError', __('Failed to start report generation: ').$exception->getMessage());
        }
    }

    // Note: dehydrate() method removed as it was clearing component state during testing
    // Component memory cleanup is handled by PHP garbage collection

    #[Layout('components.layouts.app')]
    public function render(): ViewContract
    {
        return view('livewire.reports.generate', [
            'title' => __('Generate Report'),
        ]);
    }
}

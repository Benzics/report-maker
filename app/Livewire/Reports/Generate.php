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

    public $isLoading = false;

    public $error = '';

    public $validationError = '';

    public $sessionId;

    public $isDateColumn = false;

    // Cache key for processed columns
    private $cacheKey;

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

        // Check if the selected column contains date values
        $this->isDateColumn = $this->isColumnDateType($this->filterColumn);
    }

    public function updatedFilterValue()
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

            // Validate filter fields if filter column is selected
            if (! empty($this->filterColumn) && empty(trim($this->filterValue))) {
                $this->validationError = __('Please enter a filter value when a filter column is selected.');
                $this->dispatch('showValidationError', $this->validationError);

                return;
            }

            // Show loading dialog
            $this->dispatch('showLoading', __('Generating report...'));

            // Always use queued job for consistent behavior
            $this->generateReportAsync();
            Log::info('Generating report...', ['document_id' => $this->document->id, 'selected_columns' => $this->selectedColumns, 'filter_column' => $this->filterColumn, 'filter_value' => $this->filterValue]);

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
     * Check if a column contains date values by sampling the data
     */
    private function isColumnDateType($columnIndex): bool
    {
        if (empty($columnIndex) || empty($this->document)) {
            return false;
        }

        try {
            // Get file contents from storage
            $fileContents = Storage::disk($this->document->disk)->get($this->document->path);
            if ($fileContents === null) {
                return false;
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
                $highestRow = min($worksheet->getHighestRow(), 100); // Sample first 100 rows

                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex + 1);
                $dateCount = 0;
                $sampleCount = 0;

                // Sample the first few rows to determine if column contains dates
                for ($row = 2; $row <= $highestRow; $row++) { // Skip header row
                    $cell = $worksheet->getCell($columnLetter.$row);
                    $cellValue = $cell->getValue();

                    if (! empty($cellValue)) {
                        $sampleCount++;

                        // Check if PhpSpreadsheet detects this as a date
                        if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
                            $dateCount++;
                        } else {
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

            } finally {
                // Clean up temporary file
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }
                unset($spreadsheet, $worksheet, $reader);
            }

        } catch (Throwable $exception) {
            Log::warning('Failed to detect column date type', [
                'document_id' => $this->document->id,
                'column_index' => $columnIndex,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check if a string value looks like a date
     */
    private function looksLikeDate(string $value): bool
    {
        // Common date formats
        $dateFormats = [
            'm/d/Y', 'm-d-Y', 'Y-m-d', 'd/m/Y', 'd-m-Y',
            'm/d/y', 'm-d-y', 'd/m/y', 'd-m-y',
            'n/j/Y', 'n-j-Y', 'j/n/Y', 'j-n-Y',
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

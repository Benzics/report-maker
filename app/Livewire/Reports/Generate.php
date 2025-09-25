<?php

namespace App\Livewire\Reports;

use App\Models\Document;
use App\Models\GeneratedReport;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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
    
    // Cache key for processed columns
    private $cacheKey;

    public function mount($documentId)
    {
        $this->document = Document::where('id', $documentId)
            ->where('user_id', Auth::id())
            ->first();

        if (!$this->document) {
            abort(404, 'Document not found.');
        }

        // Create cache key based on document ID and last modified time
        $this->cacheKey = 'document_columns_' . $this->document->id . '_' . $this->document->updated_at->timestamp;
        
        // Load columns immediately for better performance
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
            // Only set selectedColumns if it's empty (first load)
            if (empty($this->selectedColumns)) {
                $this->selectedColumns = array_keys($cachedColumns);
            }
            $this->isLoading = false;
            return;
        }

        // In testing environment, load immediately
        if (app()->environment('testing')) {
            $this->loadDocumentColumns();
            return;
        }

        // For small files, also load immediately to avoid unnecessary async complexity
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
     * Load document columns synchronously (called from JavaScript)
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
                // Only set selectedColumns if it's empty (first load)
                if (empty($this->selectedColumns)) {
                    $this->selectedColumns = array_keys($cachedColumns);
                }
                $this->isLoading = false;
                return;
            }

            // Check if file exists in storage
            if (!Storage::disk($this->document->disk)->exists($this->document->path)) {
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
                // Use optimized settings for better performance
                $reader = IOFactory::createReader('Xlsx');
                $reader->setReadDataOnly(true); // Only read data, not formatting
                $reader->setReadEmptyCells(false); // Skip empty cells
                
                $spreadsheet = $reader->load($tempPath);
                $worksheet = $spreadsheet->getActiveSheet();
                
                // Get only the first row for headers - much faster
                $highestColumn = $worksheet->getHighestColumn(1); // Only check row 1
                $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

                // Get the first row (headers) - optimized approach
                $headers = [];
                for ($colIndex = 1; $colIndex <= $highestColumnIndex; $colIndex++) {
                    $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                    $cellValue = $worksheet->getCell($columnLetter . '1')->getValue();
                    if (!empty($cellValue)) {
                        $headers[] = [
                            'column' => $columnLetter,
                            'name' => $cellValue,
                            'index' => count($headers)
                        ];
                    }
                }

                $this->columns = $headers;
                // Only set selectedColumns if it's empty (first load)
                if (empty($this->selectedColumns)) {
                    $this->selectedColumns = array_keys($headers); // Select all by default
                }

                // Cache the processed columns for 24 hours (longer cache for better performance)
                Cache::put($this->cacheKey, $headers, 86400);

            } finally {
                // Clean up temporary file
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }
                
                // Clear PhpSpreadsheet from memory
                unset($spreadsheet, $worksheet, $reader);
            }

        } catch (Throwable $exception) {
            Log::error('Failed to load document columns', [
                'document_id' => $this->document->id,
                'document_path' => $this->document->path,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            $this->error = __('Failed to read Excel file: ') . $exception->getMessage();
        } finally {
            $this->isLoading = false;
        }
    }

    public function toggleColumn($index)
    {
        if (in_array($index, $this->selectedColumns)) {
            $this->selectedColumns = array_filter($this->selectedColumns, fn($i) => $i !== $index);
        } else {
            $this->selectedColumns[] = $index;
            sort($this->selectedColumns);
        }
        
        // Clear validation error when user starts selecting columns
        if (!empty($this->selectedColumns)) {
            $this->validationError = '';
        }
    }

    public function updatedFilterColumn()
    {
        // Clear validation error when user changes filter column
        $this->validationError = '';
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
            if (!empty($this->filterColumn) && empty(trim($this->filterValue))) {
                $this->validationError = __('Please enter a filter value when a filter column is selected.');
                $this->dispatch('showValidationError', $this->validationError);
                return;
            }

            // Show loading dialog
            $this->dispatch('showLoading', __('Generating report...'));

            // Use a queued job for better performance on large files
            if ($this->shouldUseQueue()) {
                $this->generateReportAsync();
                return;
            }

            // Generate the report synchronously for small files
            $reportData = $this->processReportData();
            
            if (empty($reportData)) {
                $this->dispatch('hideLoading');
                $this->dispatch('showError', __('No data found matching your criteria.'));
                return;
            }

            // Create the Excel file
            $filePath = $this->createExcelFile($reportData);
            
            // Generate a unique filename
            $fileName = 'report_' . $this->document->id . '_' . time() . '.xlsx';
            
            // Store the file
            $storedPath = 'reports/' . $fileName;
            Storage::disk('local')->put($storedPath, file_get_contents($filePath));
            
            // Clean up temporary file
            unlink($filePath);
            
            // Create a temporary generated report record (not saved to DB yet)
            $generatedReport = new GeneratedReport([
                'document_id' => $this->document->id,
                'user_id' => Auth::id(),
                'name' => 'Report from ' . $this->document->original_name,
                'description' => $this->getReportDescription(),
                'selected_columns' => $this->selectedColumns,
                'filter_column' => $this->filterColumn,
                'filter_value' => $this->filterValue,
                'file_path' => $storedPath,
                'file_name' => $fileName,
                'file_size' => Storage::disk('local')->size($storedPath),
                'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'is_saved' => false,
            ]);

            // Hide loading dialog
            $this->dispatch('hideLoading');
            
            // Redirect to preview page with the generated report data
            return redirect()->route('reports.preview', [
                'report' => base64_encode(serialize($generatedReport))
            ]);

        } catch (Throwable $exception) {
            Log::error('Failed to generate report', [
                'document_id' => $this->document->id,
                'user_id' => Auth::id(),
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            $this->dispatch('hideLoading');
            $this->dispatch('showError', __('Failed to generate report: ') . $exception->getMessage());
        }
    }

    /**
     * Process the report data based on selected columns and filters
     */
    private function processReportData()
    {
        try {
            // Get file contents from storage
            $fileContents = Storage::disk($this->document->disk)->get($this->document->path);
            if ($fileContents === null) {
                return [];
            }

            // Create a temporary file for PhpSpreadsheet
            $tempPath = tempnam(sys_get_temp_dir(), 'excel_');
            file_put_contents($tempPath, $fileContents);

            $spreadsheet = IOFactory::load($tempPath);
            $worksheet = $spreadsheet->getActiveSheet();
            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();

            // Get all data
            $allData = [];
            for ($row = 1; $row <= $highestRow; $row++) {
                $rowData = [];
                for ($col = 'A'; $col <= $highestColumn; $col++) {
                    $cellValue = $worksheet->getCell($col . $row)->getValue();
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
            Log::error('Failed to process report data', [
                'document_id' => $this->document->id,
                'message' => $exception->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Apply filters to the data
     */
    private function applyFilters($data)
    {
        if (empty($this->filterColumn) || empty($this->filterValue)) {
            return $data;
        }

        $filterColumnIndex = (int) $this->filterColumn;
        $filterValue = strtolower(trim($this->filterValue));

        return array_filter($data, function($row) use ($filterColumnIndex, $filterValue) {
            if (!isset($row[$filterColumnIndex])) {
                return false;
            }
            
            $cellValue = strtolower(trim($row[$filterColumnIndex]));
            return strpos($cellValue, $filterValue) !== false;
        });
    }

    /**
     * Select only the chosen columns from the data
     */
    private function selectColumns($data)
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
    private function createExcelFile($data)
    {
        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();

        // Add headers
        $headers = [];
        foreach ($this->selectedColumns as $columnIndex) {
            $headers[] = $this->columns[$columnIndex]['name'] ?? "Column {$columnIndex}";
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
        $tempPath = tempnam(sys_get_temp_dir(), 'generated_report_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        // Clean up
        unset($spreadsheet);
        unset($worksheet);
        unset($writer);

        return $tempPath;
    }

    /**
     * Get report description based on filters and columns
     */
    private function getReportDescription()
    {
        $description = 'Generated report with ' . count($this->selectedColumns) . ' selected columns';
        
        if (!empty($this->filterColumn) && !empty($this->filterValue)) {
            $filterColumnName = $this->columns[$this->filterColumn]['name'] ?? "Column {$this->filterColumn}";
            $description .= " filtered by {$filterColumnName} containing '{$this->filterValue}'";
        }

        return $description;
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
     * Determine if we should use queue for report generation
     */
    private function shouldUseQueue(): bool
    {
        // Use queue for files larger than 1MB or with many columns
        $fileSize = Storage::disk($this->document->disk)->size($this->document->path);
        return $fileSize > 1024 * 1024 || count($this->columns) > 20;
    }

    /**
     * Generate report asynchronously using queue
     */
    private function generateReportAsync()
    {
        try {
            // Dispatch the queued job
            \App\Jobs\GenerateReportJob::dispatch(
                $this->document->id,
                Auth::id(),
                $this->selectedColumns,
                $this->filterColumn,
                $this->filterValue,
                session()->getId()
            );

            // Show a different loading message for large files
            $this->dispatch('showLoading', __('Processing large file... This may take a few minutes.'));
            
            // Start polling for job completion
            $this->dispatch('startPolling');

        } catch (Throwable $exception) {
            Log::error('Failed to dispatch report generation job', [
                'document_id' => $this->document->id,
                'user_id' => Auth::id(),
                'message' => $exception->getMessage(),
            ]);

            $this->dispatch('hideLoading');
            $this->dispatch('showError', __('Failed to start report generation: ') . $exception->getMessage());
        }
    }

    /**
     * Clean up resources when component is unmounted
     */
    public function dehydrate()
    {
        // Clear any large data from memory
        $this->columns = [];
        $this->selectedColumns = [];
    }

    #[Layout('components.layouts.app')]
    public function render(): ViewContract
    {
        return view('livewire.reports.generate', [
            'title' => __('Generate Report'),
        ]);
    }
}
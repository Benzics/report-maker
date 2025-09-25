<?php

namespace App\Livewire\Reports;

use App\Models\Document;
use App\Models\GeneratedReport;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Preview extends Component
{
    public $reportData = [];
    public $isLoading = false;
    public $error = '';

    public function mount()
    {
        // Get report data from URL parameter
        $reportParam = request()->get('report');
        
        if (!$reportParam) {
            $this->error = __('No report data provided.');
            return;
        }

        try {
            $report = unserialize(base64_decode($reportParam));
            
            if (!$report instanceof GeneratedReport) {
                $this->error = __('Invalid report data.');
                return;
            }

            // Get the original document
            $document = Document::find($report->document_id);
            if (!$document) {
                $this->error = __('Original document not found.');
                return;
            }

            // Format report data for display
            $this->reportData = [
                'id' => $report->id,
                'name' => $report->name,
                'description' => $report->description,
                'original_document' => $document->original_name,
                'selected_columns_count' => count($report->selected_columns),
                'file_size' => $this->formatFileSize($report->file_size),
                'file_name' => $report->file_name,
                'download_url' => route('reports.download', ['report' => $report->id]),
                'is_saved' => $report->is_saved,
                'raw_data' => $report, // Keep raw data for saving
            ];

        } catch (\Exception $e) {
            $this->error = __('Failed to load report data.');
        }
    }

    public function saveReport()
    {
        try {
            $this->isLoading = true;

            if (empty($this->reportData['raw_data'])) {
                $this->dispatch('showError', __('No report data to save.'));
                return;
            }

            $report = $this->reportData['raw_data'];
            
            // Check if already saved
            if ($report->is_saved) {
                $this->dispatch('showError', __('This report is already saved.'));
                return;
            }

            // Save to database
            $report->is_saved = true;
            $report->save();

            // Update the display data
            $this->reportData['is_saved'] = true;

            $this->dispatch('showSuccess', __('Report saved successfully! You can find it in your dashboard.'));

        } catch (\Exception $e) {
            $this->dispatch('showError', __('Failed to save report: ') . $e->getMessage());
        } finally {
            $this->isLoading = false;
        }
    }

    private function formatFileSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    #[Layout('components.layouts.app')]
    public function render(): ViewContract
    {
        return view('livewire.reports.preview', [
            'title' => __('Report Preview'),
        ]);
    }
}

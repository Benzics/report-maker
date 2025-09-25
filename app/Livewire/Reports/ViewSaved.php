<?php

namespace App\Livewire\Reports;

use App\Models\Document;
use App\Models\GeneratedReport;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class ViewSaved extends Component
{
    use WithPagination;

    public $search = '';
    public $documentId;
    public $document;

    public function mount($documentId)
    {
        $this->documentId = $documentId;
        $this->document = Document::where('id', $documentId)
            ->where('user_id', Auth::id())
            ->first();

        if (!$this->document) {
            abort(404, 'Document not found.');
        }
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function getReportsProperty()
    {
        return $this->document->generatedReports()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('description', 'like', '%' . $this->search . '%');
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();
    }

    public function downloadReport($reportId): void
    {
        try {
            $report = GeneratedReport::where('id', $reportId)
                ->where('document_id', $this->documentId)
                ->where('user_id', Auth::id())
                ->first();

            if (!$report) {
                $this->dispatch('showError', __('Report not found.'));
                return;
            }

            if (!$report->file_path || !Storage::exists($report->file_path)) {
                $this->dispatch('showError', __('Report file not found.'));
                return;
            }

            // Redirect to the download route
            $this->redirect(route('reports.download', $reportId));
        } catch (Throwable $exception) {
            Log::error('Report download failed', [
                'user_id' => Auth::id(),
                'report_id' => $reportId,
                'document_id' => $this->documentId,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            $this->dispatch('showError', __('Failed to download report. Please try again.'));
        }
    }

    public function deleteReport($reportId): void
    {
        try {
            $report = GeneratedReport::where('id', $reportId)
                ->where('document_id', $this->documentId)
                ->where('user_id', Auth::id())
                ->first();

            if (!$report) {
                $this->dispatch('showError', __('Report not found.'));
                return;
            }

            // Delete the file from storage
            if ($report->file_path && Storage::exists($report->file_path)) {
                Storage::delete($report->file_path);
            }

            // Delete the database record
            $report->delete();

            $this->dispatch('showSuccess', __('Report deleted successfully.'));
        } catch (Throwable $exception) {
            Log::error('Report deletion failed', [
                'user_id' => Auth::id(),
                'report_id' => $reportId,
                'document_id' => $this->documentId,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            $this->dispatch('showError', __('Failed to delete report. Please try again.'));
        }
    }

    #[Layout('components.layouts.app')]
    public function render(): ViewContract
    {
        return view('livewire.reports.view-saved', [
            'title' => __('Saved Reports - :document', ['document' => $this->document->original_name]),
        ]);
    }
}
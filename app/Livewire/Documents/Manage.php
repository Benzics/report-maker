<?php

namespace App\Livewire\Documents;

use App\Models\Document;
use App\Models\User;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class Manage extends Component
{
    use WithPagination;

    public $search = '';

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function getDocumentsProperty()
    {
        return Auth::user()->documents()
            ->when($this->search, function ($query) {
                $query->where('original_name', 'like', '%' . $this->search . '%');
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();
    }

    public function navigateToReport($documentId): void
    {
        $document = Document::where('id', $documentId)
            ->where('user_id', Auth::id())
            ->first();

        if (!$document) {
            $this->dispatch('showError', __('Document not found.'));
            return;
        }

        // Show loading dialog and redirect
        $this->js('
            Swal.fire({
                title: "Loading document...",
                text: "Please wait while we prepare your report generation page.",
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            setTimeout(() => {
                window.location.href = "' . route('reports.generate', $document->id) . '";
            }, 100);
        ');
    }

    public function deleteDocument($documentId): void
    {
        try {
            $document = Document::where('id', $documentId)
                ->where('user_id', Auth::id())
                ->first();

            if (!$document) {
                $this->dispatch('showError', __('Document not found.'));
                return;
            }

            // Delete the file from storage
            if (Storage::disk($document->disk)->exists($document->path)) {
                Storage::disk($document->disk)->delete($document->path);
            }

            // Delete the database record
            $document->delete();

            $this->dispatch('showSuccess', __('Document deleted successfully.'));
        } catch (Throwable $exception) {
            Log::error('Document deletion failed', [
                'user_id' => Auth::id(),
                'document_id' => $documentId,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            $this->dispatch('showError', __('Failed to delete document. Please try again.'));
        }
    }

    #[Layout('components.layouts.app')]
    public function render(): ViewContract
    {
        return view('livewire.documents.manage', [
            'title' => __('Dashboard'),
        ]);
    }
}
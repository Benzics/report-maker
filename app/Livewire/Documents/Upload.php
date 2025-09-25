<?php

namespace App\Livewire\Documents;

use App\Models\Document;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

class Upload extends Component
{
    use WithFileUploads;

    /**
     * @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null
     */
    public $file = null;

    protected function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xls,xlsx,csv', 'max:50240'], // max ~10MB
        ];
    }

    public function save(): void
    {
        $this->validate();

        $user = Auth::user();
        $disk = 'local';

        $storedPath = null;

        try {
            $originalName = $this->file->getClientOriginalName();
            $mimeType = $this->file->getClientMimeType();
            $size = $this->file->getSize();

            $storedPath = $this->file->store('documents/'.$user->id, $disk);

            Document::create([
                'user_id' => $user->id,
                'original_name' => $originalName,
                'path' => $storedPath,
                'disk' => $disk,
                'mime_type' => $mimeType,
                'size' => $size,
                'status' => 'pending',
            ]);

            $this->reset('file');

            session()->flash('livewire-toast', [
                'type' => 'success',
                'message' => __('Document uploaded successfully.')
            ]);
            $this->redirectRoute('dashboard');
        } catch (Throwable $exception) {
            if ($storedPath && Storage::disk($disk)->exists($storedPath)) {
                // Cleanup partially stored file on failure
                try {
                    Storage::disk($disk)->delete($storedPath);
                } catch (Throwable $cleanupException) {
                    Log::error('Failed to delete partially uploaded file.', [
                        'path' => $storedPath,
                        'disk' => $disk,
                        'message' => $cleanupException->getMessage(),
                        'exception' => $cleanupException,
                    ]);
                }
            }

            Log::error('Document upload failed', [
                'user_id' => $user?->id,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            $this->addError('file', __('Upload failed. Please try again.'));
            $this->dispatch('livewire-toast', 'showError', __('Upload failed. Please try again.'));
        }
    }

    #[Layout('components.layouts.app')]
    public function render(): ViewContract
    {
        return view('livewire.documents.upload', [
            'title' => __('Upload Document'),
        ]);
    }
}



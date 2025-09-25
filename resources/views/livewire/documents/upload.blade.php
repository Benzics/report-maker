<div>
    <div class="max-w-2xl">
        <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Upload a new document') }}</h1>
        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Choose an Excel file to upload.') }}</p>

        <div class="mt-6 overflow-hidden rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <form wire:submit="save" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('File') }}</label>
                    <input type="file" wire:model="file" accept=".xls,.xlsx,.csv" class="mt-2 block w-full cursor-pointer rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-neutral-700 dark:bg-zinc-800 dark:text-zinc-100" />
                    @error('file')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                <div class="pt-2 mt-2">
                    <div wire:loading.remove wire:target="upload">
                        <flux:button type="submit" style="cursor:pointer" variant="primary" :disabled="!$file">
                            {{ __('Upload') }}
                        </flux:button>
                    </div>
                    <div wire:loading wire:target="upload">
                        <flux:button variant="primary" disabled>
                            <span class="flex items-center space-x-2">
                                <svg class="h-5 w-5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>{{ __('Uploading...') }}</span>
                            </span>
                        </flux:button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>



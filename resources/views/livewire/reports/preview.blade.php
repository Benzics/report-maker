<div class="flex h-full w-full flex-1 flex-col gap-6" 
     x-data="{
         init() {
             this.$wire.on('showError', (message) => {
                 Swal.fire({
                     icon: 'error',
                     title: '{{ __('Error') }}',
                     text: message,
                     confirmButtonText: '{{ __('OK') }}'
                 });
             });
             
             this.$wire.on('showSuccess', (message) => {
                 Swal.fire({
                     icon: 'success',
                     title: '{{ __('Success') }}',
                     text: message,
                     confirmButtonText: '{{ __('OK') }}'
                 });
             });
         }
     }">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Report Preview') }}</h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('Your report has been generated successfully. You can download it or save it to your account.') }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('dashboard') }}" class="inline-flex items-center px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-md text-sm font-medium text-zinc-700 dark:text-zinc-300 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                {{ __('Back to Dashboard') }}
            </a>
        </div>
    </div>

    @if($error)
        <!-- Error State -->
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-neutral-200 dark:border-neutral-700 p-6">
            <div class="text-center">
                <svg class="mx-auto h-12 w-12 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Error') }}</h3>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $error }}</p>
                <div class="mt-6">
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        {{ __('Back to Dashboard') }}
                    </a>
                </div>
            </div>
        </div>
    @elseif(!empty($reportData))
        <!-- Report Information -->
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-neutral-200 dark:border-neutral-700 p-6">
            <h2 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">{{ __('Report Details') }}</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">{{ __('Report Name') }}</h3>
                    <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $reportData['name'] }}</p>
                </div>
                
                <div>
                    <h3 class="text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">{{ __('Original Document') }}</h3>
                    <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $reportData['original_document'] }}</p>
                </div>
                
                <div>
                    <h3 class="text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">{{ __('Selected Columns') }}</h3>
                    <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $reportData['selected_columns_count'] }} columns</p>
                </div>
                
                <div>
                    <h3 class="text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">{{ __('File Size') }}</h3>
                    <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $reportData['file_size'] }}</p>
                </div>
            </div>
            
            @if($reportData['description'])
                <div class="mt-4">
                    <h3 class="text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">{{ __('Description') }}</h3>
                    <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $reportData['description'] }}</p>
                </div>
            @endif

            @if($reportData['is_saved'])
                <div class="mt-4 p-3 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-md">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-green-800 dark:text-green-200">
                                {{ __('This report has been saved to your account.') }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Actions -->
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-neutral-200 dark:border-neutral-700 p-6">
            <h2 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">{{ __('Actions') }}</h2>
            
            <div class="flex flex-col sm:flex-row gap-4">
                <!-- Download Button -->
                <a href="{{ $reportData['download_url'] }}" 
                   class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    {{ __('Download Report') }}
                </a>

                <!-- Save to Database Button -->
                @if(!$reportData['is_saved'])
                    <button 
                        wire:click="saveReport"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center px-6 py-3 border border-neutral-300 dark:border-neutral-600 text-base font-medium rounded-md text-zinc-700 dark:text-zinc-300 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        <span wire:loading.remove wire:target="saveReport">{{ __('Save to My Reports') }}</span>
                        <span wire:loading wire:target="saveReport">{{ __('Saving...') }}</span>
                    </button>
                @else
                    <div class="inline-flex items-center px-6 py-3 border border-green-300 dark:border-green-600 text-base font-medium rounded-md text-green-700 dark:text-green-300 bg-green-50 dark:bg-green-900/30">
                        <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        {{ __('Already Saved') }}
                    </div>
                @endif
            </div>
        </div>
    @else
        <!-- Loading State -->
        <div class="flex items-center justify-center py-12">
            <div class="text-center">
                <svg class="animate-spin h-8 w-8 text-blue-500 mx-auto" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Loading report...') }}</p>
            </div>
        </div>
    @endif
</div>

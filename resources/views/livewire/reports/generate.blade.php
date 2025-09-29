<div class="flex h-full w-full flex-1 flex-col gap-6" 
     x-data="{
         showForm: true,
         init() {
             this.$wire.on('showLoading', (message) => {
                 Swal.fire({
                     title: '{{ __('Generating Report') }}',
                     text: message,
                     allowOutsideClick: false,
                     allowEscapeKey: false,
                     showConfirmButton: false,
                     didOpen: () => {
                         Swal.showLoading();
                     }
                 });
             });
             
             this.$wire.on('hideLoading', () => {
                 Swal.close();
             });
             
             this.$wire.on('showRefreshLoading', (message) => {
                 Swal.fire({
                     title: '{{ __('Refreshing Data') }}',
                     text: message,
                     allowOutsideClick: false,
                     allowEscapeKey: false,
                     showConfirmButton: false,
                     didOpen: () => {
                         Swal.showLoading();
                     }
                 });
             });
             
             this.$wire.on('hideRefreshLoading', () => {
                 Swal.close();
             });
             
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
             
             this.$wire.on('showValidationError', (message) => {
                 Swal.fire({
                     icon: 'warning',
                     title: '{{ __('Validation Error') }}',
                     text: message,
                     confirmButtonText: '{{ __('OK') }}',
                     confirmButtonColor: '#f59e0b'
                 });
             });
             
             // Handle async column loading
             this.$wire.on('loadColumns', () => {
                 // Small delay to ensure UI is rendered
                 setTimeout(() => {
                     this.$wire.call('loadDocumentColumns');
                 }, 100);
             });
             
            // Listen for report generation completion via Echo
            this.setupEchoListener();
            
            // Fallback polling for job completion (keep as backup)
            let pollingInterval = null;
            
            this.$wire.on('startPolling', () => {
                if (pollingInterval) clearInterval(pollingInterval);
                
                pollingInterval = setInterval(() => {
                    // Check if there are any completed reports for this user
                    fetch('/api/reports/check-completion', {
                        method: 'GET',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.completed) {
                            clearInterval(pollingInterval);
                            Swal.close(); // Hide loading dialog
                            Swal.fire({
                                icon: 'success',
                                title: '{{ __('Report Generated!') }}',
                                text: 'Your report has been generated successfully!',
                                confirmButtonText: '{{ __('Download Report') }}',
                                showCancelButton: true,
                                cancelButtonText: '{{ __('Close') }}'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.href = data.download_url;
                                }
                            });
                        } else if (data.failed) {
                            clearInterval(pollingInterval);
                            Swal.close(); // Hide loading dialog
                            Swal.fire({
                                icon: 'error',
                                title: '{{ __('Report Generation Failed') }}',
                                text: data.error_message || 'Report generation failed. Please try again.',
                                confirmButtonText: '{{ __('OK') }}'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error checking report status:', error);
                    });
                }, 3000); // Check every 3 seconds
            });
            
            this.$wire.on('stopPolling', () => {
                if (pollingInterval) {
                    clearInterval(pollingInterval);
                    pollingInterval = null;
                }
            });
        },
        
        setupEchoListener() {
            // Listen for report generation events via Echo
            if (window.Echo && '{{ $sessionId }}') {
                const channelName = 'report-generation.{{ $sessionId }}';
                console.log('Setting up Echo listener for channel:', channelName);
                
                window.Echo.private(channelName)
                    .listen('ReportGenerated', (e) => {
                        console.log('Report generation completed via Echo:', e);
                        
                        // Hide loading dialog
                        Swal.close();
                        
                        // Show success message with download option
                        Swal.fire({
                            icon: 'success',
                            title: '{{ __('Report Generated!') }}',
                            text: e.message || 'Your report has been generated successfully!',
                            confirmButtonText: '{{ __('View Saved Reports') }}',
                            showCancelButton: true,
                            cancelButtonText: '{{ __('Close') }}'
                        }).then((result) => {

                            if (result.isConfirmed && e.report && e.report.saved_url) {
                                window.location.href = e.report.saved_url;
                          
                            }
                        });
                    })
                    .listen('ReportGenerationFailed', (e) => {
                        console.log('Report generation failed via Echo:', e);
                        
                        // Hide loading dialog
                        Swal.close();
                        
                        // Show error message
                        Swal.fire({
                            icon: 'error',
                            title: '{{ __('Report Generation Failed') }}',
                            text: e.message || 'Report generation failed. Please try again.',
                            confirmButtonText: '{{ __('OK') }}'
                        });
                    });
            } else {
                console.warn('Echo not available or sessionId missing, falling back to polling');
            }
         }
     }">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Generate Report') }}</h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('Generate a custom report from') }}: <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $document->original_name ?? '' }}</span>
            </p>
        </div>
        <div class="flex items-center gap-2">
            <button 
                wire:click="clearCache"
                wire:loading.attr="disabled"
                wire:target="clearCache"
                class="inline-flex items-center px-3 py-2 border border-neutral-300 dark:border-neutral-600 rounded-md text-sm font-medium text-zinc-700 dark:text-zinc-300 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                title="{{ __('Refresh document data') }}"
            >
                <svg class="h-4 w-4 mr-1" 
                     wire:loading.remove 
                     wire:target="clearCache" 
                     fill="none" 
                     stroke="currentColor" 
                     viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                <svg class="animate-spin h-4 w-4 mr-1" 
                     wire:loading 
                     wire:target="clearCache" 
                     fill="none" 
                     viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span wire:loading.remove wire:target="clearCache">{{ __('Refresh') }}</span>
                <span wire:loading wire:target="clearCache">{{ __('Refreshing...') }}</span>
            </button>
            <a href="{{ route('dashboard') }}" class="inline-flex items-center px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-md text-sm font-medium text-zinc-700 dark:text-zinc-300 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                {{ __('Back to Dashboard') }}
            </a>
        </div>
    </div>

    @if($error)
        <!-- Document Loading Error Message -->
        <div class="rounded-md bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-3 flex-1">
                    <h3 class="text-sm font-medium text-red-800 dark:text-red-200">{{ __('Error') }}</h3>
                    <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                        <p>{{ $error }}</p>
                    </div>
                    <div class="mt-3">
                        <button 
                            wire:click="clearCache"
                            wire:loading.attr="disabled"
                            wire:target="clearCache"
                            class="text-sm text-red-600 dark:text-red-400 hover:text-red-500 dark:hover:text-red-300 underline disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span wire:loading.remove wire:target="clearCache">{{ __('Try refreshing the data') }}</span>
                            <span wire:loading wire:target="clearCache">{{ __('Refreshing...') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Loading State -->
    <div x-show="$wire.isLoading" class="flex items-center justify-center py-12">
        <div class="text-center">
            <svg class="animate-spin h-8 w-8 text-blue-500 mx-auto" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Loading document columns...') }}</p>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-500">{{ __('This may take a moment for large files') }}</p>
        </div>
    </div>

    <!-- Form Section - Show when columns are available -->
    @if(!empty($columns))
        <div x-show="!$wire.isLoading" x-transition>
        <form wire:submit="generateReport">
        <!-- Column Selection -->
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-neutral-200 dark:border-neutral-700 p-6">
            <h2 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">{{ __('Select Columns to Include') }}</h2>
            <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-6">{{ __('Choose which columns you want to include in your report.') }}</p>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" 
                 wire:loading.class="opacity-50 pointer-events-none"
                 wire:target="clearCache">
                @foreach($columns as $index => $column)
                    <label class="flex items-center p-3 border border-neutral-200 dark:border-neutral-600 rounded-lg hover:bg-neutral-50 dark:hover:bg-zinc-800 cursor-pointer transition-colors">
                        <input 
                            type="checkbox" 
                            wire:model.live="selectedColumns"
                            value="{{ $index }}"
                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-neutral-300 dark:border-neutral-600 rounded"
                        />
                        <span class="ml-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $column['name'] }}
                        </span>
                        <span class="ml-auto text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $column['column'] }}
                        </span>
                    </label>
                @endforeach
            </div>

            @if(empty($selectedColumns))
                <div class="mt-4 p-3 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 rounded-md">
                    <p class="text-sm text-amber-800 dark:text-amber-200">
                        {{ __('Please select at least one column to include in your report.') }}
                    </p>
                </div>
            @endif
        </div>

        <!-- Table Style Selection -->
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-neutral-200 dark:border-neutral-700 p-6">
            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">
                {{ __('Table Style') }}
            </h3>
            <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-4">
                {{ __('Choose a color scheme for your generated report. This will apply formatting similar to Excel table styles.') }}
            </p>
            
            <div class="space-y-3">
                <label for="tableStyle" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    {{ __('Select Table Style') }}
                </label>
                <select 
                    id="tableStyle"
                    wire:model.live="tableStyle"
                    class="w-full px-3 py-2 border border-neutral-300 dark:border-neutral-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-zinc-800 dark:text-zinc-100"
                >
                    @foreach($this->getTableStyles() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                
                <div class="mt-2 p-3 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-md">
                    <p class="text-sm text-blue-800 dark:text-blue-200">
                        <strong>{{ __('Preview:') }}</strong> {{ __('The selected style will be applied to your report with appropriate header colors and alternating row colors for better readability.') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Filter Options -->
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-neutral-200 dark:border-neutral-700 p-6">
            <h2 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">{{ __('Filter Data (Optional)') }}</h2>
            <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-6">{{ __('Filter the data by up to three columns and values. All filters work together (AND logic).') }}</p>
            
            <!-- Filter 1 -->
            <div class="mb-6">
                <h3 class="text-md font-medium text-zinc-800 dark:text-zinc-200 mb-4">{{ __('Filter 1') }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4" 
                     wire:loading.class="opacity-50 pointer-events-none"
                     wire:target="clearCache">
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">
                            {{ __('Filter Column') }}
                        </label>
                        <select 
                            wire:model.live="filterColumn"
                            class="block w-full px-3 py-2 border border-neutral-300 dark:border-neutral-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-zinc-800 dark:text-zinc-100"
                        >
                            <option value="">{{ __('Select a column to filter by...') }}</option>
                            @foreach($columns as $index => $column)
                                <option value="{{ $index }}">{{ $column['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">
                            {{ __('Filter Value') }}
                            @if($isDateColumn)
                                <span class="text-xs text-blue-600 dark:text-blue-400 ml-1">({{ __('Date detected') }})</span>
                            @endif
                        </label>
                        
                        @if($isDateColumn)
                            <div class="space-y-3">
                                <!-- Single Date Input -->
                                <div>
                                    <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400 mb-1">
                                        {{ __('Single Date') }}
                                    </label>
                                    <input 
                                        type="date" 
                                        wire:model="filterValue"
                                        class="block w-full px-3 py-2 border border-neutral-300 dark:border-neutral-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-zinc-800 dark:text-zinc-100 disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-gray-100 dark:disabled:bg-zinc-700"
                                        x-bind:disabled="!$wire.filterColumn"
                                    />
                                </div>
                                
                                <!-- Date Range Inputs -->
                                <div class="border-t border-neutral-200 dark:border-neutral-600 pt-3">
                                    <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400 mb-2">
                                        {{ __('Date Range') }}
                                    </label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="block text-xs text-zinc-500 dark:text-zinc-400 mb-1">
                                                {{ __('From') }}
                                            </label>
                                            <input 
                                                type="date" 
                                                wire:model="filterValueStart"
                                                class="block w-full px-3 py-2 border border-neutral-300 dark:border-neutral-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-zinc-800 dark:text-zinc-100 disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-gray-100 dark:disabled:bg-zinc-700"
                                                x-bind:disabled="!$wire.filterColumn"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs text-zinc-500 dark:text-zinc-400 mb-1">
                                                {{ __('To') }}
                                            </label>
                                            <input 
                                                type="date" 
                                                wire:model="filterValueEnd"
                                                class="block w-full px-3 py-2 border border-neutral-300 dark:border-neutral-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-zinc-800 dark:text-zinc-100 disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-gray-100 dark:disabled:bg-zinc-700"
                                                x-bind:disabled="!$wire.filterColumn"
                                            />
                                        </div>
                                    </div>
                                </div>
                                
                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ __('Use single date for exact match or range for filtering between dates') }}
                                </p>
                            </div>
                        @else
                            <input 
                                type="text" 
                                wire:model="filterValue"
                                placeholder="{{ __('Enter value to filter by...') }}"
                                class="block w-full px-3 py-2 border border-neutral-300 dark:border-neutral-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-zinc-800 dark:text-zinc-100 disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-gray-100 dark:disabled:bg-zinc-700"
                                x-bind:disabled="!$wire.filterColumn"
                            />
                        @endif
                    </div>
                </div>
            </div>

            <!-- Filter 2 -->
            <div class="mb-6">
                <h3 class="text-md font-medium text-zinc-800 dark:text-zinc-200 mb-4">{{ __('Filter 2 (Optional)') }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4" 
                     wire:loading.class="opacity-50 pointer-events-none"
                     wire:target="clearCache">
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">
                            {{ __('Filter Column') }}
                        </label>
                        <select 
                            wire:model.live="filterColumn2"
                            class="block w-full px-3 py-2 border border-neutral-300 dark:border-neutral-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-zinc-800 dark:text-zinc-100"
                        >
                            <option value="">{{ __('Select a column to filter by...') }}</option>
                            @foreach($columns as $index => $column)
                                <option value="{{ $index }}">{{ $column['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">
                            {{ __('Filter Value') }}
                            @if($isDateColumn2)
                                <span class="text-xs text-blue-600 dark:text-blue-400 ml-1">({{ __('Date detected') }})</span>
                            @endif
                        </label>
                        
                        @if($isDateColumn2)
                            <div class="space-y-3">
                                <!-- Single Date Input -->
                                <div>
                                    <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400 mb-1">
                                        {{ __('Single Date') }}
                                    </label>
                                    <input 
                                        type="date" 
                                        wire:model="filterValue2"
                                        class="block w-full px-3 py-2 border border-neutral-300 dark:border-neutral-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-zinc-800 dark:text-zinc-100 disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-gray-100 dark:disabled:bg-zinc-700"
                                        x-bind:disabled="!$wire.filterColumn2"
                                    />
                                </div>
                                
                                <!-- Date Range Inputs -->
                                <div class="border-t border-neutral-200 dark:border-neutral-600 pt-3">
                                    <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400 mb-2">
                                        {{ __('Date Range') }}
                                    </label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="block text-xs text-zinc-500 dark:text-zinc-400 mb-1">
                                                {{ __('From') }}
                                            </label>
                                            <input 
                                                type="date" 
                                                wire:model="filterValue2Start"
                                                class="block w-full px-3 py-2 border border-neutral-300 dark:border-neutral-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-zinc-800 dark:text-zinc-100 disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-gray-100 dark:disabled:bg-zinc-700"
                                                x-bind:disabled="!$wire.filterColumn2"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs text-zinc-500 dark:text-zinc-400 mb-1">
                                                {{ __('To') }}
                                            </label>
                                            <input 
                                                type="date" 
                                                wire:model="filterValue2End"
                                                class="block w-full px-3 py-2 border border-neutral-300 dark:border-neutral-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-zinc-800 dark:text-zinc-100 disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-gray-100 dark:disabled:bg-zinc-700"
                                                x-bind:disabled="!$wire.filterColumn2"
                                            />
                                        </div>
                                    </div>
                                </div>
                                
                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ __('Use single date for exact match or range for filtering between dates') }}
                                </p>
                            </div>
                        @else
                            <input 
                                type="text" 
                                wire:model="filterValue2"
                                placeholder="{{ __('Enter value to filter by...') }}"
                                class="block w-full px-3 py-2 border border-neutral-300 dark:border-neutral-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-zinc-800 dark:text-zinc-100 disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-gray-100 dark:disabled:bg-zinc-700"
                                x-bind:disabled="!$wire.filterColumn2"
                            />
                        @endif
                    </div>
                </div>
            </div>

            <!-- Filter 3 -->
            <div class="mb-4">
                <h3 class="text-md font-medium text-zinc-800 dark:text-zinc-200 mb-4">{{ __('Filter 3 (Optional)') }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4" 
                     wire:loading.class="opacity-50 pointer-events-none"
                     wire:target="clearCache">
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">
                            {{ __('Filter Column') }}
                        </label>
                        <select 
                            wire:model.live="filterColumn3"
                            class="block w-full px-3 py-2 border border-neutral-300 dark:border-neutral-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-zinc-800 dark:text-zinc-100"
                        >
                            <option value="">{{ __('Select a column to filter by...') }}</option>
                            @foreach($columns as $index => $column)
                                <option value="{{ $index }}">{{ $column['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">
                            {{ __('Filter Value') }}
                            @if($isDateColumn3)
                                <span class="text-xs text-blue-600 dark:text-blue-400 ml-1">({{ __('Date detected') }})</span>
                            @endif
                        </label>
                        
                        @if($isDateColumn3)
                            <div class="space-y-3">
                                <!-- Single Date Input -->
                                <div>
                                    <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400 mb-1">
                                        {{ __('Single Date') }}
                                    </label>
                                    <input 
                                        type="date" 
                                        wire:model="filterValue3"
                                        class="block w-full px-3 py-2 border border-neutral-300 dark:border-neutral-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-zinc-800 dark:text-zinc-100 disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-gray-100 dark:disabled:bg-zinc-700"
                                        x-bind:disabled="!$wire.filterColumn3"
                                    />
                                </div>
                                
                                <!-- Date Range Inputs -->
                                <div class="border-t border-neutral-200 dark:border-neutral-600 pt-3">
                                    <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400 mb-2">
                                        {{ __('Date Range') }}
                                    </label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="block text-xs text-zinc-500 dark:text-zinc-400 mb-1">
                                                {{ __('From') }}
                                            </label>
                                            <input 
                                                type="date" 
                                                wire:model="filterValue3Start"
                                                class="block w-full px-3 py-2 border border-neutral-300 dark:border-neutral-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-zinc-800 dark:text-zinc-100 disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-gray-100 dark:disabled:bg-zinc-700"
                                                x-bind:disabled="!$wire.filterColumn3"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs text-zinc-500 dark:text-zinc-400 mb-1">
                                                {{ __('To') }}
                                            </label>
                                            <input 
                                                type="date" 
                                                wire:model="filterValue3End"
                                                class="block w-full px-3 py-2 border border-neutral-300 dark:border-neutral-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-zinc-800 dark:text-zinc-100 disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-gray-100 dark:disabled:bg-zinc-700"
                                                x-bind:disabled="!$wire.filterColumn3"
                                            />
                                        </div>
                                    </div>
                                </div>
                                
                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ __('Use single date for exact match or range for filtering between dates') }}
                                </p>
                            </div>
                        @else
                            <input 
                                type="text" 
                                wire:model="filterValue3"
                                placeholder="{{ __('Enter value to filter by...') }}"
                                class="block w-full px-3 py-2 border border-neutral-300 dark:border-neutral-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-zinc-800 dark:text-zinc-100 disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-gray-100 dark:disabled:bg-zinc-700"
                                x-bind:disabled="!$wire.filterColumn3"
                            />
                        @endif
                    </div>
                </div>
            </div>

            <div x-show="($wire.filterColumn && !$wire.filterValue && !$wire.filterValueStart && !$wire.filterValueEnd) || ($wire.filterColumn2 && !$wire.filterValue2 && !$wire.filterValue2Start && !$wire.filterValue2End) || ($wire.filterColumn3 && !$wire.filterValue3 && !$wire.filterValue3Start && !$wire.filterValue3End)" class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-md">
                <p class="text-sm text-blue-800 dark:text-blue-200">
                    {{ __('Please enter values or ranges for all selected filter columns.') }}
                </p>
            </div>
        </div>

        <!-- Generate Report Button -->
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-neutral-200 dark:border-neutral-700 p-6">
            <div class="text-center">
                <button 
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="generateReport"
                    x-bind:disabled="$wire.selectedColumns.length === 0"
                    class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                    <svg class="h-5 w-5 mr-2" 
                         wire:loading.remove 
                         wire:target="generateReport" 
                         fill="none" 
                         stroke="currentColor" 
                         viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <svg class="animate-spin h-5 w-5 mr-2" 
                         wire:loading 
                         wire:target="generateReport" 
                         fill="none" 
                         viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="generateReport">{{ __('Generate Report') }}</span>
                    <span wire:loading wire:target="generateReport">{{ __('Generating...') }}</span>
                </button>
                
                <p x-show="$wire.selectedColumns.length === 0" class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Please select at least one column to generate a report.') }}
                </p>
            </div>
        </div>
        </form>
        </div>
    @endif
</div>


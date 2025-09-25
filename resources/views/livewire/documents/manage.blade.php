<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Welcome, :name', ['name' => auth()->user()->name]) }}</h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Here are your uploaded documents.') }}</p>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="flex items-center gap-4">
        <div class="flex-1 max-w-md">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Search documents...') }}"
                    class="block w-full pl-10 pr-3 py-2 border border-neutral-300 dark:border-neutral-600 rounded-md leading-5 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 placeholder-zinc-500 dark:placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:ring-blue-400 dark:focus:border-blue-400"
                />
            </div>
        </div>
        @if($search)
            <button 
                wire:click="$set('search', '')"
                class="inline-flex items-center px-3 py-2 border border-neutral-300 dark:border-neutral-600 rounded-md text-sm font-medium text-zinc-700 dark:text-zinc-300 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
                <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
                {{ __('Clear') }}
            </button>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-zinc-900">
        @if($search)
            <div class="px-4 py-3 border-b border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-zinc-800">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Search results for') }}: <span class="font-medium text-zinc-900 dark:text-zinc-100">"{{ $search }}"</span>
                    </p>
                    <div wire:loading class="flex items-center text-sm text-zinc-500 dark:text-zinc-400">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-zinc-500" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ __('Searching...') }}
                    </div>
                </div>
            </div>
        @endif
        <div class="overflow-x-auto">
            <table class="min-w-full w-full table-auto divide-y divide-neutral-200 dark:divide-neutral-700">
            
                <thead class="bg-neutral-50/60 dark:bg-zinc-800/60">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-600 dark:text-zinc-300">{{ __('File name') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-600 dark:text-zinc-300">{{ __('Size') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-600 dark:text-zinc-300">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-600 dark:text-zinc-300">{{ __('Uploaded') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-zinc-600 dark:text-zinc-300">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                    @forelse($this->documents ?? [] as $doc)
                        <tr class="hover:bg-neutral-50 dark:hover:bg-zinc-800/50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-neutral-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">XLS</span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $doc->original_name }}</p>
                                        <p class="truncate text-xs text-zinc-600 dark:text-zinc-300">{{ $doc->mime_type ?? 'application/vnd.ms-excel' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-zinc-700 dark:text-zinc-200">{{ $doc->size ? number_format($doc->size / 1024, 1) . ' KB' : '—' }}</td>
                            <td class="px-4 py-3">
                                @php
                                    $statusColors = [
                                        'pending' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
                                        'processing' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
                                        'failed' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
                                        'completed' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
                                    ];
                                    $badge = $statusColors[$doc->status] ?? 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200';
                                @endphp
                                <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium {{ $badge }}">{{ ucfirst($doc->status) }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-zinc-700 dark:text-zinc-200">{{ $doc->created_at->diffForHumans() }}</td>
                            <td class="px-4 py-3 text-right relative overflow-visible">
                              <el-dropdown class="inline-block">
                              <button class="inline-flex w-full justify-center rounded-md bg-neutral-100 dark:bg-neutral-800 px-3 py-2 text-sm font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-colors">
                                ⋯
                              </button>

                              <el-menu anchor="bottom end" popover class="w-56 origin-top-right rounded-md bg-gray-800 outline-1 -outline-offset-1 outline-white/10 transition transition-discrete [--anchor-gap:--spacing(2)] data-closed:scale-95 data-closed:transform data-closed:opacity-0 data-enter:duration-100 data-enter:ease-out data-leave:duration-75 data-leave:ease-in">
                                <div class="py-1">
                                  <button 
                                    wire:click="navigateToReport({{ $doc->id }})"
                                    class="block w-full px-4 py-2 text-sm text-gray-300 text-center focus:bg-white/5 focus:text-white focus:outline-hidden hover:bg-white/10 transition-colors"
                                  >
                                    Generate Report
                                  </button>
                                  <a href="{{ route('reports.saved', $doc->id) }}" class="block px-4 py-2 text-sm text-gray-300 text-center focus:bg-white/5 focus:text-white focus:outline-hidden">View Saved Reports</a>
                                  <button 
                                    type="button" 
                                    onclick="confirmDelete({{ $doc->id }})"
                                    class="block w-full px-4 py-2 text-center text-sm text-red-500 focus:bg-red-500 focus:text-white focus:outline-hidden"
                                  >
                                    Delete
                                  </button>
                                </div>
                              </el-menu>
                            </el-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-sm text-zinc-600 dark:text-zinc-300">{{ __('No documents uploaded yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(($this->documents ?? null) && method_exists($this->documents, 'hasPages') && $this->documents->hasPages())
            <div class="border-t border-neutral-200 px-4 py-3 dark:border-neutral-700">
                {{ $this->documents->links() }}
            </div>
        @endif
    </div>
</div>

<script>
    function confirmDelete(documentId) {
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                @this.deleteDocument(documentId);
            }
        });
    }

</script>
<div class="fixed bottom-4 right-4 z-50 @if($hideOnClick) cursor-pointer @endif"
    x-data="{show: false, timeout: null, duration: null}"
    @if($message)
        x-init="() => { duration = @this.duration; clearTimeout(timeout); show = true;
                if( duration > 0 ) {timeout = setTimeout(() => { show = false }, duration); }}"
    @endif
    @new-toast.window="duration = @this.duration; clearTimeout(timeout); show = true;
                if( duration > 0 ) { timeout = setTimeout(() => { show = false }, duration); }"
    @click="if(@this.hideOnClick) { show = false; }"
    x-show="show"

    @if($transition)
        x-transition:enter="transition ease-in-out duration-300" 
        x-transition:enter-start="opacity-0 transform {{$this->transitioClasses['enter_start_class']}}" 
        x-transition:enter-end="opacity-100 transform {{$this->transitioClasses['enter_end_class']}}" 
        x-transition:leave="transition ease-in-out duration-500" 
        x-transition:leave-start="opacity-100 transform {{$this->transitioClasses['leave_start_class']}}"
        x-transition:leave-end="opacity-0 transform {{$this->transitioClasses['leave_start_class']}}"
    @endif
>
    @if($message)
        <div class="flex bg-{{$bgColorCss}}-500 border-{{$bgColorCss}}-700 py-3 px-4 shadow-lg mb-2 rounded-lg max-w-sm">
            <!-- icons -->
            {{-- @if($showIcon)
                <div class="text-{{$bgColorCss}}-500 rounded-full bg-{{$textColorCss}} mr-3 flex-shrink-0">
                    @include('livewire-toast::icons.' . $type)
                </div>
            @endif --}}
            <!-- message -->
            <div class="text-{{$textColorCss}} text-sm font-medium flex-1">
                {{$message}}
            </div>
        </div>
    @endif
</div>
{{-- 
===========================
resources/views/filament/widgets/quick-session-starter.blade.php
Compact dan elegant session starter widget dengan modern design
===========================
--}}

<x-filament-widgets::widget class="fi-wi-stats-overview">
    <div class="fi-wi-stats-overview-stats-ctn grid gap-6 lg:grid-cols-4">
        {{-- Session Control Card --}}
        <div class="fi-wi-stats-overview-stat relative rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="grid gap-y-2">
                {{-- Header with Icon --}}
                <div class="flex items-center gap-2">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-green-500/10">
                        <svg class="h-5 w-5 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Session Control</h3>
                        <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">Quick Start</p>
                    </div>
                </div>

                {{-- Action Button --}}
                <div class="mt-4">
                    {{ ($this->startSessionAction)(['size' => 'sm', 'outlined' => false]) }}
                </div>
            </div>
        </div>

        {{-- Stats Cards --}}
        <div class="fi-wi-stats-overview-stat relative rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="grid gap-y-2">
                <div class="flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-500/10">
                        <svg class="h-4 w-4 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Active Sessions</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $this->getActiveSessionsCount() }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="fi-wi-stats-overview-stat relative rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="grid gap-y-2">
                <div class="flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-green-500/10">
                        <svg class="h-4 w-4 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5a2.25 2.25 0 0 0 2.25-2.25m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5a2.25 2.25 0 0 1 2.25 2.25v7.5" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Today's Sessions</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $this->getTodaySessionsCount() }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="fi-wi-stats-overview-stat relative rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="grid gap-y-2">
                <div class="flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-yellow-500/10">
                        <svg class="h-4 w-4 text-yellow-600 dark:text-yellow-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 1-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m6 4.5v-3.75m-6 3.75h6m6-6V9.75a1.125 1.125 0 0 1 1.125-1.125H21M15 10.5h3.75M15 10.5v3.75m3.75-3.75v3.75" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Working Equipment</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $this->getWorkingEquipmentCount() }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Session Information Panel (Compact) --}}
    @if($this->getActiveSessionsCount() > 0)
    <div class="mt-6 rounded-lg bg-green-50 p-4 dark:bg-green-900/20">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
            <div class="ml-3 flex-1">
                <h3 class="text-sm font-medium text-green-800 dark:text-green-200">
                    Operations Active
                </h3>
                <div class="mt-1 text-sm text-green-700 dark:text-green-300">
                    <p>{{ $this->getActiveSessionsCount() }} loading session(s) currently in progress. Monitor progress in Loading Sessions.</p>
                </div>
            </div>
            <div class="ml-3">
                <a href="/admin/loading-sessions" 
                   class="inline-flex items-center rounded-md bg-green-100 px-2.5 py-1.5 text-xs font-medium text-green-800 hover:bg-green-200 dark:bg-green-800 dark:text-green-100 dark:hover:bg-green-700">
                    View Sessions
                    <svg class="ml-1 h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                </a>
            </div>
        </div>
    </div>
    @endif

    {{-- Current Shift Info (Compact) --}}
    <div class="mt-4 flex items-center justify-between rounded-lg bg-gray-50 px-4 py-2 dark:bg-gray-800/50">
        <div class="flex items-center space-x-3 text-sm text-gray-600 dark:text-gray-400">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <span>Current Time: {{ now()->format('H:i:s') }}</span>
            <span class="text-gray-400">|</span>
            <span>Shift: 
                @php
                    $hour = now()->hour;
                    if ($hour >= 7 && $hour < 15) {
                        echo 'A (07:00-15:00)';
                    } elseif ($hour >= 15 && $hour < 23) {
                        echo 'B (15:00-23:00)';
                    } else {
                        echo 'C (23:00-07:00)';
                    }
                @endphp
            </span>
        </div>
        
        <div class="flex items-center space-x-2">
            <div class="h-2 w-2 rounded-full bg-green-500"></div>
            <span class="text-xs text-gray-500 dark:text-gray-400">System Online</span>
        </div>
    </div>

    {{-- Auto-refresh indicator --}}
    <div class="mt-2 flex justify-center">
        <span class="text-xs text-gray-400 dark:text-gray-500" wire:poll.30s>
            Auto-refresh: 30s
        </span>
    </div>
</x-filament-widgets::widget>

{{-- Custom Styles --}}
@push('styles')
<style>
    /* Widget hover animations */
    .fi-wi-stats-overview-stat {
        transition: all 0.2s ease-in-out;
    }
    
    .fi-wi-stats-overview-stat:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }

    /* Pulse animation untuk online indicator */
    @keyframes pulse-green {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    
    .animate-pulse-green {
        animation: pulse-green 2s infinite;
    }
</style>
@endpush

{{-- JavaScript untuk dynamic updates --}}
@push('scripts')
<script>
    // Update current time setiap detik
    setInterval(function() {
        const timeElements = document.querySelectorAll('[data-time]');
        timeElements.forEach(element => {
            element.textContent = 'Current Time: ' + new Date().toLocaleTimeString('en-US', {hour12: false});
        });
    }, 1000);

    // Handle session started event
    document.addEventListener('session-started', function(event) {
        // Show success notification
        window.dispatchEvent(new CustomEvent('notify', {
            detail: {
                type: 'success',
                message: `Loading session ${event.detail.sessionCode} started successfully!`
            }
        }));
        
        // Refresh widget setelah 2 detik
        setTimeout(() => {
            window.livewire.find('{{ $this->getId() }}').call('$refresh');
        }, 2000);
    });
</script>
@endpush
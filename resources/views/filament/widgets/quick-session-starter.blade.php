// ===========================
// resources/views/filament/widgets/quick-session-starter.blade.php
// ===========================
<x-filament-widgets::widget>
    <x-filament::section>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <!-- Quick Stats -->
            <div class="md:col-span-3">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <!-- Active Sessions -->
                    <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg p-4 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-green-100 text-sm font-medium">Active Sessions</p>
                                <p class="text-2xl font-bold">{{ $this->getActiveSessionsCount() }}</p>
                            </div>
                            <div class="bg-white bg-opacity-20 rounded-full p-3">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-7 4h12l-1-7H7l-1 7z"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Today's Sessions -->
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg p-4 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-blue-100 text-sm font-medium">Today's Sessions</p>
                                <p class="text-2xl font-bold">{{ $this->getTodaySessionsCount() }}</p>
                            </div>
                            <div class="bg-white bg-opacity-20 rounded-full p-3">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Working Equipment -->
                    <div class="bg-gradient-to-r from-orange-500 to-orange-600 rounded-lg p-4 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-orange-100 text-sm font-medium">Working Equipment</p>
                                <p class="text-2xl font-bold">{{ $this->getWorkingEquipmentCount() }}</p>
                            </div>
                            <div class="bg-white bg-opacity-20 rounded-full p-3">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Session Status Info -->
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Session Control Center</h3>
                    <p class="text-gray-600 dark:text-gray-300 text-sm mb-4">
                        Manage loading operations and monitor equipment status in real-time. Start new sessions, track progress, and ensure operational efficiency.
                    </p>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Current Time: {{ now()->format('H:i:s') }} | 
                        Current Shift: {{ 
                            now()->hour >= 7 && now()->hour < 15 ? 'A (07:00-15:00)' : 
                            (now()->hour >= 15 && now()->hour < 23 ? 'B (15:00-23:00)' : 'C (23:00-07:00)') 
                        }}
                    </div>
                </div>
            </div>

            <!-- Start Session Button -->
            <div class="flex flex-col justify-center">
                <div class="bg-gradient-to-br from-green-400 to-green-600 rounded-xl p-6 text-center text-white shadow-lg">
                    <div class="mb-4">
                        <svg class="w-12 h-12 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-7 4h12l-1-7H7l-1 7z"/>
                        </svg>
                        <h3 class="text-lg font-bold">Start New Session</h3>
                        <p class="text-sm text-green-100">Begin loading operations</p>
                    </div>
                    
                    {{ $this->startSessionAction }}
                    
                    <div class="mt-4 text-xs text-green-100">
                        Quick setup with area selection and equipment assignment
                    </div>
                </div>
            </div>
        </div>
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
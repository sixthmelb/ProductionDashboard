{{-- 
===========================
resources/views/filament/pages/manager-dashboard.blade.php
Dashboard view dengan responsive layout dan real-time updates
===========================
--}}

<x-filament-panels::page>
    {{-- Dashboard Header dengan Time Range Filter --}}
    <div class="mb-6">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    {{-- Quick Stats Summary --}}
                    <div class="flex flex-wrap gap-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                                {{ \App\Models\LoadingSession::active()->count() }}
                            </div>
                            <div class="text-sm text-gray-500">Active Sessions</div>
                        </div>
                        
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                {{ \App\Models\Equipment::active()->count() }}
                            </div>
                            <div class="text-sm text-gray-500">Total Equipment</div>
                        </div>
                        
                        <div class="text-center">
                            <div class="text-2xl font-bold text-orange-600 dark:text-orange-400">
                                {{ \App\Models\EquipmentBreakdown::whereIn('status', ['ongoing', 'pending_parts'])->count() }}
                            </div>
                            <div class="text-sm text-gray-500">Active Issues</div>
                        </div>
                        
                        <div class="text-center">
                            <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                                {{ number_format(\App\Models\BucketActivity::whereDate('activity_time', today())->sum('estimated_tonnage'), 1) }}t
                            </div>
                            <div class="text-sm text-gray-500">Today's Production</div>
                        </div>
                    </div>

                    {{-- Time Range Quick Filter --}}
                    <div class="flex gap-2">
                        <button type="button" 
                                class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600"
                                onclick="filterDashboard('today')"
                                id="filter-today">
                            Today
                        </button>
                        <button type="button" 
                                class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600"
                                onclick="filterDashboard('week')"
                                id="filter-week">
                            This Week
                        </button>
                        <button type="button" 
                                class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600"
                                onclick="filterDashboard('month')"
                                id="filter-month">
                            This Month
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Alert Notifications Area --}}
    @if(\App\Models\EquipmentBreakdown::where('severity', 'critical')->whereIn('status', ['ongoing', 'pending_parts'])->exists())
        <div class="mb-6">
            <div class="bg-red-50 border-l-4 border-red-400 p-4 rounded-md dark:bg-red-900/50 dark:border-red-600">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-red-400" />
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700 dark:text-red-200">
                            <strong>Critical Alert:</strong> 
                            {{ \App\Models\EquipmentBreakdown::where('severity', 'critical')->whereIn('status', ['ongoing', 'pending_parts'])->count() }} 
                            critical equipment breakdown(s) requiring immediate attention.
                            <a href="{{ route('filament.admin.resources.equipment-breakdowns.index') }}" 
                               class="underline hover:text-red-800 dark:hover:text-red-100">
                                View Details →
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Low Fuel Alert --}}
    @php
        // FIXED: Simplified query untuk avoid ambiguity
        $lowFuelCount = \App\Models\EquipmentStatusLog::join(
                \DB::raw('(SELECT equipment_id, MAX(status_time) as max_time FROM equipment_status_log GROUP BY equipment_id) latest'),
                function($join) {
                    $join->on('equipment_status_log.equipment_id', '=', 'latest.equipment_id')
                         ->on('equipment_status_log.status_time', '=', 'latest.max_time');
                }
            )
            ->join('equipment', 'equipment_status_log.equipment_id', '=', 'equipment.id')
            ->where('equipment.status', 'active')
            ->where('equipment_status_log.fuel_level', '<', 20)
            ->whereNotNull('equipment_status_log.fuel_level')
            ->count();
    @endphp
    
    @if($lowFuelCount > 0)
        <div class="mb-6">
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-md dark:bg-yellow-900/50 dark:border-yellow-600">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-yellow-400" />
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-700 dark:text-yellow-200">
                            <strong>Fuel Warning:</strong> 
                            {{ $lowFuelCount }} equipment with low fuel levels (&lt;20%). 
                            Consider refueling to avoid operational disruptions.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Main Dashboard Content --}}
    <div class="space-y-6">
        {{-- Widgets akan di-render di sini oleh Filament --}}
        <x-filament-widgets::widgets 
            :widgets="$this->getHeaderWidgets()" 
            :columns="$this->getWidgetColumns()" 
        />
    </div>

    {{-- Auto-refresh Script --}}
    @push('scripts')
    <script>
        // Dashboard filtering functionality
        function filterDashboard(timeRange) {
            // Update active button
            document.querySelectorAll('[id^="filter-"]').forEach(btn => {
                btn.classList.remove('bg-indigo-600', 'text-white');
                btn.classList.add('bg-white', 'text-gray-700');
            });
            
            document.getElementById('filter-' + timeRange).classList.remove('bg-white', 'text-gray-700');
            document.getElementById('filter-' + timeRange).classList.add('bg-indigo-600', 'text-white');
            
            // Emit event untuk widget updates
            window.dispatchEvent(new CustomEvent('dashboard-filter-changed', {
                detail: { timeRange: timeRange }
            }));
            
            // Update preferences
            fetch('/admin/dashboard/update-preferences', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    time_range: timeRange
                })
            });
        }

        // Auto-refresh functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Set initial active filter
            const currentFilter = '{{ session("dashboard_preferences.time_range", "today") }}';
            filterDashboard(currentFilter);
            
            // Auto-refresh if enabled
            const autoRefresh = {{ session('dashboard_preferences.auto_refresh', true) ? 'true' : 'false' }};
            
            if (autoRefresh) {
                setInterval(function() {
                    // Refresh widgets
                    window.Livewire.find('{{ $this->getId() }}').call('$refresh');
                    
                    // Update last refresh time in subtitle
                    const now = new Date();
                    const timeString = now.toLocaleTimeString('en-US', {
                        hour12: false,
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    
                    // Update subtitle if exists
                    const subtitle = document.querySelector('[data-slot="subheading"]');
                    if (subtitle) {
                        subtitle.textContent = subtitle.textContent.replace(
                            /Last Update: \d{2}:\d{2}/, 
                            'Last Update: ' + timeString
                        );
                    }
                }, 300000); // 5 minutes
            }
        });

        // Handle critical alerts dengan sound notification (optional)
        function checkCriticalAlerts() {
            const criticalCount = {{ \App\Models\EquipmentBreakdown::where('severity', 'critical')->whereIn('status', ['ongoing', 'pending_parts'])->count() }};
            
            if (criticalCount > 0) {
                // Show browser notification if permitted
                if ("Notification" in window && Notification.permission === "granted") {
                    new Notification("Critical Equipment Alert", {
                        body: `${criticalCount} critical breakdown(s) require immediate attention`,
                        icon: "/favicon.ico"
                    });
                }
            }
        }

        // Request notification permission
        if ("Notification" in window && Notification.permission === "default") {
            Notification.requestPermission();
        }
    </script>
    @endpush

    {{-- Custom Styles for Dashboard --}}
    @push('styles')
    <style>
        /* Custom animations untuk dashboard */
        .dashboard-fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Hover effects untuk cards */
        .dashboard-card {
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        
        .dashboard-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        /* Loading spinner untuk widgets */
        .widget-loading {
            position: relative;
            opacity: 0.6;
        }
        
        .widget-loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
    @endpush
</x-filament-panels::page>
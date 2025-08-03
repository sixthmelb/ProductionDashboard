{{-- 
===========================
resources/views/filament/widgets/equipment-status-widget.blade.php
Real-time equipment status grid dengan interactive controls
===========================
--}}

<x-filament-widgets::widget>
    <x-filament::section>
        {{-- Widget Header dengan Summary Stats --}}
        <x-slot name="heading">
            Equipment Status Overview
        </x-slot>

        <x-slot name="description">
            Real-time equipment monitoring and status management
        </x-slot>

        <x-slot name="headerEnd">
            <div class="flex items-center space-x-4 text-sm">
                {{-- Equipment Type Distribution --}}
                <div class="flex items-center space-x-2">
                    <span class="text-gray-500">🚛 {{ $this->getEquipmentSummary()['type_distribution']['dumptruck'] }}</span>
                    <span class="text-gray-500">🏗️ {{ $this->getEquipmentSummary()['type_distribution']['excavator'] }}</span>
                </div>
                
                {{-- Utilization Rate --}}
                <div class="flex items-center space-x-1">
                    <span class="text-gray-500">Utilization:</span>
                    <span class="font-semibold 
                        @if($this->getEquipmentSummary()['utilization_rate'] >= 80) text-green-600
                        @elseif($this->getEquipmentSummary()['utilization_rate'] >= 60) text-yellow-600
                        @else text-red-600
                        @endif">
                        {{ $this->getEquipmentSummary()['utilization_rate'] }}%
                    </span>
                </div>

                {{-- Critical Alerts --}}
                @if($this->getEquipmentSummary()['alerts']['total_alerts'] > 0)
                <div class="flex items-center space-x-1">
                    <x-heroicon-o-exclamation-triangle class="w-4 h-4 text-red-500" />
                    <span class="text-red-600 font-semibold">
                        {{ $this->getEquipmentSummary()['alerts']['total_alerts'] }} Alert(s)
                    </span>
                </div>
                @endif
            </div>
        </x-slot>

        {{-- Status Distribution Summary Bar --}}
        <div class="mb-6">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Status Distribution</h3>
                <span class="text-xs text-gray-500">
                    Total: {{ $this->getEquipmentSummary()['total_equipment'] }} Equipment
                </span>
            </div>
            
            @php
                $summary = $this->getEquipmentSummary();
                $total = $summary['total_equipment'];
                $working = $summary['status_distribution']['working'];
                $idle = $summary['status_distribution']['idle'];
                $breakdown = $summary['status_distribution']['breakdown'];
                $maintenance = $summary['status_distribution']['maintenance'];
            @endphp

            <div class="flex h-3 bg-gray-200 rounded-full overflow-hidden dark:bg-gray-700">
                @if($working > 0)
                <div class="bg-green-500 transition-all duration-300" 
                     style="width: {{ $total > 0 ? ($working / $total) * 100 : 0 }}%"
                     title="Working: {{ $working }}"></div>
                @endif
                
                @if($idle > 0)
                <div class="bg-yellow-500 transition-all duration-300" 
                     style="width: {{ $total > 0 ? ($idle / $total) * 100 : 0 }}%"
                     title="Idle: {{ $idle }}"></div>
                @endif
                
                @if($breakdown > 0)
                <div class="bg-red-500 transition-all duration-300" 
                     style="width: {{ $total > 0 ? ($breakdown / $total) * 100 : 0 }}%"
                     title="Breakdown: {{ $breakdown }}"></div>
                @endif
                
                @if($maintenance > 0)
                <div class="bg-blue-500 transition-all duration-300" 
                     style="width: {{ $total > 0 ? ($maintenance / $total) * 100 : 0 }}%"
                     title="Maintenance: {{ $maintenance }}"></div>
                @endif
            </div>

            {{-- Status Legend --}}
            <div class="flex flex-wrap gap-4 mt-2 text-xs">
                <div class="flex items-center space-x-1">
                    <div class="w-3 h-3 bg-green-500 rounded"></div>
                    <span>Working ({{ $working }})</span>
                </div>
                <div class="flex items-center space-x-1">
                    <div class="w-3 h-3 bg-yellow-500 rounded"></div>
                    <span>Idle ({{ $idle }})</span>
                </div>
                <div class="flex items-center space-x-1">
                    <div class="w-3 h-3 bg-red-500 rounded"></div>
                    <span>Breakdown ({{ $breakdown }})</span>
                </div>
                <div class="flex items-center space-x-1">
                    <div class="w-3 h-3 bg-blue-500 rounded"></div>
                    <span>Maintenance ({{ $maintenance }})</span>
                </div>
            </div>
        </div>

        {{-- Equipment Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            @forelse($this->getEquipmentData() as $equipment)
                <div class="relative bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 hover:shadow-md transition-all duration-200
                    @if($equipment['is_critical']) ring-2 ring-red-500 ring-opacity-50 @endif">
                    
                    {{-- Equipment Header --}}
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <h4 class="font-semibold text-gray-900 dark:text-gray-100">
                                {{ $equipment['code'] }}
                            </h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $equipment['type_name'] }}
                            </p>
                        </div>
                        
                        {{-- Status Badge --}}
                        <div class="flex flex-col items-end space-y-1">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                @if($equipment['status_color'] === 'success') bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100
                                @elseif($equipment['status_color'] === 'warning') bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100
                                @elseif($equipment['status_color'] === 'danger') bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100
                                @elseif($equipment['status_color'] === 'info') bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100
                                @else bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100
                                @endif">
                                {{ $equipment['status_text'] }}
                            </span>
                            
                            {{-- Critical Alert Badge --}}
                            @if($equipment['is_critical'])
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100">
                                    <x-heroicon-s-exclamation-triangle class="w-3 h-3 mr-1" />
                                    Critical
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Equipment Details --}}
                    <div class="space-y-2 text-sm">
                        {{-- Brand & Model --}}
                        <div class="flex justify-between">
                            <span class="text-gray-500">Model:</span>
                            <span class="text-gray-900 dark:text-gray-100">{{ $equipment['brand_model'] }}</span>
                        </div>
                        
                        {{-- Capacity --}}
                        <div class="flex justify-between">
                            <span class="text-gray-500">Capacity:</span>
                            <span class="text-gray-900 dark:text-gray-100">{{ $equipment['capacity'] }}</span>
                        </div>
                        
                        {{-- Operator --}}
                        <div class="flex justify-between">
                            <span class="text-gray-500">Operator:</span>
                            <span class="text-gray-900 dark:text-gray-100">{{ $equipment['operator_name'] }}</span>
                        </div>
                        
                        {{-- Location --}}
                        <div class="flex justify-between">
                            <span class="text-gray-500">Location:</span>
                            <span class="text-gray-900 dark:text-gray-100 truncate ml-2">{{ $equipment['location'] }}</span>
                        </div>

                        {{-- Fuel Level --}}
                        @if($equipment['fuel_level'])
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500">Fuel:</span>
                                <div class="flex items-center space-x-2">
                                    <div class="flex-1 bg-gray-200 rounded-full h-2 w-16 dark:bg-gray-700">
                                        <div class="h-2 rounded-full transition-all duration-300
                                            @if($equipment['fuel_color'] === 'success') bg-green-500
                                            @elseif($equipment['fuel_color'] === 'warning') bg-yellow-500
                                            @elseif($equipment['fuel_color'] === 'danger') bg-red-500
                                            @else bg-gray-400
                                            @endif"
                                            style="width: {{ $equipment['fuel_level'] }}%"></div>
                                    </div>
                                    <span class="text-xs font-medium 
                                        @if($equipment['fuel_color'] === 'danger') text-red-600
                                        @elseif($equipment['fuel_color'] === 'warning') text-yellow-600
                                        @else text-gray-600
                                        @endif">
                                        {{ $equipment['fuel_level'] }}%
                                    </span>
                                </div>
                            </div>
                        @endif

                        {{-- Last Update --}}
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-400">Updated:</span>
                            <span class="text-gray-400">{{ $equipment['last_update'] }}</span>
                        </div>
                    </div>

                    {{-- Breakdown Information --}}
                    @if($equipment['breakdown_info'])
                        <div class="mt-3 p-2 bg-red-50 dark:bg-red-900/20 rounded border border-red-200 dark:border-red-800">
                            <div class="flex items-center space-x-2 mb-1">
                                <x-heroicon-s-exclamation-triangle class="w-4 h-4 text-red-500" />
                                <span class="text-xs font-medium text-red-700 dark:text-red-300">
                                    {{ ucfirst($equipment['breakdown_info']['type']) }} - {{ ucfirst($equipment['breakdown_info']['severity']) }}
                                </span>
                            </div>
                            <p class="text-xs text-red-600 dark:text-red-400 truncate">
                                {{ $equipment['breakdown_info']['description'] }}
                            </p>
                            <p class="text-xs text-red-500 dark:text-red-400 mt-1">
                                Duration: {{ $equipment['breakdown_info']['duration'] }}
                            </p>
                        </div>
                    @endif

                    {{-- Action Buttons --}}
                    <div class="mt-4 flex space-x-2">
                        {{-- Quick Status Update --}}
                        @if($canUpdateStatus && $equipment['current_status'] !== 'breakdown')
                            <button wire:click="updateEquipmentStatus({{ $equipment['id'] }}, '{{ $equipment['current_status'] === 'working' ? 'idle' : 'working' }}')"
                                    class="flex-1 text-xs px-2 py-1 rounded border transition-colors
                                        @if($equipment['current_status'] === 'working')
                                            border-yellow-300 text-yellow-700 hover:bg-yellow-50 dark:border-yellow-600 dark:text-yellow-400 dark:hover:bg-yellow-900/20
                                        @else
                                            border-green-300 text-green-700 hover:bg-green-50 dark:border-green-600 dark:text-green-400 dark:hover:bg-green-900/20
                                        @endif">
                                {{ $equipment['current_status'] === 'working' ? 'Set Idle' : 'Set Working' }}
                            </button>
                        @endif

                        {{-- Report Breakdown --}}
                        @if($canReportBreakdown && $equipment['can_work'])
                            <button wire:click="reportBreakdown({{ $equipment['id'] }})"
                                    class="flex-1 text-xs px-2 py-1 rounded border border-red-300 text-red-700 hover:bg-red-50 transition-colors dark:border-red-600 dark:text-red-400 dark:hover:bg-red-900/20">
                                Report Issue
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                {{-- Empty State --}}
                <div class="col-span-full flex flex-col items-center justify-center py-12 text-gray-500 dark:text-gray-400">
                    <x-heroicon-o-truck class="w-12 h-12 mb-4" />
                    <h3 class="text-lg font-medium mb-2">No Equipment Found</h3>
                    <p class="text-sm">Add equipment to start monitoring their status.</p>
                </div>
            @endforelse
        </div>

        {{-- Auto-refresh Indicator --}}
        @if($refreshInterval)
            <div class="mt-4 flex items-center justify-center text-xs text-gray-400">
                <x-heroicon-o-arrow-path class="w-3 h-3 mr-1 animate-spin" wire:loading />
                <span wire:loading.remove>Auto-refresh: {{ $refreshInterval }}</span>
                <span wire:loading>Updating...</span>
            </div>
        @endif
    </x-filament::section>
<?php
// ===========================
// app/Filament/Widgets/EquipmentStatusWidget.php  
// File yang dihasilkan dari: php artisan make:filament-widget EquipmentStatusWidget
// Kemudian dimodifikasi untuk real-time equipment status grid dengan detailed info
// ===========================

namespace App\Filament\Widgets;

use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms;
use Illuminate\Support\Facades\Cache;
use App\Models\Equipment;
use App\Models\EquipmentStatusLog;

class EquipmentStatusWidget extends BaseWidget
{
    // ===========================
    // WIDGET CONFIGURATION - Menggunakan TableWidget instead of custom view
    // ===========================
    
    /**
     * PERFORMANCE: Polling interval untuk real-time status updates
     */
    protected static ?string $pollingInterval = '60s';

    /**
     * LAYOUT: Widget column span
     */
    protected int | string | array $columnSpan = 'full';

    /**
     * FEATURE: Sort order dalam dashboard
     */
    protected static ?int $sort = 2;

    /**
     * HEADING: Widget title
     */
    protected static ?string $heading = 'Equipment Status Overview';

    // ===========================
    // TABLE CONFIGURATION - Using Filament Table instead of custom view
    // ===========================

    public function table(Table $table): Table
    {
        return $table
            ->query(Equipment::query()->active()->with(['activeBreakdowns']))
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Equipment')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('type_name')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Dump Truck' => 'warning',
                        'Excavator' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('current_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'working' => 'success',
                        'idle' => 'warning',
                        'breakdown' => 'danger',
                        'maintenance' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'working' => 'Working',
                        'idle' => 'Idle',
                        'breakdown' => 'Breakdown',
                        'maintenance' => 'Maintenance',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('brand_model')
                    ->label('Brand/Model')
                    ->getStateUsing(function ($record): string {
                        return trim("{$record->brand} {$record->model}") ?: '-';
                    }),

                Tables\Columns\TextColumn::make('capacity')
                    ->label('Capacity')
                    ->getStateUsing(function ($record): string {
                        $unit = $record->type === 'dumptruck' ? 'ton' : 'm³';
                        return $record->capacity ? $record->capacity . ' ' . $unit : '-';
                    }),

                Tables\Columns\TextColumn::make('current_operator')
                    ->label('Operator')
                    ->getStateUsing(function ($record): string {
                        $currentLog = EquipmentStatusLog::where('equipment_id', $record->id)
                            ->orderByDesc('status_time')
                            ->first();
                        return $currentLog?->operator_name ?? '-';
                    })
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('fuel_level')
                    ->label('Fuel')
                    ->getStateUsing(function ($record): ?string {
                        $currentLog = EquipmentStatusLog::where('equipment_id', $record->id)
                            ->orderByDesc('status_time')
                            ->first();
                        return $currentLog?->fuel_level ? $currentLog->fuel_level . '%' : null;
                    })
                    ->badge()
                    ->color(function ($record): string {
                        $currentLog = EquipmentStatusLog::where('equipment_id', $record->id)
                            ->orderByDesc('status_time')
                            ->first();
                        $fuelLevel = $currentLog?->fuel_level;
                        if (!$fuelLevel) return 'gray';
                        return match(true) {
                            $fuelLevel >= 70 => 'success',
                            $fuelLevel >= 30 => 'warning',
                            default => 'danger'
                        };
                    })
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('breakdown_info')
                    ->label('Issue')
                    ->getStateUsing(function ($record): string {
                        $breakdown = $record->activeBreakdowns->first();
                        return $breakdown ? 
                            ucfirst($breakdown->breakdown_type) . ' (' . ucfirst($breakdown->severity) . ')' : 
                            '-';
                    })
                    ->color(function ($record): string {
                        $breakdown = $record->activeBreakdowns->first();
                        if (!$breakdown) return 'gray';
                        return match($breakdown->severity) {
                            'critical' => 'danger',
                            'high' => 'danger', 
                            'medium' => 'warning',
                            'low' => 'success',
                            default => 'gray'
                        };
                    })
                    ->placeholder('-'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'dumptruck' => 'Dump Truck',
                        'excavator' => 'Excavator',
                    ]),
                Tables\Filters\SelectFilter::make('current_status')
                    ->options([
                        'working' => 'Working',
                        'idle' => 'Idle',
                        'breakdown' => 'Breakdown',
                        'maintenance' => 'Maintenance',
                    ]),
            ])
            // TEMPORARY: Remove actions untuk testing
            // ->actions([
            //     Tables\Actions\Action::make('updateStatus')
            //         ->label('Update Status')
            //         ->icon('heroicon-o-arrow-path')
            //         ->color('info')
            //         ->form([
            //             \Filament\Forms\Components\Select::make('status')
            //                 ->label('New Status')
            //                 ->options([
            //                     'idle' => 'Idle',
            //                     'working' => 'Working',
            //                     'maintenance' => 'Maintenance',
            //                 ])
            //                 ->required(),
            //         ])
            //         ->action(function (Equipment $record, array $data): void {
            //             $record->statusLogs()->create([
            //                 'status' => $data['status'],
            //                 'status_time' => now(),
            //                 'notes' => "Status updated via dashboard by " . auth()->user()->name,
            //             ]);
            //         }),
            // ])
            ->poll('60s')
            ->striped()
            ->paginated([10, 25, 50]);
    }

    /**
     * MAIN METHOD: Get equipment data dengan real-time status
     * FIXED: Alternative approach untuk avoid SQL ambiguity
     */
    public function getEquipmentData(): array
    {
        $cacheKey = 'dashboard_equipment_status';
        $cacheTTL = config('mining.performance.cache_ttl.equipment_status', 300);

        return Cache::remember($cacheKey, $cacheTTL, function () {
            return Equipment::active()
                ->with([
                    'activeBreakdowns:id,equipment_id,breakdown_type,severity,description,status,start_time'
                ])
                ->orderBy('type')
                ->orderBy('code')
                ->get()
                ->map(function ($equipment) {
                    return $this->formatEquipmentData($equipment);
                })
                ->toArray();
        });
    }

    /**
     * DATA FORMATTING: Format equipment data untuk display
     * FIXED: Get current status log dengan alternative query
     */
    private function formatEquipmentData(Equipment $equipment): array
    {
        // ALTERNATIVE: Get current status log dengan manual query untuk avoid ambiguity
        $currentLog = EquipmentStatusLog::where('equipment_id', $equipment->id)
            ->orderByDesc('status_time')
            ->orderByDesc('id')
            ->first();
            
        $activeBreakdown = $equipment->activeBreakdowns->first();

        return [
            'id' => $equipment->id,
            'code' => $equipment->code,
            'type' => $equipment->type,
            'type_name' => $equipment->type_name,
            'brand_model' => trim("{$equipment->brand} {$equipment->model}"),
            'capacity' => $equipment->capacity . ' ' . $equipment->capacity_unit,
            
            // Status Information
            'current_status' => $equipment->current_status,
            'status_color' => $this->getStatusColor($equipment->current_status),
            'status_icon' => $this->getStatusIcon($equipment->current_status),
            'status_text' => $this->getStatusText($equipment->current_status),
            
            // Operational Details dari current status log
            'operator_name' => $currentLog?->operator_name ?? '-',
            'location' => $currentLog?->location ?? '-',
            'fuel_level' => $currentLog?->fuel_level,
            'fuel_color' => $this->getFuelColor($currentLog?->fuel_level),
            'last_update' => $currentLog?->status_time?->diffForHumans() ?? 'Unknown',
            
            // Breakdown Information jika ada
            'breakdown_info' => $activeBreakdown ? [
                'type' => $activeBreakdown->breakdown_type,
                'severity' => $activeBreakdown->severity,
                'description' => $activeBreakdown->description,
                'duration' => $activeBreakdown->start_time->diffForHumans(),
                'severity_color' => $this->getSeverityColor($activeBreakdown->severity),
            ] : null,
            
            // Operational Capability
            'can_work' => $equipment->canWork(),
            'is_critical' => $this->isCriticalStatus($equipment, $currentLog, $activeBreakdown),
        ];
    }

    // ===========================
    // STATUS HELPER METHODS - Utility functions untuk status management
    // ===========================

    /**
     * UTILITY: Get status color untuk display
     */
    private function getStatusColor(string $status): string
    {
        return match($status) {
            'working' => 'success',
            'idle' => 'warning', 
            'breakdown' => 'danger',
            'maintenance' => 'info',
            default => 'gray'
        };
    }

    /**
     * UTILITY: Get status icon
     */
    private function getStatusIcon(string $status): string
    {
        return match($status) {
            'working' => 'heroicon-o-play-circle',
            'idle' => 'heroicon-o-pause-circle',
            'breakdown' => 'heroicon-o-exclamation-triangle',
            'maintenance' => 'heroicon-o-wrench-screwdriver',
            default => 'heroicon-o-question-mark-circle'
        };
    }

    /**
     * UTILITY: Get human-readable status text
     */
    private function getStatusText(string $status): string
    {
        return match($status) {
            'working' => 'Working',
            'idle' => 'Idle',
            'breakdown' => 'Breakdown',
            'maintenance' => 'Maintenance',
            default => 'Unknown'
        };
    }

    /**
     * UTILITY: Get fuel level color untuk warning system
     */
    private function getFuelColor(?float $fuelLevel): string
    {
        if (!$fuelLevel) return 'gray';
        
        return match(true) {
            $fuelLevel >= 70 => 'success',
            $fuelLevel >= 30 => 'warning',
            $fuelLevel >= 20 => 'warning',
            default => 'danger'
        };
    }

    /**
     * UTILITY: Get breakdown severity color
     */
    private function getSeverityColor(string $severity): string
    {
        return match($severity) {
            'low' => 'success',
            'medium' => 'warning',
            'high' => 'danger',
            'critical' => 'danger',
            default => 'gray'
        };
    }

    /**
     * BUSINESS LOGIC: Check if equipment is in critical status
     */
    private function isCriticalStatus(Equipment $equipment, ?EquipmentStatusLog $currentLog, $activeBreakdown): bool
    {
        // Critical conditions
        $criticalConditions = [
            // Critical breakdown
            $activeBreakdown && $activeBreakdown->severity === 'critical',
            
            // Very low fuel
            $currentLog && $currentLog->fuel_level && $currentLog->fuel_level < 10,
            
            // Equipment breakdown yang lama (> 4 hours)
            $activeBreakdown && $activeBreakdown->start_time->diffInHours() > 4,
        ];

        return collect($criticalConditions)->contains(true);
    }

    // ===========================
    // SUMMARY STATISTICS - Equipment overview data
    // ===========================

    /**
     * DATA: Get equipment summary statistics
     */
    public function getEquipmentSummary(): array
    {
        $cacheKey = 'dashboard_equipment_summary';
        $cacheTTL = config('mining.performance.cache_ttl.equipment_status', 300);

        return Cache::remember($cacheKey, $cacheTTL, function () {
            $equipment = Equipment::active()->get();
            
            $statusDistribution = $equipment->groupBy('current_status')->map->count();
            $typeDistribution = $equipment->groupBy('type')->map->count();
            
            // Critical alerts count
            $criticalBreakdowns = $equipment->filter(function ($eq) {
                $activeBreakdown = $eq->activeBreakdowns->first();
                return $activeBreakdown && $activeBreakdown->severity === 'critical';
            })->count();

            // Low fuel count
            $lowFuelCount = $equipment->filter(function ($eq) {
                $currentLog = $eq->currentStatusLog;
                return $currentLog && $currentLog->fuel_level && $currentLog->fuel_level < 20;
            })->count();

            return [
                'total_equipment' => $equipment->count(),
                'status_distribution' => [
                    'working' => $statusDistribution->get('working', 0),
                    'idle' => $statusDistribution->get('idle', 0),
                    'breakdown' => $statusDistribution->get('breakdown', 0),
                    'maintenance' => $statusDistribution->get('maintenance', 0),
                ],
                'type_distribution' => [
                    'dumptruck' => $typeDistribution->get('dumptruck', 0),
                    'excavator' => $typeDistribution->get('excavator', 0),
                ],
                'alerts' => [
                    'critical_breakdowns' => $criticalBreakdowns,
                    'low_fuel' => $lowFuelCount,
                    'total_alerts' => $criticalBreakdowns + $lowFuelCount,
                ],
                'utilization_rate' => $this->calculateUtilizationRate($statusDistribution),
            ];
        });
    }

    /**
     * CALCULATION: Calculate equipment utilization rate
     */
    private function calculateUtilizationRate($statusDistribution): float
    {
        $totalEquipment = $statusDistribution->sum();
        $workingEquipment = $statusDistribution->get('working', 0);
        $availableEquipment = $totalEquipment - $statusDistribution->get('breakdown', 0) - $statusDistribution->get('maintenance', 0);
        
        if ($availableEquipment <= 0) return 0;
        
        return round(($workingEquipment / $availableEquipment) * 100, 1);
    }

    // ===========================
    // LIVEWIRE ACTIONS - Interactive functionality
    // ===========================

    /**
     * ACTION: Quick status update untuk equipment
     */
    public function updateEquipmentStatus(int $equipmentId, string $newStatus): void
    {
        try {
            $equipment = Equipment::findOrFail($equipmentId);
            
            // Validation: Check if status change is allowed
            if ($newStatus === 'working' && !$equipment->canWork()) {
                $this->dispatch('equipment-status-error', 
                    message: "Cannot set {$equipment->code} to working due to active breakdown"
                );
                return;
            }

            // Create status log entry
            $equipment->statusLogs()->create([
                'status' => $newStatus,
                'status_time' => now(),
                'notes' => "Status updated via dashboard by " . auth()->user()->name,
            ]);

            // Clear cache untuk refresh data
            $this->clearWidgetCache();

            // Emit success event
            $this->dispatch('equipment-status-updated', 
                equipmentCode: $equipment->code,
                newStatus: $newStatus
            );

        } catch (\Exception $e) {
            $this->dispatch('equipment-status-error', 
                message: "Failed to update equipment status: " . $e->getMessage()
            );
        }
    }

    /**
     * ACTION: Report breakdown untuk equipment
     */
    public function reportBreakdown(int $equipmentId): void
    {
        // Redirect ke breakdown form dengan equipment pre-selected
        $this->dispatch('open-breakdown-form', equipmentId: $equipmentId);
    }

    // ===========================
    // CACHE MANAGEMENT - Performance optimization
    // ===========================

    /**
     * PERFORMANCE: Clear widget-specific cache
     */
    private function clearWidgetCache(): void
    {
        Cache::forget('dashboard_equipment_status');
        Cache::forget('dashboard_equipment_summary');
        
        // Clear individual equipment cache yang mungkin berubah
        Equipment::all()->each(function ($equipment) {
            Cache::forget("equipment_status_{$equipment->id}");
            Cache::forget("equipment_can_work_{$equipment->id}");
        });
    }

    /**
     * EVENT LISTENER: Handle various dashboard events
     */
    protected $listeners = [
        'dashboard-refreshed' => 'refreshWidget',
        'equipment-status-changed' => 'refreshWidget',
        'breakdown-reported' => 'refreshWidget',
    ];

    /**
     * EVENT HANDLER: Refresh widget data
     */
    public function refreshWidget(): void
    {
        $this->clearWidgetCache();
        $this->dispatch('$refresh');
    }

    // ===========================
    // VIEW DATA PREPARATION - Data untuk blade template
    // ===========================

    /**
     * VIEW DATA: Prepare all data untuk widget view
     */
    protected function getViewData(): array
    {
        return [
            'equipment' => $this->getEquipmentData(),
            'summary' => $this->getEquipmentSummary(),
            'refreshInterval' => static::$pollingInterval,
            'canUpdateStatus' => auth()->user()->can('update_equipment'),
            'canReportBreakdown' => auth()->user()->can('create_equipment::breakdown'),
        ];
    }
}
<?php
// ===========================
// app/Filament/Widgets/PerformanceChartsWidget.php
// File yang dihasilkan dari: php artisan make:filament-widget PerformanceChartsWidget --chart
// Kemudian dimodifikasi untuk production performance analytics dengan multiple chart types
// ===========================

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;
use App\Models\BucketActivity;
use App\Models\LoadingSession;
use App\Models\EquipmentBreakdown;
use App\Models\Equipment;
use Carbon\Carbon;

class PerformanceChartsWidget extends ChartWidget
{
    // ===========================
    // WIDGET CONFIGURATION - Dari template, ditambah customization
    // ===========================
    
    /**
     * LAYOUT: Chart heading dengan dynamic title
     */
    protected static ?string $heading = 'Production Performance Analytics';

    /**
     * CHART: Default chart type, bisa diubah dengan user preference
     */
    protected static string $type = 'line';

    /**
     * REQUIRED: Implement abstract getType() method dari ChartWidget
     * Returns dynamic chart type berdasarkan current selection
     */
    protected function getType(): string
    {
        return match($this->currentChartType) {
            'production_trend', 'session_performance' => 'line',
            'equipment_utilization', 'breakdown_analysis', 'hourly_production' => 'bar',
            default => 'line'
        };
    }

    /**
     * LAYOUT: Widget column span
     */
    protected int | string | array $columnSpan = 'full';

    /**
     * FEATURE: Sort order dalam dashboard
     */
    protected static ?int $sort = 3;

    /**
     * PERFORMANCE: Polling interval untuk real-time updates
     */
    protected static ?string $pollingInterval = null;

    /**
     * STATE: Current chart type untuk dynamic switching
     */
    public string $currentChartType = 'production_trend';

    // ===========================
    // INITIALIZATION - Setup widget
    // ===========================

    /**
     * SETUP: Mount method untuk initialize widget settings
     */
    public function mount(): void
    {
        // Get polling interval dari config
        $refreshInterval = config('mining.performance.polling_intervals.dashboard_widgets', 300);
        static::$pollingInterval = $refreshInterval . 's';

        // Get chart type preference dari session
        $this->currentChartType = session('dashboard_chart_type', 'production_trend');
    }

    // ===========================
    // CHART DATA GENERATION - Main chart data methods
    // ===========================

    /**
     * MAIN METHOD: Get chart data berdasarkan current chart type
     * Menggunakan caching untuk performance optimization
     */
    protected function getData(): array
    {
        $timeRange = session('dashboard_preferences.time_range', 'today');
        $cacheKey = "dashboard_performance_chart_{$this->currentChartType}_{$timeRange}";
        $cacheTTL = config('mining.performance.cache_ttl.dashboard_performance_data', 300);

        return Cache::remember($cacheKey, $cacheTTL, function () use ($timeRange) {
            return match($this->currentChartType) {
                'production_trend' => $this->getProductionTrendData($timeRange),
                'equipment_utilization' => $this->getEquipmentUtilizationData($timeRange),
                'breakdown_analysis' => $this->getBreakdownAnalysisData($timeRange),
                'session_performance' => $this->getSessionPerformanceData($timeRange),
                'hourly_production' => $this->getHourlyProductionData(),
                default => $this->getProductionTrendData($timeRange)
            };
        });
    }

    /**
     * CHART TYPE: Production trend over time
     * Shows tonnage dan bucket trends untuk selected time range
     */
    private function getProductionTrendData(string $timeRange): array
    {
        $dateRange = $this->getDateRange($timeRange);
        $periods = $this->generateDatePeriods($timeRange);

        $tonnageData = [];
        $bucketData = [];
        $labels = [];

        foreach ($periods as $period) {
            $startDate = $period['start'];
            $endDate = $period['end'];
            
            // Get tonnage untuk period
            $tonnage = BucketActivity::whereBetween('activity_time', [$startDate, $endDate])
                ->sum('estimated_tonnage');
                
            // Get bucket count untuk period
            $buckets = BucketActivity::whereBetween('activity_time', [$startDate, $endDate])
                ->sum('bucket_count');

            $tonnageData[] = round($tonnage, 1);
            $bucketData[] = $buckets;
            $labels[] = $period['label'];
        }

        // Update widget properties untuk production trend
        static::$heading = 'Production Trend Analysis';
        static::$type = 'line';

        return [
            'datasets' => [
                [
                    'label' => 'Tonnage',
                    'data' => $tonnageData,
                    'borderColor' => '#10B981', // Green
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'tension' => 0.4,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Buckets',
                    'data' => $bucketData,
                    'borderColor' => '#3B82F6', // Blue
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.4,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * CHART TYPE: Equipment utilization comparison
     * Shows utilization rate per equipment type
     */
    private function getEquipmentUtilizationData(string $timeRange): array
    {
        $dateRange = $this->getDateRange($timeRange);
        
        // Get equipment dengan working hours calculation
        $equipmentData = Equipment::active()
            ->with(['statusLogs' => function ($query) use ($dateRange) {
                $query->whereBetween('status_time', $dateRange);
            }])
            ->get()
            ->map(function ($equipment) use ($dateRange) {
                // Calculate working hours untuk period
                $workingMinutes = $equipment->statusLogs
                    ->where('status', 'working')
                    ->sum(function ($log) {
                        // Estimate duration between logs (simplified)
                        return 60; // Assume 1 hour per working log
                    });
                
                $totalMinutes = Carbon::parse($dateRange[0])->diffInMinutes(Carbon::parse($dateRange[1]));
                $utilizationRate = $totalMinutes > 0 ? ($workingMinutes / $totalMinutes) * 100 : 0;

                return [
                    'code' => $equipment->code,
                    'type' => $equipment->type,
                    'utilization' => round($utilizationRate, 1),
                ];
            });

        // Separate by equipment type
        $dumptrucks = $equipmentData->where('type', 'dumptruck');
        $excavators = $equipmentData->where('type', 'excavator');

        // Update widget properties
        static::$heading = 'Equipment Utilization Comparison';
        static::$type = 'bar';

        return [
            'datasets' => [
                [
                    'label' => 'Dump Trucks',
                    'data' => $dumptrucks->pluck('utilization')->values()->toArray(),
                    'backgroundColor' => '#F59E0B', // Yellow
                    'borderColor' => '#D97706',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'Excavators',
                    'data' => $excavators->pluck('utilization')->values()->toArray(),
                    'backgroundColor' => '#8B5CF6', // Purple
                    'borderColor' => '#7C3AED',
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $equipmentData->pluck('code')->toArray(),
        ];
    }

    /**
     * CHART TYPE: Breakdown analysis by type dan severity
     */
    private function getBreakdownAnalysisData(string $timeRange): array
    {
        $dateRange = $this->getDateRange($timeRange);
        
        // Get breakdown data by type
        $breakdownsByType = EquipmentBreakdown::whereBetween('start_time', $dateRange)
            ->selectRaw('breakdown_type, COUNT(*) as count, AVG(duration_minutes) as avg_duration')
            ->whereNotNull('duration_minutes')
            ->groupBy('breakdown_type')
            ->get();

        $types = ['mechanical', 'electrical', 'hydraulic', 'engine', 'tire', 'other'];
        $counts = [];
        $avgDurations = [];
        
        foreach ($types as $type) {
            $data = $breakdownsByType->firstWhere('breakdown_type', $type);
            $counts[] = $data ? $data->count : 0;
            $avgDurations[] = $data ? round($data->avg_duration / 60, 1) : 0; // Convert to hours
        }

        // Update widget properties
        static::$heading = 'Breakdown Analysis by Type';
        static::$type = 'bar';

        return [
            'datasets' => [
                [
                    'label' => 'Breakdown Count',
                    'data' => $counts,
                    'backgroundColor' => [
                        '#EF4444', // Red
                        '#F59E0B', // Yellow
                        '#3B82F6', // Blue
                        '#8B5CF6', // Purple
                        '#10B981', // Green
                        '#6B7280', // Gray
                    ],
                    'borderWidth' => 1,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Avg Duration (Hours)',
                    'data' => $avgDurations,
                    'type' => 'line',
                    'borderColor' => '#DC2626',
                    'backgroundColor' => 'rgba(220, 38, 38, 0.1)',
                    'tension' => 0.4,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => array_map('ucfirst', $types),
        ];
    }

    /**
     * CHART TYPE: Session performance metrics
     */
    private function getSessionPerformanceData(string $timeRange): array
    {
        $dateRange = $this->getDateRange($timeRange);
        $periods = $this->generateDatePeriods($timeRange);

        $sessionCounts = [];
        $completionRates = [];
        $avgDurations = [];
        $labels = [];

        foreach ($periods as $period) {
            $sessions = LoadingSession::whereBetween('start_time', [$period['start'], $period['end']]);
            
            $totalSessions = $sessions->count();
            $completedSessions = $sessions->where('status', 'completed')->count();
            $completionRate = $totalSessions > 0 ? ($completedSessions / $totalSessions) * 100 : 0;
            
            // Average duration for completed sessions
            $avgDuration = LoadingSession::whereBetween('start_time', [$period['start'], $period['end']])
                ->where('status', 'completed')
                ->whereNotNull('end_time')
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, start_time, end_time)) as avg_minutes')
                ->value('avg_minutes');

            $sessionCounts[] = $totalSessions;
            $completionRates[] = round($completionRate, 1);
            $avgDurations[] = $avgDuration ? round($avgDuration / 60, 1) : 0;
            $labels[] = $period['label'];
        }

        // Update widget properties
        static::$heading = 'Session Performance Metrics';
        static::$type = 'line';

        return [
            'datasets' => [
                [
                    'label' => 'Session Count',
                    'data' => $sessionCounts,
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.4,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Completion Rate (%)',
                    'data' => $completionRates,
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'tension' => 0.4,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * CHART TYPE: Hourly production pattern (untuk today only)
     */
    private function getHourlyProductionData(): array
    {
        $hours = [];
        $tonnageData = [];
        
        // Generate hourly data for today
        for ($hour = 0; $hour < 24; $hour++) {
            $startTime = Carbon::today()->setHour($hour);
            $endTime = Carbon::today()->setHour($hour + 1);
            
            $tonnage = BucketActivity::whereBetween('activity_time', [$startTime, $endTime])
                ->sum('estimated_tonnage');
                
            $hours[] = $startTime->format('H:i');
            $tonnageData[] = round($tonnage, 1);
        }

        // Update widget properties
        static::$heading = 'Today\'s Hourly Production Pattern';
        static::$type = 'bar';

        return [
            'datasets' => [
                [
                    'label' => 'Tonnage per Hour',
                    'data' => $tonnageData,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.7)',
                    'borderColor' => '#10B981',
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $hours,
        ];
    }

    // ===========================
    // CHART OPTIONS - Configuration untuk different chart types
    // ===========================

    /**
     * CONFIGURATION: Get chart options berdasarkan chart type
     */
    protected function getOptions(): array
    {
        $baseOptions = [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'position' => 'top',
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
            'interaction' => [
                'mode' => 'nearest',
                'axis' => 'x',
                'intersect' => false,
            ],
        ];

        // Chart-specific options
        return match($this->currentChartType) {
            'production_trend', 'session_performance' => array_merge($baseOptions, [
                'scales' => [
                    'y' => [
                        'type' => 'linear',
                        'display' => true,
                        'position' => 'left',
                        'title' => [
                            'display' => true,
                            'text' => $this->currentChartType === 'production_trend' ? 'Tonnage' : 'Count'
                        ],
                    ],
                    'y1' => [
                        'type' => 'linear',
                        'display' => true,
                        'position' => 'right',
                        'title' => [
                            'display' => true,
                            'text' => $this->currentChartType === 'production_trend' ? 'Buckets' : 'Percentage / Hours'
                        ],
                        'grid' => [
                            'drawOnChartArea' => false,
                        ],
                    ],
                ],
            ]),
            
            'breakdown_analysis' => array_merge($baseOptions, [
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                        'title' => [
                            'display' => true,
                            'text' => 'Count'
                        ],
                    ],
                    'y1' => [
                        'type' => 'linear',
                        'display' => true,
                        'position' => 'right',
                        'title' => [
                            'display' => true,
                            'text' => 'Hours'
                        ],
                        'grid' => [
                            'drawOnChartArea' => false,
                        ],
                    ],
                ],
            ]),
            
            default => array_merge($baseOptions, [
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                    ],
                ],
            ])
        };
    }

    // ===========================
    // UTILITY METHODS - Helper functions
    // ===========================

    /**
     * UTILITY: Get date range berdasarkan time range selection
     */
    private function getDateRange(string $timeRange): array
    {
        return match($timeRange) {
            'today' => [Carbon::today(), Carbon::tomorrow()],
            'week' => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()->addDay()],
            'month' => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()->addDay()],
            'quarter' => [Carbon::now()->startOfQuarter(), Carbon::now()->endOfQuarter()->addDay()],
            default => [Carbon::today(), Carbon::tomorrow()]
        };
    }

    /**
     * UTILITY: Generate date periods untuk chart labels
     */
    private function generateDatePeriods(string $timeRange): array
    {
        $periods = [];

        switch($timeRange) {
            case 'today':
                // Hourly periods untuk today
                for ($hour = 0; $hour < 24; $hour += 4) {
                    $start = Carbon::today()->setHour($hour);
                    $end = Carbon::today()->setHour($hour + 4);
                    $periods[] = [
                        'start' => $start,
                        'end' => $end,
                        'label' => $start->format('H:i') . '-' . $end->format('H:i'),
                    ];
                }
                break;

            case 'week':
                // Daily periods untuk this week
                $startOfWeek = Carbon::now()->startOfWeek();
                for ($day = 0; $day < 7; $day++) {
                    $date = $startOfWeek->copy()->addDays($day);
                    $periods[] = [
                        'start' => $date->startOfDay(),
                        'end' => $date->endOfDay(),
                        'label' => $date->format('M j'),
                    ];
                }
                break;

            case 'month':
                // Weekly periods untuk this month
                $startOfMonth = Carbon::now()->startOfMonth();
                $endOfMonth = Carbon::now()->endOfMonth();
                $weekStart = $startOfMonth->copy();
                
                while ($weekStart->lte($endOfMonth)) {
                    $weekEnd = $weekStart->copy()->addDays(6)->min($endOfMonth);
                    $periods[] = [
                        'start' => $weekStart->startOfDay(),
                        'end' => $weekEnd->endOfDay(),
                        'label' => $weekStart->format('M j') . '-' . $weekEnd->format('j'),
                    ];
                    $weekStart->addWeeks(1);
                }
                break;

            default:
                // Default to daily
                $periods[] = [
                    'start' => Carbon::today(),
                    'end' => Carbon::tomorrow(),
                    'label' => 'Today',
                ];
        }

        return $periods;
    }

    // ===========================
    // LIVEWIRE INTERACTIONS - Dynamic chart switching
    // ===========================

    /**
     * ACTION: Switch chart type
     */
    public function switchChartType(string $chartType): void
    {
        $this->currentChartType = $chartType;
        
        // Save preference
        session(['dashboard_chart_type' => $chartType]);
        
        // Clear cache untuk refresh data
        $this->clearChartCache();
        
        // Re-render chart
        $this->dispatch('$refresh');
    }

    /**
     * PERFORMANCE: Clear chart-specific cache
     */
    private function clearChartCache(): void
    {
        $timeRanges = ['today', 'week', 'month', 'quarter'];
        $chartTypes = ['production_trend', 'equipment_utilization', 'breakdown_analysis', 'session_performance', 'hourly_production'];
        
        foreach ($timeRanges as $timeRange) {
            foreach ($chartTypes as $chartType) {
                Cache::forget("dashboard_performance_chart_{$chartType}_{$timeRange}");
            }
        }
    }

    /**
     * EVENT LISTENERS: Handle dashboard events
     */
    protected $listeners = [
        'dashboard-refreshed' => 'refreshWidget',
        'dashboard-filter-changed' => 'handleFilterChange',
    ];

    /**
     * EVENT HANDLER: Refresh widget
     */
    public function refreshWidget(): void
    {
        $this->clearChartCache();
        $this->dispatch('$refresh');
    }

    /**
     * EVENT HANDLER: Handle filter changes
     */
    public function handleFilterChange($data): void
    {
        $this->clearChartCache();
        $this->dispatch('$refresh');
    }

    /**
     * HELPER: Get available chart types untuk switching
     */
    public function getAvailableChartTypes(): array
    {
        return [
            'production_trend' => 'Production Trend',
            'equipment_utilization' => 'Equipment Utilization',
            'breakdown_analysis' => 'Breakdown Analysis',
            'session_performance' => 'Session Performance',
            'hourly_production' => 'Hourly Production',
        ];
    }
}
<?php
// ===========================
// app/Filament/Widgets/ProductionMetricsWidget.php
// File yang dihasilkan dari: php artisan make:filament-widget ProductionMetricsWidget --stats-overview
// Kemudian dimodifikasi untuk production KPI cards dengan real-time data
// ===========================

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use App\Models\LoadingSession;
use App\Models\Equipment;
use App\Models\BucketActivity;
use App\Models\EquipmentBreakdown;
use Carbon\Carbon;

class ProductionMetricsWidget extends BaseWidget
{
    // ===========================
    // WIDGET CONFIGURATION - Dari template, ditambah customization
    // ===========================
    
    /**
     * PERFORMANCE: Polling interval untuk real-time updates
     * Menggunakan config untuk flexible polling rate
     */
    protected static ?string $pollingInterval = null;

    /**
     * LAYOUT: Widget column span untuk responsive layout
     */
    protected int | string | array $columnSpan = 'full';

    /**
     * FEATURE: Sort order dalam dashboard
     */
    protected static ?int $sort = 1;

    // ===========================
    // INITIALIZATION - Setup polling dari config
    // ===========================

    /**
     * SETUP: Mount method untuk initialize widget settings
     * Menggunakan user preferences dan config untuk polling interval
     */
    public function mount(): void
    {
        // Get polling interval dari user preferences atau config default
        $refreshInterval = session('dashboard_preferences.refresh_interval', '300');
        
        if ($refreshInterval !== '0') {
            static::$pollingInterval = $refreshInterval . 's';
        }
    }

    // ===========================
    // CORE FUNCTIONALITY - Stats calculation dengan caching
    // ===========================

    /**
     * MAIN METHOD: Generate stats cards dengan real-time production data
     * Menggunakan caching untuk performance optimization
     */
    protected function getStats(): array
    {
        // Get time range dari user preferences
        $timeRange = session('dashboard_preferences.time_range', 'today');
        $dateRange = $this->getDateRange($timeRange);

        // Cache key berdasarkan time range untuk efficient caching
        $cacheKey = "dashboard_production_metrics_{$timeRange}";
        $cacheTTL = config('mining.performance.cache_ttl.equipment_status', 300);

        return Cache::remember($cacheKey, $cacheTTL, function () use ($dateRange, $timeRange) {
            return [
                $this->getTotalProductionStat($dateRange, $timeRange),
                $this->getSessionPerformanceStat($dateRange, $timeRange),
                $this->getEquipmentUtilizationStat(),
                $this->getBreakdownImpactStat($dateRange),
            ];
        });
    }

    // ===========================
    // INDIVIDUAL STAT METHODS - Setiap KPI card dengan business logic
    // ===========================

    /**
     * KPI CARD: Total Production (Tonnage & Buckets)
     * Menampilkan total tonnage dan bucket count dengan trend comparison
     */
    private function getTotalProductionStat(array $dateRange, string $timeRange): Stat
    {
        // Current period data
        $currentTonnage = BucketActivity::whereBetween('activity_time', $dateRange)
            ->sum('estimated_tonnage');
        
        $currentBuckets = BucketActivity::whereBetween('activity_time', $dateRange)
            ->sum('bucket_count');

        // Previous period untuk comparison
        $previousRange = $this->getPreviousDateRange($timeRange);
        $previousTonnage = BucketActivity::whereBetween('activity_time', $previousRange)
            ->sum('estimated_tonnage');

        // Calculate trend percentage
        $tonnageChange = $this->calculatePercentageChange($previousTonnage, $currentTonnage);
        
        // Determine trend color dan icon
        $trendColor = $tonnageChange >= 0 ? 'success' : 'danger';
        $trendIcon = $tonnageChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';

        return Stat::make('Total Production', number_format($currentTonnage, 1) . ' tons')
            ->description($currentBuckets . ' buckets loaded')
            ->descriptionIcon($trendIcon)
            ->descriptionColor($trendColor)
            ->chart($this->getProductionTrendChart($timeRange))
            ->color('success')
            ->extraAttributes([
                'class' => 'dashboard-card',
            ]);
    }

    /**
     * KPI CARD: Session Performance
     * Menampilkan session completion rate dan efficiency metrics
     */
    private function getSessionPerformanceStat(array $dateRange, string $timeRange): Stat
    {
        $totalSessions = LoadingSession::whereBetween('start_time', $dateRange)->count();
        $completedSessions = LoadingSession::whereBetween('start_time', $dateRange)
            ->where('status', 'completed')
            ->count();

        // Calculate completion rate
        $completionRate = $totalSessions > 0 ? round(($completedSessions / $totalSessions) * 100, 1) : 0;

        // Get average session duration untuk completed sessions
        $avgDuration = LoadingSession::whereBetween('start_time', $dateRange)
            ->where('status', 'completed')
            ->whereNotNull('end_time')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, start_time, end_time)) as avg_minutes')
            ->value('avg_minutes');

        $avgDurationText = $avgDuration ? round($avgDuration / 60, 1) . 'h avg' : 'N/A';

        // Determine performance color
        $performanceColor = $completionRate >= 90 ? 'success' : ($completionRate >= 70 ? 'warning' : 'danger');

        return Stat::make('Session Performance', $completionRate . '%')
            ->description("{$completedSessions}/{$totalSessions} completed ({$avgDurationText})")
            ->descriptionIcon('heroicon-m-clock')
            ->color($performanceColor)
            ->chart($this->getSessionTrendChart($timeRange))
            ->extraAttributes([
                'class' => 'dashboard-card',
            ]);
    }

    /**
     * KPI CARD: Equipment Utilization
     * Real-time equipment status distribution dengan utilization rate
     */
    private function getEquipmentUtilizationStat(): Stat
    {
        $totalEquipment = Equipment::active()->count();
        
        // Get current equipment status distribution
        $statusCounts = Equipment::active()
            ->get()
            ->groupBy('current_status')
            ->map->count();

        $workingCount = $statusCounts->get('working', 0);
        $idleCount = $statusCounts->get('idle', 0);
        $breakdownCount = $statusCounts->get('breakdown', 0);
        $maintenanceCount = $statusCounts->get('maintenance', 0);

        // Calculate utilization rate (working equipment / available equipment)
        $availableEquipment = $totalEquipment - $breakdownCount - $maintenanceCount;
        $utilizationRate = $availableEquipment > 0 ? round(($workingCount / $availableEquipment) * 100, 1) : 0;

        // Status breakdown untuk description
        $statusText = "🟢 {$workingCount} Working  🟡 {$idleCount} Idle  🔴 {$breakdownCount} Down";

        // Color berdasarkan utilization rate
        $utilizationColor = $utilizationRate >= 80 ? 'success' : ($utilizationRate >= 60 ? 'warning' : 'danger');

        return Stat::make('Equipment Utilization', $utilizationRate . '%')
            ->description($statusText)
            ->descriptionIcon('heroicon-m-truck')
            ->color($utilizationColor)
            ->chart($this->getUtilizationChart())
            ->extraAttributes([
                'class' => 'dashboard-card',
            ]);
    }

    /**
     * KPI CARD: Breakdown Impact
     * Active breakdowns dengan downtime dan cost impact
     */
    private function getBreakdownImpactStat(array $dateRange): Stat
    {
        $activeBreakdowns = EquipmentBreakdown::whereIn('status', ['ongoing', 'pending_parts'])->count();
        
        // Calculate total downtime for current period
        $totalDowntimeMinutes = EquipmentBreakdown::whereBetween('start_time', $dateRange)
            ->whereNotNull('duration_minutes')
            ->sum('duration_minutes');

        $totalDowntimeHours = $totalDowntimeMinutes > 0 ? round($totalDowntimeMinutes / 60, 1) : 0;

        // Calculate repair costs for current period
        $totalRepairCost = EquipmentBreakdown::whereBetween('start_time', $dateRange)
            ->sum('repair_cost');

        // Format cost untuk display
        $costText = $totalRepairCost > 0 ? 'Rp ' . number_format($totalRepairCost / 1000000, 1) . 'M' : 'Rp 0';

        // Description dengan downtime dan cost
        $impactText = "{$totalDowntimeHours}h downtime, {$costText} costs";

        // Color berdasarkan active breakdowns
        $impactColor = $activeBreakdowns === 0 ? 'success' : ($activeBreakdowns <= 2 ? 'warning' : 'danger');

        return Stat::make('Active Breakdowns', $activeBreakdowns)
            ->description($impactText)
            ->descriptionIcon('heroicon-m-exclamation-triangle')
            ->color($impactColor)
            ->chart($this->getBreakdownTrendChart())
            ->extraAttributes([
                'class' => 'dashboard-card',
            ]);
    }

    // ===========================
    // HELPER METHODS - Utility functions untuk calculations
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
     * UTILITY: Get previous date range untuk trend comparison
     */
    private function getPreviousDateRange(string $timeRange): array
    {
        return match($timeRange) {
            'today' => [Carbon::yesterday(), Carbon::today()],
            'week' => [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()->addDay()],
            'month' => [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()->addDay()],
            'quarter' => [Carbon::now()->subQuarter()->startOfQuarter(), Carbon::now()->subQuarter()->endOfQuarter()->addDay()],
            default => [Carbon::yesterday(), Carbon::today()]
        };
    }

    /**
     * CALCULATION: Calculate percentage change dengan null safety
     */
    private function calculatePercentageChange(?float $previous, ?float $current): float
    {
        if (!$previous || $previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    // ===========================
    // CHART DATA METHODS - Mini charts untuk trend visualization
    // ===========================

    /**
     * CHART: Production trend chart untuk recent days
     */
    private function getProductionTrendChart(string $timeRange): array
    {
        $days = $timeRange === 'today' ? 7 : ($timeRange === 'week' ? 4 : 6);
        $chartData = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $tonnage = BucketActivity::whereDate('activity_time', $date)
                ->sum('estimated_tonnage');
            
            $chartData[] = $tonnage ?? 0;
        }

        return $chartData;
    }

    /**
     * CHART: Session trend chart
     */
    private function getSessionTrendChart(string $timeRange): array
    {
        $days = 7;
        $chartData = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $completedSessions = LoadingSession::whereDate('start_time', $date)
                ->where('status', 'completed')
                ->count();
            
            $chartData[] = $completedSessions;
        }

        return $chartData;
    }

    /**
     * CHART: Equipment utilization trend
     */
    private function getUtilizationChart(): array
    {
        // Simple chart dengan current status distribution
        $statusCounts = Equipment::active()
            ->get()
            ->groupBy('current_status')
            ->map->count();

        return [
            $statusCounts->get('working', 0),
            $statusCounts->get('idle', 0),
            $statusCounts->get('breakdown', 0),
            $statusCounts->get('maintenance', 0),
        ];
    }

    /**
     * CHART: Breakdown trend chart
     */
    private function getBreakdownTrendChart(): array
    {
        $days = 7;
        $chartData = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $breakdowns = EquipmentBreakdown::whereDate('start_time', $date)->count();
            
            $chartData[] = $breakdowns;
        }

        return $chartData;
    }

    // ===========================
    // LIVEWIRE EVENTS - Real-time updates
    // ===========================

    /**
     * EVENT LISTENER: Handle dashboard refresh events
     */
    public function refreshWidget(): void
    {
        // Clear widget-specific cache
        $timeRange = session('dashboard_preferences.time_range', 'today');
        Cache::forget("dashboard_production_metrics_{$timeRange}");
        
        // Re-render widget
        $this->dispatch('$refresh');
    }

    /**
     * EVENT LISTENER: Handle time range filter changes
     */
    protected $listeners = [
        'dashboard-refreshed' => 'refreshWidget',
        'dashboard-filter-changed' => 'handleFilterChange',
    ];

    /**
     * FILTER HANDLER: Update widget when time range filter changes
     */
    public function handleFilterChange($data): void
    {
        // Clear cache for all time ranges
        $timeRanges = ['today', 'week', 'month', 'quarter'];
        foreach ($timeRanges as $range) {
            Cache::forget("dashboard_production_metrics_{$range}");
        }
        
        // Re-render widget dengan new filter
        $this->dispatch('$refresh');
    }
}
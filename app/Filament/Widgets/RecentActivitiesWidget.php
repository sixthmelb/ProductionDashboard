<?php
// ===========================
// app/Filament/Widgets/RecentActivitiesWidget.php
// File yang dihasilkan dari: php artisan make:filament-widget RecentActivitiesWidget --table
// Kemudian dimodifikasi untuk recent activities monitoring
// ===========================

namespace App\Filament\Widgets;

use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;
use App\Models\BucketActivity;
use App\Models\EquipmentStatusLog;
use App\Models\EquipmentBreakdown;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class RecentActivitiesWidget extends BaseWidget
{
    // ===========================
    // WIDGET CONFIGURATION
    // ===========================
    
    /**
     * PERFORMANCE: Polling interval untuk real-time updates
     */
    protected static ?string $pollingInterval = '60s';

    /**
     * LAYOUT: Widget column span
     */
    protected int | string | array $columnSpan = 'full';

    /**
     * FEATURE: Sort order dalam dashboard
     */
    protected static ?int $sort = 4;

    /**
     * HEADING: Widget title
     */
    protected static ?string $heading = 'Recent Bucket Activities';

    // ===========================
    // SIMPLIFIED TABLE - Hanya Bucket Activities
    // ===========================

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getBucketActivitiesQuery())
            ->columns([
                Tables\Columns\TextColumn::make('activity_time')
                    ->label('Time')
                    ->dateTime('H:i:s')
                    ->sortable()
                    ->description(fn ($record): string => $record->activity_time->diffForHumans()),

                Tables\Columns\TextColumn::make('loadingSession.session_code')
                    ->label('Session')
                    ->badge()
                    ->color('info')
                    ->searchable(),

                Tables\Columns\TextColumn::make('excavator.code')
                    ->label('Excavator')
                    ->badge()
                    ->color('success')
                    ->searchable(),

                Tables\Columns\TextColumn::make('dumptruck.code')
                    ->label('Dump Truck')
                    ->badge()
                    ->color('warning')
                    ->searchable(),

                Tables\Columns\TextColumn::make('bucket_count')
                    ->label('Buckets')
                    ->badge()
                    ->color('primary')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('estimated_tonnage')
                    ->label('Tonnage')
                    ->numeric(2)
                    ->suffix(' ton')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(50)
                    ->placeholder('-'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('excavator_id')
                    ->label('Excavator')
                    ->relationship('excavator', 'code'),

                Tables\Filters\SelectFilter::make('dumptruck_id')
                    ->label('Dump Truck')
                    ->relationship('dumptruck', 'code'),

                Tables\Filters\Filter::make('today')
                    ->label('Today Only')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereDate('activity_time', today())
                    ),

                Tables\Filters\Filter::make('last_hour')
                    ->label('Last Hour')
                    ->query(fn (Builder $query): Builder => 
                        $query->where('activity_time', '>=', now()->subHour())
                    ),
            ])
            ->defaultSort('activity_time', 'desc')
            ->poll('60s')
            ->striped()
            ->paginated([10, 25, 50])
            ->emptyStateHeading('No Recent Activities')
            ->emptyStateDescription('Bucket loading activities will appear here as they occur.')
            ->emptyStateIcon('heroicon-o-truck');
    }

    // ===========================
    // DATA PREPARATION - Removed complex virtual query approach
    // ===========================

    /**
     * SIMPLIFIED: Hanya bucket activities untuk avoid complexity
     */
    private function getBucketActivitiesQuery(): Builder
    {
        $timeRange = session('dashboard_preferences.time_range', 'today');
        $dateRange = $this->getDateRange($timeRange);

        return BucketActivity::with([
                'excavator:id,code,type',
                'dumptruck:id,code,type', 
                'loadingSession:id,session_code'
            ])
            ->whereBetween('activity_time', $dateRange)
            ->orderBy('activity_time', 'desc');
    }

    // ===========================
    // UTILITY METHODS
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

    // ===========================
    // EVENT HANDLERS
    // ===========================

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
        $this->dispatch('$refresh');
    }

    /**
     * EVENT HANDLER: Handle filter changes
     */
    public function handleFilterChange($data): void
    {
        $this->dispatch('$refresh');
    }

    // ===========================
    // ADDITIONAL HELPER METHODS
    // ===========================

    /**
     * HELPER: Get activity summary untuk widget info
     */
    public function getActivitySummary(): array
    {
        $timeRange = session('dashboard_preferences.time_range', 'today');
        $dateRange = $this->getDateRange($timeRange);

        $totalActivities = BucketActivity::whereBetween('activity_time', $dateRange)->count();
        $totalTonnage = BucketActivity::whereBetween('activity_time', $dateRange)->sum('estimated_tonnage');
        $totalBuckets = BucketActivity::whereBetween('activity_time', $dateRange)->sum('bucket_count');

        return [
            'total_activities' => $totalActivities,
            'total_tonnage' => round($totalTonnage, 2),
            'total_buckets' => $totalBuckets,
            'period' => $timeRange,
        ];
    }

    /**
     * PERFORMANCE: Mount method untuk initialization
     */
    public function mount(): void
    {
        // Set default heading berdasarkan time range
        $timeRange = session('dashboard_preferences.time_range', 'today');
        static::$heading = match($timeRange) {
            'today' => 'Today\'s Bucket Activities',
            'week' => 'This Week\'s Bucket Activities', 
            'month' => 'This Month\'s Bucket Activities',
            'quarter' => 'This Quarter\'s Bucket Activities',
            default => 'Recent Bucket Activities'
        };
    }
}

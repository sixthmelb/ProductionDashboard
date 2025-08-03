<?php
// ===========================
// app/Filament/Pages/ManagerDashboard.php
// File yang dihasilkan dari: php artisan make:filament-page ManagerDashboard
// Kemudian dimodifikasi untuk production dashboard dengan role-based access
// ===========================

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Pages\Concerns\InteractsWithHeaderActions;
use Filament\Actions;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;

class ManagerDashboard extends Page
{
    use InteractsWithHeaderActions;

    // ===========================
    // PAGE CONFIGURATION - Dimodifikasi dari template default
    // ===========================
    
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';
    
    protected static string $view = 'filament.pages.manager-dashboard';
    
    protected static ?string $navigationLabel = 'Production Dashboard';
    
    protected static ?string $title = 'Production Dashboard';
    
    // TAMBAHAN: Posisi di navigation menu untuk dashboard utama
    protected static ?int $navigationSort = 1;
    
    // TAMBAHAN: Group dalam navigation untuk memisahkan dari operations
    protected static ?string $navigationGroup = 'Analytics';

    // TAMBAHAN: Max width untuk layout yang optimal
    public function getMaxWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    // ===========================
    // ACCESS CONTROL - Role-based permissions
    // ===========================
    
    /**
     * SECURITY: Role-based access control
     * Hanya Manager dan SuperAdmin yang bisa akses dashboard ini
     * Menggunakan Spatie Permission untuk check roles
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();
        
        // Check apakah user exist dan punya role yang diizinkan
        if (!$user) {
            return false;
        }

        // Allow access untuk manager dan superadmin
        return $user->hasAnyRole(['manager', 'superadmin']);
    }

    /**
     * ENHANCEMENT: Dynamic subtitle dengan info real-time
     * Menampilkan shift aktif dan waktu update terakhir
     */
    public function getSubheading(): string|Htmlable|null
    {
        $currentShift = $this->getCurrentShift();
        $lastUpdate = now()->format('H:i');
        
        return "Production Analytics & KPIs | Current Shift: {$currentShift} | Last Update: {$lastUpdate}";
    }

    // ===========================
    // HEADER ACTIONS - Quick access tools untuk manager
    // ===========================
    
    /**
     * FUNCTIONALITY: Header actions untuk manager dashboard
     * Export, refresh, dan settings functionality
     */
    protected function getHeaderActions(): array
    {
        return [
            // FEATURE: Export Production Report
            Actions\Action::make('exportReport')
                ->label('Export Report')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->form([
                    \Filament\Forms\Components\Select::make('report_type')
                        ->label('Report Type')
                        ->options([
                            'daily' => 'Daily Production Report',
                            'weekly' => 'Weekly Performance Report',
                            'monthly' => 'Monthly Analytics Report',
                            'breakdown' => 'Breakdown Analysis Report',
                        ])
                        ->required(),
                    
                    \Filament\Forms\Components\DatePicker::make('start_date')
                        ->label('Start Date')
                        ->default(today())
                        ->required(),
                    
                    \Filament\Forms\Components\DatePicker::make('end_date')
                        ->label('End Date')
                        ->default(today())
                        ->after('start_date')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    // TODO: Implement export functionality dengan Service
                    \App\Services\DashboardService::exportReport($data);
                    
                    $this->notify('success', 'Report export started. You will receive notification when ready.');
                }),
            
            // FEATURE: Manual refresh untuk clear cache dan reload data
            Actions\Action::make('refreshDashboard')
                ->label('Refresh Data')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    // Clear dashboard cache untuk force refresh
                    $this->clearDashboardCache();
                    
                    // Emit refresh event ke semua widgets
                    $this->dispatch('dashboard-refreshed');
                    
                    $this->notify('success', 'Dashboard data refreshed successfully.');
                }),
            
            // FEATURE: Dashboard personalization settings
            Actions\Action::make('dashboardSettings')
                ->label('Settings')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->slideOver()
                ->form([
                    \Filament\Forms\Components\Select::make('time_range')
                        ->label('Default Time Range')
                        ->options([
                            'today' => 'Today',
                            'week' => 'This Week',
                            'month' => 'This Month',
                            'quarter' => 'This Quarter',
                        ])
                        ->default(session('dashboard_preferences.time_range', 'today')),
                    
                    \Filament\Forms\Components\Toggle::make('auto_refresh')
                        ->label('Auto Refresh')
                        ->helperText('Automatically refresh dashboard every 5 minutes')
                        ->default(session('dashboard_preferences.auto_refresh', true)),
                    
                    \Filament\Forms\Components\CheckboxList::make('visible_widgets')
                        ->label('Visible Widgets')
                        ->options([
                            'production_metrics' => 'Production Metrics',
                            'equipment_status' => 'Equipment Status',
                            'performance_charts' => 'Performance Charts',
                            'recent_activities' => 'Recent Activities',
                            'breakdown_summary' => 'Breakdown Summary',
                        ])
                        ->default(session('dashboard_preferences.visible_widgets', [
                            'production_metrics', 
                            'equipment_status', 
                            'performance_charts'
                        ])),
                    
                    \Filament\Forms\Components\Select::make('refresh_interval')
                        ->label('Refresh Interval')
                        ->options([
                            '60' => '1 minute',
                            '300' => '5 minutes', 
                            '600' => '10 minutes',
                            '0' => 'Manual only',
                        ])
                        ->default(session('dashboard_preferences.refresh_interval', '300')),
                ])
                ->action(function (array $data): void {
                    // PERSISTENCE: Save dashboard preferences ke session
                    session(['dashboard_preferences' => $data]);
                    
                    $this->notify('success', 'Dashboard preferences saved.');
                    
                    // Refresh page untuk apply settings
                    $this->redirect(request()->header('Referer'));
                }),
        ];
    }

    // ===========================
    // WIDGET MANAGEMENT - Dynamic widget loading berdasarkan preferences
    // ===========================
    
    /**
     * CUSTOMIZATION: Dynamic widget loading berdasarkan user preferences
     * Widget ditampilkan sesuai dengan settings yang dipilih user
     */
    protected function getHeaderWidgets(): array
    {
        // Get user preferences dari session
        $preferences = session('dashboard_preferences', []);
        $visibleWidgets = $preferences['visible_widgets'] ?? [
            'production_metrics', 
            'equipment_status', 
            'performance_charts',
            'recent_activities'
        ];

        $widgets = [];

        // CONDITIONAL: Production Metrics Widget (KPI Cards)
        if (in_array('production_metrics', $visibleWidgets)) {
            $widgets[] = \App\Filament\Widgets\ProductionMetricsWidget::class;
        }

        // CONDITIONAL: Equipment Status Overview Widget  
        if (in_array('equipment_status', $visibleWidgets)) {
            $widgets[] = \App\Filament\Widgets\EquipmentStatusWidget::class;
        }

        // CONDITIONAL: Performance Charts Widget
        if (in_array('performance_charts', $visibleWidgets)) {
            $widgets[] = \App\Filament\Widgets\PerformanceChartsWidget::class;
        }

        // CONDITIONAL: Recent Activities Widget
        if (in_array('recent_activities', $visibleWidgets)) {
            $widgets[] = \App\Filament\Widgets\RecentActivitiesWidget::class;
        }

        // CONDITIONAL: Breakdown Summary Widget
        if (in_array('breakdown_summary', $visibleWidgets)) {
            $widgets[] = \App\Filament\Widgets\BreakdownSummaryWidget::class;
        }

        return $widgets;
    }

    /**
     * LAYOUT: Widget columns configuration untuk responsive design
     * Menyesuaikan layout berdasarkan screen size
     */
    public function getWidgetColumns(): int|string|array
    {
        return [
            'sm' => 1,   // Small screens: 1 column
            'md' => 2,   // Medium screens: 2 columns  
            'lg' => 2,   // Large screens: 2 columns
            'xl' => 3,   // Extra large: 3 columns
            '2xl' => 3,  // 2XL screens: 3 columns
        ];
    }

    // ===========================
    // BUSINESS LOGIC HELPERS - Utility methods untuk dashboard
    // ===========================
    
    /**
     * UTILITY: Get current shift berdasarkan waktu sekarang
     * Menggunakan config mining untuk shift definitions
     */
    private function getCurrentShift(): string
    {
        $hour = now()->hour;
        
        // Menggunakan config shifts dari config/mining.php
        $shifts = config('mining.operations.shifts', [
            'A' => ['start' => '07:00', 'end' => '15:00'],
            'B' => ['start' => '15:00', 'end' => '23:00'],
            'C' => ['start' => '23:00', 'end' => '07:00'],
        ]);
        
        if ($hour >= 7 && $hour < 15) {
            return 'A (07:00-15:00)';
        } elseif ($hour >= 15 && $hour < 23) {
            return 'B (15:00-23:00)';
        } else {
            return 'C (23:00-07:00)';
        }
    }

    /**
     * PERFORMANCE: Clear dashboard cache untuk refresh data
     * Menghapus cache yang terkait dengan dashboard widgets
     */
    private function clearDashboardCache(): void
    {
        // Cache keys untuk dashboard data
        $cacheKeys = [
            'dashboard_production_metrics',
            'dashboard_equipment_status', 
            'dashboard_performance_data',
            'dashboard_recent_activities',
            'dashboard_breakdown_summary',
        ];

        // Clear dashboard-specific cache
        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }

        // Clear equipment-related cache juga karena dashboard rely on equipment data
        $equipmentIds = \App\Models\Equipment::pluck('id');
        foreach ($equipmentIds as $equipmentId) {
            Cache::forget("equipment_status_{$equipmentId}");
            Cache::forget("equipment_can_work_{$equipmentId}");
            Cache::forget("equipment_breakdown_reason_{$equipmentId}");
            Cache::forget("equipment_active_breakdown_{$equipmentId}");
        }
    }

    /**
     * NOTIFICATION: Helper method untuk show notifications
     * Standardized notification system
     */
    private function notify(string $type, string $message): void
    {
        \Filament\Notifications\Notification::make()
            ->title($message)
            ->{$type}()
            ->duration(5000) // 5 seconds
            ->send();
    }

    // ===========================
    // PAGE LIFECYCLE - Setup dan initialization
    // ===========================
    
    /**
     * INITIALIZATION: Mount method - executed ketika page load
     * Setup default preferences dan initial data loading
     */
    public function mount(): void
    {
        // Set default preferences jika belum ada
        if (!session()->has('dashboard_preferences')) {
            session(['dashboard_preferences' => [
                'time_range' => 'today',
                'auto_refresh' => true,
                'visible_widgets' => [
                    'production_metrics', 
                    'equipment_status', 
                    'performance_charts'
                ],
                'refresh_interval' => '300', // 5 minutes
            ]]);
        }

        // Pre-load critical data untuk faster rendering
        $this->preloadDashboardData();
    }

    /**
     * PERFORMANCE: Pre-load critical data untuk faster dashboard rendering
     * Warm up cache dengan data yang akan digunakan oleh widgets
     */
    private function preloadDashboardData(): void
    {
        // Pre-load equipment data dengan relationships
        \App\Models\Equipment::with([
            'currentStatusLog:id,equipment_id,status,fuel_level,operator_name,location,status_time',
            'activeBreakdowns:id,equipment_id,breakdown_type,severity,description,status,start_time'
        ])->get();

        // Pre-load active sessions
        \App\Models\LoadingSession::active()
            ->with([
                'stackingArea:id,code,name',
                'user:id,name'
            ])
            ->get();
    }
}
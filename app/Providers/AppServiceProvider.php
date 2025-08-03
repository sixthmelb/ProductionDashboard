<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
        // PERFORMANCE: Register Equipment Breakdown Observer untuk cache management
        \App\Models\EquipmentBreakdown::observe(\App\Observers\EquipmentBreakdownObserver::class);
        
        // PERFORMANCE: Register Equipment Status Log Observer untuk cache invalidation
        \App\Models\EquipmentStatusLog::observe(\App\Observers\EquipmentStatusLogObserver::class);
        
        // DASHBOARD: Register view composers untuk dashboard data
        view()->composer('filament.pages.manager-dashboard', function ($view) {
            // Pre-load critical dashboard data untuk faster rendering
            $view->with([
                'criticalAlertsCount' => \App\Models\EquipmentBreakdown::where('severity', 'critical')
                    ->whereIn('status', ['ongoing', 'pending_parts'])
                    ->count(),
                'lowFuelEquipmentCount' => \App\Models\EquipmentStatusLog::whereHas('equipment', function($query) {
                        $query->where('status', 'active');
                    })
                    ->where('fuel_level', '<', 20)
                    ->whereNotNull('fuel_level')
                    ->whereRaw('id = (SELECT MAX(id) FROM equipment_status_log WHERE equipment_id = equipment_status_log.equipment_id)')
                    ->count(),
            ]);
        });
    }
}

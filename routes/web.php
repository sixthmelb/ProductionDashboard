<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';

// DASHBOARD: Routes untuk manager dashboard functionality
Route::middleware(['auth', 'verified'])->prefix('admin/dashboard')->group(function () {
    
    // UPDATE PREFERENCES: AJAX endpoint untuk update dashboard preferences
    Route::post('/update-preferences', function (Illuminate\Http\Request $request) {
        // VALIDATION: Validate preference data
        $validated = $request->validate([
            'time_range' => 'nullable|in:today,week,month,quarter',
            'auto_refresh' => 'nullable|boolean', 
            'visible_widgets' => 'nullable|array',
            'refresh_interval' => 'nullable|in:60,300,600,0',
        ]);

        // UPDATE: Save preferences ke session
        $currentPreferences = session('dashboard_preferences', []);
        $updatedPreferences = array_merge($currentPreferences, $validated);
        
        session(['dashboard_preferences' => $updatedPreferences]);

        // RESPONSE: Return success response
        return response()->json([
            'success' => true,
            'message' => 'Preferences updated successfully',
            'preferences' => $updatedPreferences
        ]);
    })->name('dashboard.update-preferences');

    // EXPORT REPORT: Endpoint untuk export dashboard reports
    Route::post('/export-report', function (Illuminate\Http\Request $request) {
        // VALIDATION: Validate export parameters
        $validated = $request->validate([
            'report_type' => 'required|in:daily,weekly,monthly,breakdown',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        try {
            // TODO: Implement actual export logic menggunakan DashboardService
            // \App\Services\DashboardService::exportReport($validated);

            // TEMPORARY: Simulate export process
            return response()->json([
                'success' => true,
                'message' => 'Export started. You will receive notification when ready.',
                'export_id' => uniqid('export_')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    })->name('dashboard.export-report');

    // REFRESH CACHE: Endpoint untuk manual cache refresh
    Route::post('/refresh-cache', function () {
        try {
            // CACHE: Clear dashboard-related cache
            $cacheKeys = [
                'dashboard_production_metrics',
                'dashboard_equipment_status',
                'dashboard_equipment_summary', 
                'dashboard_performance_data',
            ];

            foreach ($cacheKeys as $key) {
                \Illuminate\Support\Facades\Cache::forget($key);
            }

            // EQUIPMENT: Clear equipment-specific cache
            \App\Models\Equipment::all()->each(function ($equipment) {
                \Illuminate\Support\Facades\Cache::forget("equipment_status_{$equipment->id}");
                \Illuminate\Support\Facades\Cache::forget("equipment_can_work_{$equipment->id}");
            });

            return response()->json([
                'success' => true,
                'message' => 'Cache refreshed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cache refresh failed: ' . $e->getMessage()
            ], 500);
        }
    })->name('dashboard.refresh-cache');
});

<?php
// ===========================
// app/Observers/EquipmentStatusLogObserver.php
// Observer yang dihasilkan dari: php artisan make:observer EquipmentStatusLogObserver --model=EquipmentStatusLog
// Kemudian dimodifikasi untuk cache invalidation dan monitoring
// ===========================

namespace App\Observers;

use App\Models\EquipmentStatusLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EquipmentStatusLogObserver
{
    /**
     * Handle the EquipmentStatusLog "created" event.
     * PERFORMANCE: Invalidate cache ketika status log baru dibuat
     */
    public function created(EquipmentStatusLog $statusLog): void
    {
        // Clear cache untuk equipment ini karena status berubah
        $this->clearEquipmentCache($statusLog->equipment_id);

        // Log activity untuk monitoring
        Log::info('Equipment status log created', [
            'equipment_id' => $statusLog->equipment_id,
            'equipment_code' => $statusLog->equipment?->code,
            'status' => $statusLog->status,
            'operator' => $statusLog->operator_name,
            'fuel_level' => $statusLog->fuel_level,
        ]);

        // MONITORING: Check untuk critical conditions
        $this->checkCriticalConditions($statusLog);
    }

    /**
     * Handle the EquipmentStatusLog "updated" event.
     * CACHE: Invalidate cache ketika status log di-update
     */
    public function updated(EquipmentStatusLog $statusLog): void
    {
        // Clear cache untuk equipment ini
        $this->clearEquipmentCache($statusLog->equipment_id);

        // Log significant changes
        if ($statusLog->isDirty('status')) {
            Log::info('Equipment status changed', [
                'equipment_id' => $statusLog->equipment_id,
                'equipment_code' => $statusLog->equipment?->code,
                'old_status' => $statusLog->getOriginal('status'),
                'new_status' => $statusLog->status,
            ]);
        }

        // MONITORING: Check fuel level changes
        if ($statusLog->isDirty('fuel_level')) {
            $this->checkFuelLevel($statusLog);
        }
    }

    /**
     * Handle the EquipmentStatusLog "deleted" event.
     * CLEANUP: Invalidate cache ketika status log di-delete
     */
    public function deleted(EquipmentStatusLog $statusLog): void
    {
        // Clear cache untuk equipment ini
        $this->clearEquipmentCache($statusLog->equipment_id);

        Log::info('Equipment status log deleted', [
            'equipment_id' => $statusLog->equipment_id,
            'equipment_code' => $statusLog->equipment?->code,
            'status' => $statusLog->status,
        ]);
    }

    /**
     * MONITORING: Check critical conditions yang memerlukan alert
     */
    private function checkCriticalConditions(EquipmentStatusLog $statusLog): void
    {
        $criticalConditions = [];

        // Check fuel level critical
        if ($statusLog->fuel_level && $statusLog->fuel_level < 10) {
            $criticalConditions[] = "Critical fuel level: {$statusLog->fuel_level}%";
        }

        // Check status change to breakdown
        if ($statusLog->status === 'breakdown') {
            $criticalConditions[] = "Equipment status changed to breakdown";
        }

        // Log critical conditions
        if (!empty($criticalConditions)) {
            Log::warning('Critical equipment condition detected', [
                'equipment_id' => $statusLog->equipment_id,
                'equipment_code' => $statusLog->equipment?->code,
                'conditions' => $criticalConditions,
            ]);

            // TODO: Implement notification system
            // \App\Services\NotificationService::sendCriticalAlert($statusLog, $criticalConditions);
        }
    }

    /**
     * MONITORING: Check fuel level warnings
     */
    private function checkFuelLevel(EquipmentStatusLog $statusLog): void
    {
        if (!$statusLog->fuel_level) return;

        $oldFuelLevel = $statusLog->getOriginal('fuel_level');
        $newFuelLevel = $statusLog->fuel_level;

        // Check if fuel level crossed warning thresholds
        $warningLevel = config('mining.operations.equipment.fuel_warning_level', 20);
        $criticalLevel = config('mining.operations.equipment.fuel_critical_level', 10);

        // Fuel level dropped below warning threshold
        if ($oldFuelLevel >= $warningLevel && $newFuelLevel < $warningLevel) {
            Log::warning('Equipment fuel level below warning threshold', [
                'equipment_id' => $statusLog->equipment_id,
                'equipment_code' => $statusLog->equipment?->code,
                'fuel_level' => $newFuelLevel,
                'threshold' => $warningLevel,
            ]);
        }

        // Fuel level dropped below critical threshold
        if ($oldFuelLevel >= $criticalLevel && $newFuelLevel < $criticalLevel) {
            Log::error('Equipment fuel level critical', [
                'equipment_id' => $statusLog->equipment_id,
                'equipment_code' => $statusLog->equipment?->code,
                'fuel_level' => $newFuelLevel,
                'threshold' => $criticalLevel,
            ]);
        }
    }

    /**
     * PERFORMANCE: Clear semua cache terkait equipment status
     */
    private function clearEquipmentCache(int $equipmentId): void
    {
        $cacheKeys = [
            "equipment_status_{$equipmentId}",
            "equipment_can_work_{$equipmentId}",
            "equipment_breakdown_reason_{$equipmentId}",
            "equipment_active_breakdown_{$equipmentId}",
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }

        // Clear dashboard cache yang bergantung pada equipment status
        Cache::forget('dashboard_equipment_status');
        Cache::forget('dashboard_equipment_summary');
        Cache::forget('dashboard_production_metrics_today');
        Cache::forget('dashboard_production_metrics_week');
        Cache::forget('dashboard_production_metrics_month');
    }
}
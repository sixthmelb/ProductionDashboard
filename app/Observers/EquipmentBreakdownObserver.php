<?php
// ===========================
// app/Observers/EquipmentBreakdownObserver.php
// Observer yang dihasilkan dari: php artisan make:observer EquipmentBreakdownObserver --model=EquipmentBreakdown
// Kemudian dimodifikasi untuk cache management dan business logic
// ===========================

namespace App\Observers;

use App\Models\EquipmentBreakdown;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EquipmentBreakdownObserver
{
    /**
     * Handle the EquipmentBreakdown "creating" event.
     * VALIDATION: Business logic sebelum breakdown dibuat
     */
    public function creating(EquipmentBreakdown $breakdown): void
    {
        // Set default values untuk nullable fields
        if (is_null($breakdown->repair_cost)) {
            $breakdown->repair_cost = 0;
        }

        // Log activity untuk audit trail
        Log::info('Creating equipment breakdown', [
            'equipment_id' => $breakdown->equipment_id,
            'breakdown_type' => $breakdown->breakdown_type,
            'severity' => $breakdown->severity,
            'reported_by' => $breakdown->reported_by,
        ]);
    }

    /**
     * Handle the EquipmentBreakdown "created" event.
     * AUTOMATION: Clear cache dan update equipment status
     */
    public function created(EquipmentBreakdown $breakdown): void
    {
        // Clear equipment cache karena status berubah ke breakdown
        $this->clearEquipmentCache($breakdown->equipment_id);

        // Auto-create status log ketika breakdown dibuat
        $breakdown->equipment->statusLogs()->create([
            'status' => 'breakdown',
            'loading_session_id' => $breakdown->loading_session_id,
            'status_time' => $breakdown->start_time,
            'notes' => "Breakdown: {$breakdown->breakdown_type} - " . 
                      substr($breakdown->description, 0, 100),
        ]);

        Log::info('Equipment breakdown created and status updated', [
            'breakdown_id' => $breakdown->id,
            'equipment_code' => $breakdown->equipment->code,
        ]);
    }

    /**
     * Handle the EquipmentBreakdown "updating" event.
     * CALCULATION: Auto-calculate duration ketika end_time di-set
     */
    public function updating(EquipmentBreakdown $breakdown): void
    {
        // Auto-calculate duration ketika end_time di-set
        if ($breakdown->start_time && $breakdown->end_time && $breakdown->isDirty('end_time')) {
            $breakdown->duration_minutes = $breakdown->start_time->diffInMinutes($breakdown->end_time);
        }

        // Log perubahan status yang signifikan
        if ($breakdown->isDirty('status')) {
            Log::info('Equipment breakdown status changing', [
                'breakdown_id' => $breakdown->id,
                'equipment_code' => $breakdown->equipment->code,
                'old_status' => $breakdown->getOriginal('status'),
                'new_status' => $breakdown->status,
            ]);
        }
    }

    /**
     * Handle the EquipmentBreakdown "updated" event.
     * BUSINESS LOGIC: Manage equipment status transitions
     */
    public function updated(EquipmentBreakdown $breakdown): void
    {
        // Clear equipment cache untuk refresh status
        $this->clearEquipmentCache($breakdown->equipment_id);

        // Handle status change dari non-repaired ke repaired
        if ($breakdown->status === 'repaired' && $breakdown->getOriginal('status') !== 'repaired') {
            $this->handleBreakdownRepaired($breakdown);
        }

        // Handle status change dari repaired ke non-repaired (reopen)
        if (in_array($breakdown->status, ['ongoing', 'pending_parts']) && 
            $breakdown->getOriginal('status') === 'repaired') {
            $this->handleBreakdownReopened($breakdown);
        }
    }

    /**
     * Handle the EquipmentBreakdown "deleted" event.
     * CLEANUP: Clean up dan restore equipment status
     */
    public function deleted(EquipmentBreakdown $breakdown): void
    {
        // Clear equipment cache
        $this->clearEquipmentCache($breakdown->equipment_id);

        // Check apakah masih ada breakdown lain yang active
        $hasOtherBreakdowns = $breakdown->equipment->breakdowns()
            ->where('id', '!=', $breakdown->id)
            ->whereIn('status', ['ongoing', 'pending_parts'])
            ->exists();

        if (!$hasOtherBreakdowns) {
            // Tidak ada breakdown lain, set equipment ke idle
            $breakdown->equipment->statusLogs()->create([
                'status' => 'idle',
                'status_time' => now(),
                'notes' => 'Breakdown record deleted. Equipment status reset to idle.',
            ]);
        }

        Log::info('Equipment breakdown deleted', [
            'breakdown_id' => $breakdown->id,
            'equipment_code' => $breakdown->equipment->code,
            'has_other_breakdowns' => $hasOtherBreakdowns,
        ]);
    }

    /**
     * HELPER: Handle breakdown repaired logic
     */
    private function handleBreakdownRepaired(EquipmentBreakdown $breakdown): void
    {
        // Check apakah ada breakdown lain yang masih active
        $hasOtherBreakdowns = $breakdown->equipment->breakdowns()
            ->where('id', '!=', $breakdown->id)
            ->whereIn('status', ['ongoing', 'pending_parts'])
            ->exists();

        if (!$hasOtherBreakdowns) {
            // Tidak ada breakdown lain, set equipment kembali ke idle
            $breakdown->equipment->statusLogs()->create([
                'status' => 'idle',
                'loading_session_id' => $breakdown->loading_session_id,
                'status_time' => $breakdown->end_time ?? now(),
                'notes' => "Repaired from {$breakdown->breakdown_type} breakdown. Equipment ready for operation.",
            ]);
        }

        Log::info('Equipment breakdown repaired', [
            'breakdown_id' => $breakdown->id,
            'equipment_code' => $breakdown->equipment->code,
            'has_other_breakdowns' => $hasOtherBreakdowns,
            'new_status' => $hasOtherBreakdowns ? 'still_breakdown' : 'idle',
        ]);
    }

    /**
     * HELPER: Handle breakdown reopened logic
     */
    private function handleBreakdownReopened(EquipmentBreakdown $breakdown): void
    {
        // Set equipment kembali ke breakdown status
        $breakdown->equipment->statusLogs()->create([
            'status' => 'breakdown',
            'loading_session_id' => $breakdown->loading_session_id,
            'status_time' => now(),
            'notes' => "Breakdown status reverted to {$breakdown->status}. Issue reopened.",
        ]);

        Log::warning('Equipment breakdown reopened', [
            'breakdown_id' => $breakdown->id,
            'equipment_code' => $breakdown->equipment->code,
            'new_status' => $breakdown->status,
        ]);
    }

    /**
     * PERFORMANCE: Clear semua cache terkait equipment
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
        
        // Clear dashboard cache juga
        Cache::forget('dashboard_equipment_status');
        Cache::forget('dashboard_equipment_summary');
    }
}
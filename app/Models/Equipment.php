<?php
// ===========================
// app/Models/Equipment.php - Performance Optimized Version
// Menambahkan caching, eager loading, dan optimized queries
// ===========================

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Builder;

class Equipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'brand',
        'model',
        'capacity',
        'year_manufacture',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'decimal:2',
            'year_manufacture' => 'integer',
        ];
    }

    // ===========================
    // PERFORMANCE OPTIMIZATION: Relationship Definitions
    // ===========================

    public function bucketActivitiesAsExcavator(): HasMany
    {
        return $this->hasMany(BucketActivity::class, 'excavator_id');
    }

    public function bucketActivitiesAsDumptruck(): HasMany
    {
        return $this->hasMany(BucketActivity::class, 'dumptruck_id');
    }

    public function breakdowns(): HasMany
    {
        return $this->hasMany(EquipmentBreakdown::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(EquipmentStatusLog::class);
    }

    // OPTIMIZED: Menggunakan index yang baru ditambahkan
    public function currentStatusLog(): HasOne
    {
        return $this->hasOne(EquipmentStatusLog::class)
            ->orderByDesc('status_time')
            ->orderByDesc('id'); // Tambahan fallback untuk tie-breaking
    }

    // OPTIMIZED: Query untuk active breakdowns dengan index
    public function activeBreakdowns(): HasMany
    {
        return $this->hasMany(EquipmentBreakdown::class)
            ->whereIn('status', ['ongoing', 'pending_parts'])
            ->orderBy('start_time', 'desc');
    }

    // ===========================
    // PERFORMANCE OPTIMIZATION: Scopes dengan Index Support
    // ===========================

    public function scopeActive($query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeDumptrucks($query): Builder
    {
        // Menggunakan equipment_type_status_idx index
        return $query->where('type', 'dumptruck');
    }

    public function scopeExcavators($query): Builder
    {
        // Menggunakan equipment_type_status_idx index
        return $query->where('type', 'excavator');
    }

    public function scopeByType($query, $type): Builder
    {
        return $query->where('type', $type);
    }

    // NEW: Scope untuk equipment dengan breakdown
    public function scopeWithActiveBreakdowns($query): Builder
    {
        return $query->whereHas('activeBreakdowns');
    }

    // NEW: Scope untuk equipment yang available untuk kerja
    public function scopeAvailableForWork($query): Builder
    {
        return $query->whereDoesntHave('activeBreakdowns')
            ->where('status', 'active');
    }

    // ===========================
    // PERFORMANCE OPTIMIZATION: Cached Current Status
    // ===========================

    /**
     * Get current status dengan caching untuk performance
     * Cache invalidation dilakukan di Observer
     */
    public function getCurrentStatusAttribute(): string
    {
        $cacheKey = "equipment_status_{$this->id}";
        
        return Cache::remember($cacheKey, 300, function () { // 5 minutes cache
            // Priority 1: Check for ongoing/pending breakdowns dengan optimized query
            $hasActiveBreakdown = $this->activeBreakdowns()->exists();
            
            if ($hasActiveBreakdown) {
                return 'breakdown';
            }
            
            // Priority 2: Check latest status log dengan optimized query
            $latestStatusLog = $this->currentStatusLog;
            
            if ($latestStatusLog) {
                return $latestStatusLog->status;
            }
            
            // Default: idle status
            return 'idle';
        });
    }

    /**
     * Clear cache untuk current status
     */
    public function clearStatusCache(): void
    {
        Cache::forget("equipment_status_{$this->id}");
    }

    // ===========================
    // PERFORMANCE OPTIMIZATION: Optimized Business Logic
    // ===========================

    /**
     * Check if equipment can work - optimized query
     */
    public function canWork(): bool
    {
        $cacheKey = "equipment_can_work_{$this->id}";
        
        return Cache::remember($cacheKey, 300, function () {
            // Menggunakan optimized index untuk active breakdowns
            return !$this->activeBreakdowns()->exists();
        });
    }

    /**
     * Clear can work cache
     */
    public function clearCanWorkCache(): void
    {
        Cache::forget("equipment_can_work_{$this->id}");
    }

    /**
     * Get breakdown reason dengan caching
     */
    public function getBreakdownReasonAttribute(): ?string
    {
        if ($this->current_status !== 'breakdown') {
            return null;
        }

        $cacheKey = "equipment_breakdown_reason_{$this->id}";
        
        return Cache::remember($cacheKey, 300, function () {
            $activeBreakdown = $this->activeBreakdowns()->first();
            return $activeBreakdown ? $activeBreakdown->description : null;
        });
    }

    /**
     * Get active breakdown dengan caching
     */
    public function getActiveBreakdownAttribute(): ?EquipmentBreakdown
    {
        $cacheKey = "equipment_active_breakdown_{$this->id}";
        
        return Cache::remember($cacheKey, 300, function () {
            return $this->activeBreakdowns()->first();
        });
    }

    // ===========================
    // PERFORMANCE OPTIMIZATION: Bulk Status Updates
    // ===========================

    /**
     * Update status untuk multiple equipment sekaligus
     * Mengurangi individual queries
     */
    public static function bulkUpdateStatus(array $equipmentIds, string $status, array $logData = []): void
    {
        // Clear cache untuk semua equipment yang di-update
        foreach ($equipmentIds as $equipmentId) {
            Cache::forget("equipment_status_{$equipmentId}");
            Cache::forget("equipment_can_work_{$equipmentId}");
            Cache::forget("equipment_breakdown_reason_{$equipmentId}");
            Cache::forget("equipment_active_breakdown_{$equipmentId}");
        }

        // Bulk insert status logs
        $statusLogs = [];
        $now = now();
        
        foreach ($equipmentIds as $equipmentId) {
            $statusLogs[] = array_merge([
                'equipment_id' => $equipmentId,
                'status' => $status,
                'status_time' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ], $logData);
        }

        EquipmentStatusLog::insert($statusLogs);
    }

    // ===========================
    // UNCHANGED: Existing Accessors (sudah optimal)
    // ===========================

    public function getTypeNameAttribute(): string
    {
        return match($this->type) {
            'dumptruck' => 'Dump Truck',
            'excavator' => 'Excavator',
            default => 'Unknown'
        };
    }

    public function getCapacityUnitAttribute(): string
    {
        return $this->type === 'dumptruck' ? 'ton' : 'm³';
    }

    public function getIsWorkingAttribute(): bool
    {
        return $this->current_status === 'working';
    }

    public function getIsBreakdownAttribute(): bool
    {
        return $this->current_status === 'breakdown';
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->current_status) {
            'working' => 'success',
            'idle' => 'warning',
            'breakdown' => 'danger',
            'maintenance' => 'info',
            default => 'gray',
        };
    }

    // ===========================
    // PERFORMANCE OPTIMIZATION: Model Events
    // ===========================

    protected static function boot()
    {
        parent::boot();

        // Clear cache ketika equipment di-update
        static::updated(function ($equipment) {
            $equipment->clearStatusCache();
            $equipment->clearCanWorkCache();
        });

        // Clear cache ketika equipment di-delete
        static::deleted(function ($equipment) {
            $equipment->clearStatusCache();
            $equipment->clearCanWorkCache();
        });
    }
}
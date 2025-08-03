<?php
// ===========================
// app/Models/Equipment.php
// ===========================

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    // Relationships
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

    public function currentStatusLog(): HasOne
    {
        return $this->hasOne(EquipmentStatusLog::class)->latestOfMany('status_time');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeDumptrucks($query)
    {
        return $query->where('type', 'dumptruck');
    }

    public function scopeExcavators($query)
    {
        return $query->where('type', 'excavator');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    // Updated Smart Status Logic
    public function getCurrentStatusAttribute(): string
    {
        // Priority 1: Check for ongoing/pending breakdowns first
        $hasActiveBreakdown = $this->breakdowns()
            ->whereIn('status', ['ongoing', 'pending_parts'])
            ->exists();
        
        if ($hasActiveBreakdown) {
            return 'breakdown';
        }
        
        // Priority 2: Check latest status log
        $latestStatusLog = $this->currentStatusLog;
        
        if ($latestStatusLog) {
            return $latestStatusLog->status;
        }
        
        // Default: idle status
        return 'idle';
    }

    // Check if equipment can be set to working
    public function canWork(): bool
    {
        return !$this->breakdowns()
            ->whereIn('status', ['ongoing', 'pending_parts'])
            ->exists();
    }

    // Get breakdown reason if equipment is broken down
    public function getBreakdownReasonAttribute(): ?string
    {
        $activeBreakdown = $this->breakdowns()
            ->whereIn('status', ['ongoing', 'pending_parts'])
            ->latest('start_time')
            ->first();
        
        return $activeBreakdown ? $activeBreakdown->description : null;
    }

    // Get active breakdown details
    public function getActiveBreakdownAttribute(): ?EquipmentBreakdown
    {
        return $this->breakdowns()
            ->whereIn('status', ['ongoing', 'pending_parts'])
            ->latest('start_time')
            ->first();
    }

    // Accessors
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
}
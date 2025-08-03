<?php
// ===========================
// app/Models/EquipmentBreakdown.php
// ===========================

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentBreakdown extends Model
{
    use HasFactory;

    protected $fillable = [
        'equipment_id',
        'loading_session_id',
        'breakdown_type',
        'description',
        'start_time',
        'end_time',
        'duration_minutes',
        'severity',
        'repair_cost',
        'repaired_by',
        'status',
        'reported_by',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'duration_minutes' => 'integer',
            'repair_cost' => 'decimal:2',
        ];
    }

    // Relationships
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function loadingSession(): BelongsTo
    {
        return $this->belongsTo(LoadingSession::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    // Scopes
    public function scopeOngoing($query)
    {
        return $query->where('status', 'ongoing');
    }

    public function scopeRepaired($query)
    {
        return $query->where('status', 'repaired');
    }

    public function scopePendingParts($query)
    {
        return $query->where('status', 'pending_parts');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['ongoing', 'pending_parts']);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('start_time', today());
    }

    public function scopeBySeverity($query, $severity)
    {
        return $query->where('severity', $severity);
    }

    // Accessors
    public function getBreakdownTypeNameAttribute(): string
    {
        return match($this->breakdown_type) {
            'mechanical' => 'Mechanical',
            'electrical' => 'Electrical',
            'hydraulic' => 'Hydraulic',
            'engine' => 'Engine',
            'tire' => 'Tire',
            'other' => 'Other',
            default => 'Unknown'
        };
    }

    public function getSeverityNameAttribute(): string
    {
        return match($this->severity) {
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'critical' => 'Critical',
            default => 'Unknown'
        };
    }

    public function getSeverityColorAttribute(): string
    {
        return match($this->severity) {
            'low' => 'success',
            'medium' => 'warning',
            'high' => 'danger',
            'critical' => 'danger',
            default => 'secondary'
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'ongoing' => 'danger',
            'repaired' => 'success',
            'pending_parts' => 'warning',
            default => 'gray'
        };
    }

    public function getDurationHumanAttribute(): ?string
    {
        if ($this->duration_minutes) {
            $hours = intval($this->duration_minutes / 60);
            $minutes = $this->duration_minutes % 60;
            return $hours > 0 ? "{$hours}j {$minutes}m" : "{$minutes}m";
        }
        return null;
    }

    public function getIsActiveAttribute(): bool
    {
        return in_array($this->status, ['ongoing', 'pending_parts']);
    }

    // Updated Boot Method with Smart Equipment Status Management
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($breakdown) {
            // Set defaults for nullable fields
            if (is_null($breakdown->repair_cost)) {
                $breakdown->repair_cost = 0;
            }
        });

        static::created(function ($breakdown) {
            // Auto-create status log when breakdown is created
            $breakdown->equipment->statusLogs()->create([
                'status' => 'breakdown',
                'loading_session_id' => $breakdown->loading_session_id,
                'status_time' => $breakdown->start_time,
                'notes' => "Breakdown: {$breakdown->breakdown_type_name} - {$breakdown->description}",
            ]);
        });

        static::updated(function ($breakdown) {
            // When breakdown status changes to repaired
            if ($breakdown->status === 'repaired' && $breakdown->getOriginal('status') !== 'repaired') {
                // Check if there are other active breakdowns for this equipment
                $hasOtherBreakdowns = $breakdown->equipment->breakdowns()
                    ->where('id', '!=', $breakdown->id)
                    ->whereIn('status', ['ongoing', 'pending_parts'])
                    ->exists();
                
                if (!$hasOtherBreakdowns) {
                    // No other breakdowns, set equipment back to idle
                    $breakdown->equipment->statusLogs()->create([
                        'status' => 'idle',
                        'loading_session_id' => $breakdown->loading_session_id,
                        'status_time' => $breakdown->end_time ?? now(),
                        'notes' => "Repaired from {$breakdown->breakdown_type_name} breakdown. Equipment ready for operation.",
                    ]);
                }
            }

            // When breakdown status changes from repaired back to ongoing/pending
            if (in_array($breakdown->status, ['ongoing', 'pending_parts']) && $breakdown->getOriginal('status') === 'repaired') {
                // Set equipment back to breakdown status
                $breakdown->equipment->statusLogs()->create([
                    'status' => 'breakdown',
                    'loading_session_id' => $breakdown->loading_session_id,
                    'status_time' => now(),
                    'notes' => "Breakdown status reverted to {$breakdown->status}",
                ]);
            }
        });

        static::saving(function ($breakdown) {
            // Auto-calculate duration when both times are set
            if ($breakdown->start_time && $breakdown->end_time) {
                $breakdown->duration_minutes = $breakdown->start_time->diffInMinutes($breakdown->end_time);
            }
        });

        static::deleted(function ($breakdown) {
            // When breakdown is deleted, check if equipment should return to normal status
            $hasOtherBreakdowns = $breakdown->equipment->breakdowns()
                ->where('id', '!=', $breakdown->id)
                ->whereIn('status', ['ongoing', 'pending_parts'])
                ->exists();
            
            if (!$hasOtherBreakdowns) {
                // No other breakdowns, set equipment to idle
                $breakdown->equipment->statusLogs()->create([
                    'status' => 'idle',
                    'status_time' => now(),
                    'notes' => "Breakdown record deleted. Equipment status reset to idle.",
                ]);
            }
        });
    }
}
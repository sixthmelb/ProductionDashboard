<?php

// ===========================
// 4. app/Models/LoadingSession.php
// ===========================

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoadingSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_code',
        'stacking_area_id',
        'user_id',
        'shift',
        'start_time',
        'end_time',
        'status',
        'total_buckets',
        'total_tonnage',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'total_buckets' => 'integer',
            'total_tonnage' => 'decimal:2',
        ];
    }

    // Relationships
    public function stackingArea(): BelongsTo
    {
        return $this->belongsTo(StackingArea::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bucketActivities(): HasMany
    {
        return $this->hasMany(BucketActivity::class);
    }

    public function breakdowns(): HasMany
    {
        return $this->hasMany(EquipmentBreakdown::class);
    }

    public function equipmentStatusLogs(): HasMany
    {
        return $this->hasMany(EquipmentStatusLog::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByShift($query, $shift)
    {
        return $query->where('shift', $shift);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('start_time', today());
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('start_time', now()->month)
                    ->whereYear('start_time', now()->year);
    }

    // Accessors & Mutators
    public function getDurationAttribute(): ?int
    {
        if ($this->start_time && $this->end_time) {
            return $this->start_time->diffInMinutes($this->end_time);
        } elseif ($this->start_time && $this->status === 'active') {
            return $this->start_time->diffInMinutes(now());
        }
        return null;
    }

    public function getDurationHumanAttribute(): ?string
    {
        if ($duration = $this->duration) {
            $hours = intval($duration / 60);
            $minutes = $duration % 60;
            return "{$hours} jam {$minutes} menit";
        }
        return null;
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    // Auto-generate session code
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($session) {
            if (empty($session->session_code)) {
                $session->session_code = 'LS-' . now()->format('Y-m-d') . '-' . str_pad(
                    static::whereDate('start_time', today())->count() + 1,
                    3,
                    '0',
                    STR_PAD_LEFT
                );
            }
        });
    }
}

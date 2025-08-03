<?php

// ===========================
// 5. app/Models/BucketActivity.php
// ===========================

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BucketActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'loading_session_id',
        'excavator_id',
        'dumptruck_id',
        'bucket_count',
        'estimated_tonnage',
        'activity_time',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'bucket_count' => 'integer',
            'estimated_tonnage' => 'decimal:2',
            'activity_time' => 'datetime',
        ];
    }

    // Relationships
    public function loadingSession(): BelongsTo
    {
        return $this->belongsTo(LoadingSession::class);
    }

    public function excavator(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'excavator_id');
    }

    public function dumptruck(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'dumptruck_id');
    }

    // Scopes
    public function scopeToday($query)
    {
        return $query->whereDate('activity_time', today());
    }

    public function scopeBySession($query, $sessionId)
    {
        return $query->where('loading_session_id', $sessionId);
    }

    // Auto-update session totals
    protected static function boot()
    {
        parent::boot();

        static::created(function ($activity) {
            $activity->updateSessionTotals();
        });

        static::updated(function ($activity) {
            $activity->updateSessionTotals();
        });

        static::deleted(function ($activity) {
            $activity->updateSessionTotals();
        });
    }

    public function updateSessionTotals(): void
    {
        $session = $this->loadingSession;
        $session->update([
            'total_buckets' => $session->bucketActivities()->sum('bucket_count'),
            'total_tonnage' => $session->bucketActivities()->sum('estimated_tonnage'),
        ]);
    }
}
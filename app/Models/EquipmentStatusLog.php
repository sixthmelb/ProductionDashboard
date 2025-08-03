<?php

// ===========================
// 7. app/Models/EquipmentStatusLog.php
// ===========================

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentStatusLog extends Model
{
    use HasFactory;
        // Tambahkan ini untuk override nama tabel
    protected $table = 'equipment_status_log';

    protected $fillable = [
        'equipment_id',
        'status',
        'loading_session_id',
        'location',
        'operator_name',
        'fuel_level',
        'engine_hours',
        'status_time',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'fuel_level' => 'decimal:1',
            'engine_hours' => 'decimal:1',
            'status_time' => 'datetime',
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

    // Scopes
    public function scopeLatest($query)
    {
        return $query->orderBy('status_time', 'desc');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('status_time', today());
    }

    // Accessors
    public function getStatusNameAttribute(): string
    {
        return match($this->status) {
            'idle' => 'Idle',
            'working' => 'Working',
            'breakdown' => 'Breakdown',
            'maintenance' => 'Maintenance',
            default => 'Unknown'
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'idle' => 'warning',
            'working' => 'success',
            'breakdown' => 'danger',
            'maintenance' => 'info',
            default => 'secondary'
        };
    }

    public function getFuelLevelColorAttribute(): string
    {
        if (!$this->fuel_level) return 'secondary';
        
        return match(true) {
            $this->fuel_level >= 70 => 'success',
            $this->fuel_level >= 30 => 'warning',
            default => 'danger'
        };
    }
}
<?php

// ===========================
// 3. app/Models/StackingArea.php
// ===========================

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StackingArea extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'location',
        'latitude',
        'longitude',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'is_active' => 'boolean',
        ];
    }

    // Relationships
    public function loadingSessions(): HasMany
    {
        return $this->hasMany(LoadingSession::class);
    }

    public function activeLoadingSessions(): HasMany
    {
        return $this->hasMany(LoadingSession::class)->where('status', 'active');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Accessors
    public function getCoordinatesAttribute(): ?string
    {
        if ($this->latitude && $this->longitude) {
            return "{$this->latitude}, {$this->longitude}";
        }
        return null;
    }

    public function getHasActiveSessionAttribute(): bool
    {
        return $this->activeLoadingSessions()->exists();
    }
}

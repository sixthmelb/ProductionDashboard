<?php
// ===========================
// app/Models/User.php - Updated with Shield
// ===========================

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use BezhanSalleh\FilamentShield\Traits\HasPanelShield;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasRoles, HasPanelShield;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'shift',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // Filament access control with Shield
    public function canAccessPanel(Panel $panel): bool
    {
        // Check if user is active and has any role
        return $this->is_active && $this->hasAnyRole(['superadmin', 'mcr', 'manager']);
    }

    // Relationships
    public function loadingSessions(): HasMany
    {
        return $this->hasMany(LoadingSession::class);
    }

    public function reportedBreakdowns(): HasMany
    {
        return $this->hasMany(EquipmentBreakdown::class, 'reported_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    public function scopeByShift($query, $shift)
    {
        return $query->where('shift', $shift);
    }

    // Accessors
    public function getRoleNameAttribute(): string
    {
        return match($this->role) {
            'superadmin' => 'Super Admin',
            'mcr' => 'MCR Operator',
            'manager' => 'Production Manager',
            default => 'Unknown'
        };
    }

    // Helper methods for role checking
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('superadmin');
    }

    public function isMcr(): bool
    {
        return $this->hasRole('mcr');
    }

    public function isManager(): bool
    {
        return $this->hasRole('manager');
    }
}
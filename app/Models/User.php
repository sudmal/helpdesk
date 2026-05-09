<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Territory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasPushSubscriptions;

    protected $fillable = [
        'role_id', 'name', 'login', 'email', 'phone', 'password',
        'telegram_chat_id', 'max_chat_id',
        'notify_telegram', 'notify_email', 'notify_max',
        'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'notify_telegram' => 'boolean',
        'notify_email'    => 'boolean',
        'notify_max'      => 'boolean',
        'is_active'       => 'boolean',
    ];

    // === Relations ===
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function brigades(): BelongsToMany
    {
        return $this->belongsToMany(Brigade::class, 'brigade_user');
    }

    public function territories(): BelongsToMany
    {
        return $this->belongsToMany(Territory::class, 'user_territory');
    }

    public function createdTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'created_by');
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    // === Permission helpers ===
    public function hasPermission(string $permission): bool
    {
        $permissions = $this->role->permissions ?? [];

        if (in_array('*', $permissions)) {
            return true;
        }

        foreach ($permissions as $p) {
            if ($p === $permission) return true;
            // wildcard: tickets.* matches tickets.view, tickets.close etc.
            if (str_ends_with($p, '.*')) {
                $prefix = rtrim($p, '.*');
                if (str_starts_with($permission, $prefix . '.')) return true;
            }
        }
        return false;
    }

    public function isAdmin(): bool       { return $this->role->slug === 'admin'; }
    public function isHeadSupport(): bool { return $this->role->slug === 'head_support'; }
    public function isOperator(): bool    { return $this->role->slug === 'operator'; }
    public function isForeman(): bool     { return $this->role->slug === 'foreman'; }
    public function isTechnician(): bool  { return $this->role->slug === 'technician'; }
    public function canManageSettings(): bool
    {
        return $this->isAdmin() || $this->isHeadSupport();
    }
}

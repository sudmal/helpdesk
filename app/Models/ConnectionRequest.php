<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConnectionRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'phone', 'address_string', 'description',
        'status', 'scheduled_at', 'notes', 'act_number',
        'assigned_to', 'created_by', 'territory_id', 'brigade_id', 'service_type_id', 'needs_callback',
        'feasibility', 'feasibility_comment', 'feasibility_by', 'feasibility_at',
    ];

    protected $casts = [
        'scheduled_at'   => 'datetime',
        'needs_callback' => 'boolean',
        'feasibility_at' => 'datetime',
    ];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function feasibilityByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'feasibility_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class);
    }

    public function brigade(): BelongsTo
    {
        return $this->belongsTo(Brigade::class);
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function act(): HasOne
    {
        return $this->hasOne(Act::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(ConnectionRequestMaterial::class);
    }

    public function logs(): HasMany
    {
        // latest() сортирует только по created_at (секундная точность) — две
        // записи лога в одну секунду иначе оказываются в непредсказуемом
        // порядке; id как тай-брейкер даёт стабильный порядок.
        return $this->hasMany(ConnectionRequestLog::class)->latest()->orderByDesc('id');
    }
}

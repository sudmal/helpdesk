<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConnectionRequest extends Model
{
    protected $fillable = [
        'name', 'phone', 'address_string', 'description',
        'status', 'scheduled_at', 'notes', 'act_number',
        'assigned_to', 'created_by', 'territory_id', 'needs_callback',
    ];

    protected $casts = [
        'scheduled_at'   => 'datetime',
        'needs_callback' => 'boolean',
    ];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(ConnectionRequestMaterial::class);
    }
}

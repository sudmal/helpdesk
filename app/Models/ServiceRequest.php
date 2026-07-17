<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceRequest extends Model
{
    protected $fillable = [
        'name', 'phone', 'address_string', 'service_name',
        'description', 'status', 'admin_comment',
        'created_by', 'processed_by', 'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function logs(): HasMany
    {
        // latest() сортирует только по created_at (секундная точность) — id
        // как тай-брейкер даёт стабильный порядок при записях в одну секунду.
        return $this->hasMany(ServiceRequestLog::class)->latest()->orderByDesc('id');
    }
}

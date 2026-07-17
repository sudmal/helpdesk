<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequestLog extends Model
{
    protected $fillable = ['service_request_id', 'user_id', 'action', 'notes', 'meta'];

    protected $casts = ['meta' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferralSource extends Model
{
    protected $fillable = ['key', 'label', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean'];

    // Связь по "key" (не по id) -- значение, которое реально хранится в
    // connection_requests.referral_source, см. миграцию 2026_11_17_000002.
    public function connectionRequests(): HasMany
    {
        return $this->hasMany(ConnectionRequest::class, 'referral_source', 'key');
    }
}

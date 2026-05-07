<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceType extends Model
{
    protected $fillable = ['name', 'color', 'is_active', 'sort_order'];
    protected $casts    = ['is_active' => 'boolean'];

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'service_type_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}

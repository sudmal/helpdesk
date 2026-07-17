<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    protected $fillable = ['name', 'price', 'is_active', 'sort_order'];

    protected $casts = [
        'is_active' => 'boolean',
        'price'     => 'float',
    ];

    public function acts(): HasMany
    {
        return $this->hasMany(Act::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}

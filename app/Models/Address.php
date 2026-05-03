<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Address extends Model
{
    protected $fillable = [
        'territory_id', 'city', 'street', 'building', 'apartment',
        'entrance', 'floor', 'subscriber_name', 'phone', 'contract_no',
        'lanbilling_id', 'lanbilling_data', 'notes',
    ];

    protected $casts = ['lanbilling_data' => 'array'];

    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class)->latest();
    }

    /** Человекочитаемый адрес */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->city,
            $this->street,
            $this->building,
            $this->apartment ? 'кв. ' . $this->apartment : null,
        ]);
        return implode(', ', $parts);
    }

    /** Скоуп поиска по всем текстовым полям */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('street', 'like', "%{$term}%")
              ->orWhere('building', 'like', "%{$term}%")
              ->orWhere('city', 'like', "%{$term}%")
              ->orWhere('subscriber_name', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%")
              ->orWhere('contract_no', 'like', "%{$term}%");
        });
    }
}

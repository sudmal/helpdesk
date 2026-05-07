<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Address extends Model
{
    protected $appends = ['full_address'];

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
    /**
     * Умный поиск: "Малин 15 22" найдёт "ул. Малиновского д. 15 кв. 22"
     * Каждое слово запроса ищется во всех полях (AND между словами, LIKE с обеих сторон)
     */
    public function scopeSearch($query, string $term)
    {
        $words = array_values(array_filter(explode(' ', trim($term))));
        if (empty($words)) return $query;

        foreach ($words as $word) {
            $like = "%{$word}%";
            $query->where(function ($q) use ($word, $like) {
                $q->where('street',           'like', $like)
                  ->orWhere('city',            'like', $like)
                  ->orWhere('subscriber_name', 'like', $like)
                  ->orWhere('phone',           'like', $like)
                  ->orWhere('contract_no',     'like', $like);
            });
        }

        return $query;
    }
}

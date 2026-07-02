<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Territory extends Model
{
    protected $fillable = ['name', 'description', 'sort_order'];

    public function brigades(): BelongsToMany
    {
        return $this->belongsToMany(Brigade::class, 'brigade_territory');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }
}

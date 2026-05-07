<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    protected $fillable = ['name', 'unit', 'price', 'is_active', 'sort_order'];
    protected $casts    = ['price' => 'float', 'is_active' => 'boolean'];

    public function ticketMaterials(): HasMany
    {
        return $this->hasMany(TicketMaterial::class);
    }

    public function scopeActive($q) { return $q->where('is_active', true); }
}

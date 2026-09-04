<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    protected $fillable = ['code', 'name', 'unit', 'price', 'is_active', 'sort_order'];
    protected $casts    = ['price' => 'float', 'is_active' => 'boolean'];

    // Код артикула в БД необязательный (nullable). Отдаём '' вместо null,
    // чтобы не ронять строгие JSON-парсеры клиентов (Android: code: String):
    // при code=null весь справочник GET /api/materials не парсился и выпадашка
    // выбора материала при закрытии заявки оставалась пустой (баг 2026-09-04).
    public function getCodeAttribute($value): string
    {
        return $value ?? '';
    }

    public function ticketMaterials(): HasMany
    {
        return $this->hasMany(TicketMaterial::class);
    }

    public function actMaterials(): HasMany
    {
        return $this->hasMany(ActMaterial::class);
    }

    public function scopeActive($q) { return $q->where('is_active', true); }
}

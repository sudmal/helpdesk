<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = ['name', 'slug', 'permissions'];
    protected $casts = ['permissions' => 'array'];
    public function users(): HasMany { return $this->hasMany(User::class); }

    /**
     * Та же логика, что раньше была только в User::hasPermission() (включая
     * wildcard "module.*") — вынесена сюда (2026-10-16), чтобы её можно было
     * проверять и без конкретного пользователя: по РОЛИ напрямую. Нужна для
     * гейта отправки актов в архив (ActController::recomputeAfterProcessing) —
     * требовать визу отдела, только если у его роли право на обработку актов
     * вообще включено; живой кейс — роль "Логистика" лишилась
     * acts.process_logistics, и 409 актов за сентябрь 2026 зависли в
     * processing, ожидая сторону, которая структурно не может их обработать.
     */
    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions ?? [];

        if (in_array('*', $permissions)) {
            return true;
        }

        foreach ($permissions as $p) {
            if ($p === $permission) return true;
            if (str_ends_with($p, '.*')) {
                $prefix = rtrim($p, '.*');
                if (str_starts_with($permission, $prefix . '.')) return true;
            }
        }
        return false;
    }
}

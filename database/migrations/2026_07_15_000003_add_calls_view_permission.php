<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

// Раздел "Звонки" раньше был виден вообще всем без проверки прав (NavItem без
// v-if, роут без гейта). Добавляем calls.view, чтобы админ мог показывать/
// скрывать раздел по ролям через Настройки. Чтобы никто не потерял доступ,
// который был у него по умолчанию, выдаём calls.view сразу всем ролям, кроме
// admin/head_support — у них уже есть '*'.
return new class extends Migration
{
    public function up(): void
    {
        foreach (['operator', 'foreman', 'technician', 'peo', 'logistics', 'subscriber_dept'] as $slug) {
            $this->addPermissions($slug, ['calls.view']);
        }
    }

    public function down(): void
    {
        foreach (['operator', 'foreman', 'technician', 'peo', 'logistics', 'subscriber_dept'] as $slug) {
            $this->removePermissions($slug, ['calls.view']);
        }
    }

    private function addPermissions(string $slug, array $perms): void
    {
        $role = Role::where('slug', $slug)->first();
        if (!$role) return;

        $role->permissions = array_values(array_unique(array_merge($role->permissions ?? [], $perms)));
        $role->save();
    }

    private function removePermissions(string $slug, array $perms): void
    {
        $role = Role::where('slug', $slug)->first();
        if (!$role) return;

        $role->permissions = array_values(array_diff($role->permissions ?? [], $perms));
        $role->save();
    }
};

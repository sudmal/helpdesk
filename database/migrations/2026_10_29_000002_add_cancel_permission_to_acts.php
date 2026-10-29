<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

// Право на отмену акта (acts.cancel) — admin/head_support/operator и так
// всегда могут отменить (оверсайт, см. ActPolicy::cancel()), это право для
// остальных ролей. Абонотдел получает его по умолчанию -- именно они решают
// вопрос с оплатой/отказом абонента.
return new class extends Migration
{
    public function up(): void
    {
        $this->addPermissions('subscriber_dept', ['acts.cancel']);
    }

    public function down(): void
    {
        $this->removePermissions('subscriber_dept', ['acts.cancel']);
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

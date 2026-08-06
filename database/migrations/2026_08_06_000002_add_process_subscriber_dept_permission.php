<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

// Право на новую "визу" Абонотдела (subscriber_dept_processed_*), отдельную
// от acts.complete (отправка в архив) — см. память project-acts-feature.
return new class extends Migration
{
    public function up(): void
    {
        $this->addPermissions('subscriber_dept', ['acts.process_subscriber_dept']);
    }

    public function down(): void
    {
        $this->removePermissions('subscriber_dept', ['acts.process_subscriber_dept']);
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

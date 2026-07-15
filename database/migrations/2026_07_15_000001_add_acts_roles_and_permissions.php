<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

// Роли для workflow согласования Актов (см. память project-acts-feature):
// ПЭО и Логистика обрабатывают акт независимо после утверждения бригадиром,
// Абонотдел закрывает акт, когда обработаны все требуемые для типа акта стороны.
return new class extends Migration
{
    public function up(): void
    {
        Role::firstOrCreate(['slug' => 'peo'], [
            'name' => 'ПЭО',
            'permissions' => ['tickets.view', 'materials.view', 'reports.view', 'acts.view', 'acts.process_peo'],
        ]);

        Role::firstOrCreate(['slug' => 'logistics'], [
            'name' => 'Логистика',
            'permissions' => ['tickets.view', 'materials.view', 'reports.view', 'acts.view', 'acts.process_logistics'],
        ]);

        Role::firstOrCreate(['slug' => 'subscriber_dept'], [
            'name' => 'Абонотдел',
            'permissions' => ['tickets.view', 'materials.view', 'reports.view', 'acts.view', 'acts.complete'],
        ]);

        $this->addPermissions('foreman', ['acts.view', 'acts.foreman_review']);
        $this->addPermissions('technician', ['acts.view']);
    }

    public function down(): void
    {
        Role::whereIn('slug', ['peo', 'logistics', 'subscriber_dept'])->delete();

        $this->removePermissions('foreman', ['acts.view', 'acts.foreman_review']);
        $this->removePermissions('technician', ['acts.view']);
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

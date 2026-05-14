<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $foreman = DB::table('roles')->where('slug', 'foreman')->first();
        if (!$foreman) return;
        $perms = json_decode($foreman->permissions, true) ?? [];
        foreach (['tickets.create', 'materials.view'] as $p) {
            if (!in_array($p, $perms)) $perms[] = $p;
        }
        DB::table('roles')->where('slug', 'foreman')->update(['permissions' => json_encode($perms)]);
    }

    public function down(): void
    {
        $foreman = DB::table('roles')->where('slug', 'foreman')->first();
        if (!$foreman) return;
        $perms = array_values(array_diff(
            json_decode($foreman->permissions, true) ?? [],
            ['tickets.create', 'materials.view']
        ));
        DB::table('roles')->where('slug', 'foreman')->update(['permissions' => json_encode($perms)]);
    }
};
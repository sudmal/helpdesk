<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'is_superadmin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_superadmin')->default(false)->after('role_id');
            });
        }

        // Единственная учётка с этим флагом сейчас (2026-10-16, прямая просьба
        // пользователя) -- роль "Администратор" есть у нескольких сотрудников,
        // но массовая отправка актов в архив (ActController::bulkArchive) --
        // явно только для этой учётки, не для всех admin. Флаг осознанно НЕ
        // выставляется через обычный UI Настроек -- включать/выключать для
        // кого-то ещё нужно отдельной миграцией/скриптом, не случайным кликом.
        DB::table('users')->where('email', 'admin@sputnik-tele.com')->update(['is_superadmin' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_superadmin')) {
                $table->dropColumn('is_superadmin');
            }
        });
    }
};

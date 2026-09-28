<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// См. add_queue_key_to_calls.php — тот же смысл, для отчётов по сменам.
// Уникальный ключ был (shift_date, shift_definition_id) без очереди — как
// только Абонотдел начнёт получать свои собственные отчёты по сменам, это
// столкнётся (одна и та же дата+смена для двух разных очередей). Расширяем
// ключ явным queue_key вместо привязки к длинному GUID очереди Asterisk.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_reports', function (Blueprint $table) {
            $table->string('queue_key', 30)->default('techsupport')->after('shift_definition_id');
        });

        Schema::table('shift_reports', function (Blueprint $table) {
            $table->dropUnique(['shift_date', 'shift_definition_id']);
            $table->unique(['shift_date', 'shift_definition_id', 'queue_key']);
        });
    }

    public function down(): void
    {
        Schema::table('shift_reports', function (Blueprint $table) {
            $table->dropUnique(['shift_date', 'shift_definition_id', 'queue_key']);
            $table->unique(['shift_date', 'shift_definition_id']);
            $table->dropColumn('queue_key');
        });
    }
};

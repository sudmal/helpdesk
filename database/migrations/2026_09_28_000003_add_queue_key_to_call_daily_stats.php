<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Отчёт "Обработка звонков" делится на Техподдержку/Абонотдел (2026-09-28,
// см. project_calls_abonotdel_queue) — та же история, что уже была с calls/
// shift_reports: без queue_key вся статистика по часам смешивала бы обе
// очереди в одну кучу. Первичный ключ был (stat_date, hour) — расширяем до
// (stat_date, hour, queue_key), иначе AggregateCallStats не сможет хранить
// отдельную строку на каждую очередь для одного и того же часа.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_daily_stats', function (Blueprint $table) {
            $table->dropPrimary(['stat_date', 'hour']);
        });
        Schema::table('call_daily_stats', function (Blueprint $table) {
            $table->string('queue_key', 30)->default('techsupport')->after('hour');
        });
        DB::statement('UPDATE call_daily_stats SET queue_key = "techsupport"');
        Schema::table('call_daily_stats', function (Blueprint $table) {
            $table->primary(['stat_date', 'hour', 'queue_key']);
        });
    }

    public function down(): void
    {
        Schema::table('call_daily_stats', function (Blueprint $table) {
            $table->dropPrimary(['stat_date', 'hour', 'queue_key']);
            $table->dropColumn('queue_key');
        });
        Schema::table('call_daily_stats', function (Blueprint $table) {
            $table->primary(['stat_date', 'hour']);
        });
    }
};

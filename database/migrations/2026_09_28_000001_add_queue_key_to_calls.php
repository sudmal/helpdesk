<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Готовим таблицу calls к появлению второй очереди (Абонотдел, 2026-09-28).
// Раньше вся система работала в расчёте на ровно одну очередь, поэтому
// queue_status/operator_ext ничего не говорят, к какой очереди звонок
// относится — operator_ext для этого не годится: у ПРОПУЩЕННЫХ звонков он
// пустой (звонок никому не назначился), так что фильтровать отчёты по
// сменам по добавочным потерял бы все пропущенные. queue_key пишется прямо
// в момент, когда PbxController::trackCallEvents() узнаёт очередь из POST
// от queue_monitor.sh — по короткому ключу из config/pbx_queues.php.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->string('queue_key', 30)->nullable()->after('operator_ext')->index();
        });

        // Вся существующая история — это техподдержка (Абонотдел на момент
        // миграции ещё не опрашивался и статистику не слал).
        DB::table('calls')->whereNotNull('queue_status')->update(['queue_key' => 'techsupport']);
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropColumn('queue_key');
        });
    }
};

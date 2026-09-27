<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Статус SIP-транка на момент замера очереди (queue_monitor.sh шлёт его в
// каждом опросе) — нужен для полоски статуса провайдера над графиком
// звонков (Calls/Index.vue, вкладка "Очередь АТС"). trunk_status — как
// пришло из pjsip ('Avail'/'Unavail'/'Unreachable'/...), null — замер до
// появления этого поля (старая история, полоска для них не рисуется).
// trunk_loss_pct — худшие потери (%) среди шлюза/SIP-сервера/коммутатора
// Феникс за эту минуту (см. trunk_probe.sh на MikoPBX), null если проба
// недоступна.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_stats', function (Blueprint $table) {
            $table->string('trunk_status', 20)->nullable()->after('total_members');
            $table->float('trunk_loss_pct')->nullable()->after('trunk_status');
        });
    }

    public function down(): void
    {
        Schema::table('queue_stats', function (Blueprint $table) {
            $table->dropColumn(['trunk_status', 'trunk_loss_pct']);
        });
    }
};

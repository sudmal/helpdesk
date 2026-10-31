<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            // PbxController::attachDndStatus()/attachDndCounts() (2026-10-31) --
            // несколько запросов фильтруют calls по operator_ext (+queue_status,
            // +called_at), а индекса по этой колонке не было вообще (только
            // phone/address_id/called_at/queue_status/queue_key) -- полный скан
            // 49к строк на каждое открытие вкладки "Очередь АТС" (70-105мс на
            // запрос, несколько таких запросов за один вызов).
            $table->index(['operator_ext', 'queue_status', 'called_at']);
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropIndex(['operator_ext', 'queue_status', 'called_at']);
        });
    }
};

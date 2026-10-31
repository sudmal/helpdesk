<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dnd_logs', function (Blueprint $table) {
            // Почти все запросы PbxController::queueHistory()/attachDndCounts()
            // фильтруют по created_at (с опциональным state) -- таблицы без
            // индекса по created_at (только extension) это был полный скан
            // (найдено 2026-10-31: 41к строк, ~150-250мс на запрос, 3 таких
            // запроса на каждое открытие вкладки "Очередь АТС"). (state,
            // created_at) покрывает и фильтры по конкретному state+диапазону,
            // и общий диапазон по нескольким state (MySQL просто сканирует
            // несколько поддиапазонов индекса).
            $table->index(['state', 'created_at']);
            // Дополнительно для точечных per-extension exists()-проверок в
            // attachDndCounts() (answeredAfter/ringingAfter/unavailableAfter) --
            // расширяет уже существующий одиночный индекс по extension.
            $table->index(['extension', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('dnd_logs', function (Blueprint $table) {
            $table->dropIndex(['state', 'created_at']);
            $table->dropIndex(['extension', 'created_at']);
        });
    }
};

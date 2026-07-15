<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Отслеживание правок бригадира в составе акта (только бригадир может
// добавлять/удалять/менять позиции, пока акт в pending_foreman) — монтажник
// должен увидеть, что именно изменилось, и подтвердить это через "Принято".
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acts', function (Blueprint $table) {
            // non-null = есть непрочитанные монтажником правки бригадира
            $table->timestamp('materials_changed_at')->nullable()->after('foreman_return_comment');
        });

        Schema::table('act_history', function (Blueprint $table) {
            // null = ещё не подтверждено монтажником (эта запись — то самое "красным" изменение)
            $table->timestamp('acknowledged_at')->nullable()->after('new_value');
            // привязка к конкретной позиции материала — для точечной подсветки строки
            $table->unsignedBigInteger('related_material_id')->nullable()->after('acknowledged_at');
        });
    }

    public function down(): void
    {
        Schema::table('acts', function (Blueprint $table) {
            $table->dropColumn('materials_changed_at');
        });

        Schema::table('act_history', function (Blueprint $table) {
            $table->dropColumn(['acknowledged_at', 'related_material_id']);
        });
    }
};

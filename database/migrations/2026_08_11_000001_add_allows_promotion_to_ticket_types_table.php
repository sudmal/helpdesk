<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->boolean('allows_promotion')->default(false)->after('is_active');
        });

        // Акции по подключениям и переключению на ПОН — те же две заявки, для
        // которых пользователь просил включить механизм акций с самого начала.
        // Остальные типы (ремонт и т.п.) остаются выключены по умолчанию.
        \DB::table('ticket_types')
            ->whereIn('name', ['Подключение', 'Перекл. на PON'])
            ->update(['allows_promotion' => true]);
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropColumn('allows_promotion');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connection_requests', function (Blueprint $table) {
            // Вид заявки: новое подключение или переключение существующего
            // абонента на PON. Для 'switch' шаг "возможно/невозможно" не
            // обязателен перед назначением даты (абонент уже подключён).
            $table->enum('kind', ['connection', 'switch'])
                ->default('connection')
                ->after('service_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('connection_requests', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            // AddressController::hierarchy() при заданном brigade_id добавляет
            // whereIn('territory_id', ...) к каждому уровню city/street/building.
            // Без индекса, ведущего с territory_id, MariaDB не могла сузить поиск
            // (EXPLAIN показывал полный скан по addr_city_street с фильтрацией
            // territory_id пост-фактум, ~60k строк, 200-320мс на запрос) -- из-за
            // четырёх последовательных запросов при открытии заявки на редактирование
            // (город -> улица -> дом -> квартира) список домов грузился с заметной
            // задержкой.
            $table->index(['territory_id', 'city', 'street', 'building'], 'addr_territory_city_street_building');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropIndex('addr_territory_city_street_building');
        });
    }
};

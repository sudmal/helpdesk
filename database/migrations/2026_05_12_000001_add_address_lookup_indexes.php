<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            // Для иерархического проводника: city -> street -> building
            $table->index(['city', 'street'], 'addr_city_street');
            $table->index(['city', 'street', 'building'], 'addr_city_street_building');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropIndex('addr_city_street');
            $table->dropIndex('addr_city_street_building');
        });
    }
};
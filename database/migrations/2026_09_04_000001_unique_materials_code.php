<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Код артикула теперь обязателен и уникален (см. MaterialController).
        // NULL допускается (MySQL UNIQUE пропускает несколько NULL) — только
        // для ретро-деактивированных материалов без валидного кода.
        Schema::table('materials', function (Blueprint $table) {
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropUnique(['code']);
        });
    }
};

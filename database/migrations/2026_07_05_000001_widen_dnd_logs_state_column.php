<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // varchar(10) не вмещал новое состояние 'unavailable' (11 символов) --
        // тихо обрезалось до 'unavailabl' и не совпадало со сверкой в коде.
        Schema::table('dnd_logs', function (Blueprint $table) {
            $table->string('state', 20)->change();
        });
    }

    public function down(): void
    {
        Schema::table('dnd_logs', function (Blueprint $table) {
            $table->string('state', 10)->change();
        });
    }
};

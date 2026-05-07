<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('system_settings')) return;

        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, integer, boolean, json
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Дефолтные настройки
        $defaults = [
            ['key' => 'work_hours_start',       'value' => '09:00', 'type' => 'string',  'description' => 'Начало рабочего дня'],
            ['key' => 'work_hours_end',         'value' => '17:00', 'type' => 'string',  'description' => 'Конец рабочего дня'],
            ['key' => 'schedule_step_minutes',  'value' => '30',    'type' => 'integer', 'description' => 'Шаг времени при записи (мин)'],
            ['key' => 'attachment_ttl_days',    'value' => '365',   'type' => 'integer', 'description' => 'Хранить вложения (дней)'],
            ['key' => 'work_days',              'value' => '1,2,3,4,5', 'type' => 'string', 'description' => 'Рабочие дни (1=Пн, 7=Вс)'],
        ];

        foreach ($defaults as $setting) {
            DB::table('system_settings')->insertOrIgnore($setting);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};

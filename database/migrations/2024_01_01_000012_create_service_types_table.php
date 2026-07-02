<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('service_types')) return;

        Schema::create('service_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');          // Интернет, КТВ
            $table->string('color', 7)->default('#3b82f6');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Добавляем service_type_id в tickets
        if (!Schema::hasColumn('tickets', 'service_type_id')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->foreignId('service_type_id')->nullable()
                    ->after('type_id')
                    ->constrained('service_types')->nullOnDelete();
            });
        }

        // Дефолтные участки
        DB::table('service_types')->insert([
            ['name' => 'Интернет', 'color' => '#3b82f6', 'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'КТВ',      'color' => '#8b5cf6', 'is_active' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['service_type_id']);
            $table->dropColumn('service_type_id');
        });
        Schema::dropIfExists('service_types');
    }
};

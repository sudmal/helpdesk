<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::table('territories', function (Blueprint $table) {
            $table->integer('sort_order')->default(0)->after('name');
        });
        // Проставляем начальный порядок по id
        DB::statement('UPDATE territories SET sort_order = id');
    }
    public function down(): void {
        Schema::table('territories', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};

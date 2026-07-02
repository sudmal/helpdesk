<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brigades', function (Blueprint $table) {
            $table->unsignedTinyInteger('min_workers')->default(2)->after('foreman_id');
        });
    }

    public function down(): void
    {
        Schema::table('brigades', function (Blueprint $table) {
            $table->dropColumn('min_workers');
        });
    }
};
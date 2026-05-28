<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brigade_user', function (Blueprint $table) {
            $table->boolean('exclude_from_schedule')->default(false)->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('brigade_user', function (Blueprint $table) {
            $table->dropColumn('exclude_from_schedule');
        });
    }
};
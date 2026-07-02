<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('brigades')) {
            Schema::create('brigades', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->foreignId('foreman_id')->nullable()
                    ->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('brigade_territory')) {
            Schema::create('brigade_territory', function (Blueprint $table) {
                $table->foreignId('brigade_id')->constrained('brigades')->cascadeOnDelete();
                $table->foreignId('territory_id')->constrained('territories')->cascadeOnDelete();
                $table->primary(['brigade_id', 'territory_id']);
            });
        }

        if (!Schema::hasTable('brigade_user')) {
            Schema::create('brigade_user', function (Blueprint $table) {
                $table->foreignId('brigade_id')->constrained('brigades')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->primary(['brigade_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('brigade_user');
        Schema::dropIfExists('brigade_territory');
        Schema::dropIfExists('brigades');
    }
};

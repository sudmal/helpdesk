<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('schedule_holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('brigade_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brigade_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->enum('status', ['work', 'off', 'requested'])->default('work');
            $table->timestamps();
            $table->unique(['brigade_id', 'user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brigade_schedules');
        Schema::dropIfExists('schedule_holidays');
    }
};

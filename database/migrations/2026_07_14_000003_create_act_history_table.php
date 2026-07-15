<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('act_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('act_id')->constrained('acts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('field')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamps();
            $table->index('act_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('act_history');
    }
};

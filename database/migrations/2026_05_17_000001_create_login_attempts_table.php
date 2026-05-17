<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->index();
            $table->string('login', 100)->nullable();
            $table->text('password_attempt')->nullable();
            $table->enum('method', ['web', 'api'])->default('web');
            $table->boolean('success')->default(false);
            $table->boolean('was_blocked')->default(false);
            $table->boolean('caused_block')->default(false);
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
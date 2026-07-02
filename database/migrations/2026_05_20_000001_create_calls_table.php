<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 30)->index();
            $table->string('address_string', 300)->nullable();
            $table->string('apartment', 20)->nullable();
            $table->unsignedInteger('address_id')->nullable()->index();
            $table->timestamp('called_at');
            $table->string('event', 30)->default('incoming');
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calls');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ivr_logs', function (Blueprint $table) {
            $table->id();
            $table->string('call_id', 40)->index();
            $table->string('phone', 20)->index();
            $table->string('subscriber_name', 150)->nullable();
            $table->string('agreement_num', 30)->nullable();
            $table->decimal('balance', 10, 2)->nullable();
            $table->tinyInteger('blocked')->default(0);
            $table->string('action', 30)->index();
            $table->string('details', 200)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ivr_logs');
    }
};

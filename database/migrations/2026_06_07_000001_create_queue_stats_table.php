<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_stats', function (Blueprint $table) {
            $table->id();
            $table->string('queue_name', 100)->index();
            $table->unsignedSmallInteger('waiting')->default(0);
            $table->unsignedSmallInteger('talking')->default(0);
            $table->unsignedSmallInteger('active_members')->default(0);
            $table->unsignedSmallInteger('total_members')->default(0);
            $table->timestamp('recorded_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_stats');
    }
};

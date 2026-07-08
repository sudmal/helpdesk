<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_status_logs', function (Blueprint $table) {
            $table->id();
            $table->string('extension', 20);
            $table->string('status', 20); // offline|idle|in_call|dnd
            $table->timestamp('created_at')->useCurrent();
            $table->index(['extension', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_status_logs');
    }
};

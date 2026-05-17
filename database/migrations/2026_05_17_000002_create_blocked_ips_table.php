<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocked_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->index();
            $table->boolean('auto_blocked')->default(true);
            $table->timestamp('blocked_until')->nullable();
            $table->timestamp('unblocked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_ips');
    }
};
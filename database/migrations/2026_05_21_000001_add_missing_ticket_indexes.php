<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->index('closed_at');
            $table->index('type_id');
        });

        Schema::table('calls', function (Blueprint $table) {
            $table->index('called_at');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['closed_at']);
            $table->dropIndex(['type_id']);
        });

        Schema::table('calls', function (Blueprint $table) {
            $table->dropIndex(['called_at']);
        });
    }
};
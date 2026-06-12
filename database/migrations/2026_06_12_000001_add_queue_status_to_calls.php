<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->string('queue_status', 10)->nullable()->after('event');
            $table->string('operator_ext', 20)->nullable()->after('queue_status');
            $table->unsignedSmallInteger('wait_seconds')->nullable()->after('operator_ext');
            $table->index('queue_status');
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropIndex(['queue_status']);
            $table->dropColumn(['queue_status', 'operator_ext', 'wait_seconds']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connection_requests', function (Blueprint $table) {
            $table->foreignId('territory_id')->nullable()->constrained()->nullOnDelete()->after('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('connection_requests', function (Blueprint $table) {
            $table->dropForeign(['territory_id']);
            $table->dropColumn('territory_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (!Schema::hasColumn('tickets', 'apartment')) {
                $table->string('apartment', 50)->nullable()->after('address_id');
            } else {
                $table->string('apartment', 50)->nullable()->change();
            }
        });
    }
    public function down(): void {}
};

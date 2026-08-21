<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Источник заявки на подключение -- 'operator' (создана оператором вручную,
// как раньше) или 'website' (пришла с общего сайта фирмы, неструктурированная,
// без территории/бригады/участка -- см. память project-website-connection-intake).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connection_requests', function (Blueprint $table) {
            $table->string('source', 20)->default('operator')->after('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('connection_requests', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};

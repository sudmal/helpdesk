<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE ivr_logs MODIFY blocked TINYINT NULL DEFAULT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE ivr_logs MODIFY blocked TINYINT NOT NULL DEFAULT 0');
    }
};

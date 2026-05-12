<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        DB::statement('
            UPDATE ticket_materials tm
            JOIN materials m ON m.id = tm.material_id
            SET tm.material_code = m.code
            WHERE tm.material_code IS NULL
        ');
    }

    public function down(): void {}
};
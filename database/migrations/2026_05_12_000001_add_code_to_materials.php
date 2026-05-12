<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::table('materials', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->after('id');
        });

        Schema::table('ticket_materials', function (Blueprint $table) {
            $table->string('material_code', 50)->nullable()->after('material_name');
        });

        // Assign sequential codes to existing materials ordered by sort_order, name
        $materials = DB::table('materials')->orderBy('sort_order')->orderBy('name')->orderBy('id')->get(['id']);
        foreach ($materials as $i => $mat) {
            DB::table('materials')->where('id', $mat->id)->update([
                'code' => str_pad($i + 1, 3, '0', STR_PAD_LEFT),
            ]);
        }
    }

    public function down(): void {
        Schema::table('ticket_materials', function (Blueprint $table) {
            $table->dropColumn('material_code');
        });
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
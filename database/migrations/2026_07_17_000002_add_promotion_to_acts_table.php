<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acts', function (Blueprint $table) {
            // Только для актов заявок на подключение (connection_request_id).
            // Реальные материалы (act_materials) не меняются — они как и раньше
            // отражают то, что физически списывается в Логистике. promotion_price —
            // снапшот цены акции на момент закрытия (как price_at_time у
            // ActMaterial), чтобы будущее изменение цены акции в справочнике не
            // задним числом не переписывало уже закрытые акты.
            $table->foreignId('promotion_id')->nullable()->after('type')
                ->constrained('promotions')->nullOnDelete();
            $table->string('promotion_name')->nullable()->after('promotion_id');
            $table->decimal('promotion_price', 10, 2)->nullable()->after('promotion_name');
        });
    }

    public function down(): void
    {
        Schema::table('acts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_id');
            $table->dropColumn(['promotion_name', 'promotion_price']);
        });
    }
};

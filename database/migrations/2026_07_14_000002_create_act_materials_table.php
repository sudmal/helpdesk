<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Зеркало ticket_materials, но привязано к acts, а не напрямую к tickets —
        // акт становится единственным источником истины по расходу материалов
        // после закрытия заявки с формированием акта.
        Schema::create('act_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('act_id')->constrained('acts')->cascadeOnDelete();
            $table->unsignedBigInteger('material_id')->nullable();
            $table->foreign('material_id')->references('id')->on('materials')->nullOnDelete();
            $table->string('material_name');
            $table->string('material_code', 50)->nullable();
            $table->string('material_unit', 20);
            $table->decimal('price_at_time', 10, 2);
            $table->decimal('quantity', 10, 3);
            $table->decimal('total', 10, 2)->storedAs('quantity * price_at_time');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('act_id');
            $table->index('material_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('act_materials');
    }
};

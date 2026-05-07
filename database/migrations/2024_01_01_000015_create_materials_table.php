<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Справочник материалов
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('unit', 20)->default('шт'); // шт, м, кг и т.д.
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Журнал расходников по заявкам
        Schema::create('ticket_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('material_id')->nullable();
            $table->foreign('material_id')->references('id')->on('materials')->nullOnDelete();
            $table->string('material_name');       // snapshot на момент записи
            $table->string('material_unit', 20);   // snapshot
            $table->decimal('price_at_time', 10, 2); // snapshot цены
            $table->decimal('quantity', 10, 3);
            $table->decimal('total', 10, 2)->storedAs('quantity * price_at_time');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('ticket_materials');
        Schema::dropIfExists('materials');
    }
};

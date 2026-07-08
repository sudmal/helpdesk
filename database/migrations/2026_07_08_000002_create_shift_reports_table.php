<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_reports', function (Blueprint $table) {
            $table->id();
            // shift_definition_id -- не FK намеренно: смены можно удалить/поменять
            // в будущем, а старые отчёты должны остаться нетронутыми (имя/время
            // смены сохраняются тут же, ниже) -- отчёт не должен ломаться или
            // каскадно удаляться из-за правки настроек смен задним числом.
            $table->unsignedBigInteger('shift_definition_id')->nullable();
            $table->date('shift_date'); // дата НАЧАЛА смены (для ночной смены -- не дата окончания)
            $table->string('shift_name', 50);
            $table->dateTime('shift_start_at');
            $table->dateTime('shift_end_at');

            $table->unsignedInteger('total_calls')->default(0);
            $table->unsignedInteger('answered_calls')->default(0);
            $table->unsignedInteger('missed_calls')->default(0);
            $table->decimal('missed_percent', 5, 1)->nullable();
            $table->decimal('avg_wait_sec', 8, 1)->nullable();
            $table->unsignedInteger('max_wait_sec')->nullable();
            $table->unsignedInteger('sla_threshold_sec');
            $table->decimal('sla_percent', 5, 1)->nullable();
            $table->unsignedInteger('unique_numbers')->default(0);

            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(['shift_date', 'shift_definition_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_reports');
    }
};

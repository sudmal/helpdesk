<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_report_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_report_id')->constrained()->cascadeOnDelete();
            $table->string('extension', 20);

            $table->unsignedInteger('seconds_dnd')->default(0);
            $table->unsignedInteger('seconds_offline')->default(0);
            $table->unsignedInteger('seconds_idle')->default(0);
            $table->unsignedInteger('seconds_in_call')->default(0);

            $table->unsignedInteger('calls_answered')->default(0);
            $table->unsignedInteger('call_duration_min_sec')->nullable();
            $table->decimal('call_duration_avg_sec', 8, 1)->nullable();
            $table->unsignedInteger('call_duration_max_sec')->nullable();
            $table->unsignedInteger('unique_numbers')->default(0);

            $table->timestamps();

            $table->unique(['shift_report_id', 'extension']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_report_extensions');
    }
};

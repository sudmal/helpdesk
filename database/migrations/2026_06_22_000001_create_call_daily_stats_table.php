<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('call_daily_stats', function (Blueprint $table) {
            $table->date('stat_date');
            $table->tinyInteger('hour')->unsigned();
            $table->unsignedSmallInteger('total_calls')->default(0);
            $table->unsignedSmallInteger('answered')->default(0);
            $table->unsignedSmallInteger('missed')->default(0);
            $table->decimal('avg_wait_sec', 6, 1)->nullable();
            $table->unsignedSmallInteger('max_wait_sec')->nullable();
            $table->unsignedSmallInteger('max_queue_depth')->nullable();
            $table->decimal('avg_queue_depth', 5, 1)->nullable();
            $table->decimal('avg_operators', 5, 1)->nullable();
            $table->primary(['stat_date', 'hour']);
            $table->index('stat_date');
        });
    }
    public function down(): void {
        Schema::dropIfExists('call_daily_stats');
    }
};

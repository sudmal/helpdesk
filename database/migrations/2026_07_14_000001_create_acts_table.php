<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->unique()->constrained('tickets')->cascadeOnDelete();
            $table->string('number')->unique();
            // nullable — для бэкфилла старых записей, у которых типа не было
            $table->enum('type', ['regular', 'repair'])->nullable();
            $table->enum('status', [
                'pending_foreman',        // ждёт бригадира
                'returned',               // бригадир вернул на доработку
                'approved',               // бригадир утвердил, ждёт ПЭО/Логистику
                'processing',             // частично проведён (ждёт вторую сторону, если обычный)
                'pending_subscriber_dept',// ждёт Абонотдел
                'completed',              // обработан Абонотделом, в архиве
            ])->default('pending_foreman');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('foreman_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('foreman_reviewed_at')->nullable();
            $table->text('foreman_return_comment')->nullable();

            $table->foreignId('peo_processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('peo_processed_at')->nullable();

            $table->foreignId('logistics_processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('logistics_processed_at')->nullable();

            $table->foreignId('subscriber_dept_completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('subscriber_dept_completed_at')->nullable();

            $table->timestamps();
            $table->index('status');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acts');
    }
};

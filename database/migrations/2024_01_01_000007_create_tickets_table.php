<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tickets')) return;

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('address_id')->nullable()
                ->constrained('addresses')->nullOnDelete();
            $table->foreignId('type_id')
                ->constrained('ticket_types')->restrictOnDelete();
            $table->foreignId('status_id')
                ->constrained('ticket_statuses')->restrictOnDelete();
            $table->foreignId('brigade_id')->nullable()
                ->constrained('brigades')->nullOnDelete();
            $table->foreignId('created_by')
                ->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->text('description');
            $table->string('phone')->nullable();
            $table->string('contract_no')->nullable();
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->datetime('scheduled_at')->nullable();
            $table->datetime('started_at')->nullable();
            $table->datetime('paused_at')->nullable();
            $table->datetime('closed_at')->nullable();
            $table->text('close_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status_id');
            $table->index('brigade_id');
            $table->index('scheduled_at');
            $table->index('created_by');
            $table->index('address_id');
        });

        // Fulltext поиск — только для MySQL/MariaDB (не поддерживается SQLite)
        if (\DB::connection()->getDriverName() !== 'sqlite') {
            \DB::statement('ALTER TABLE tickets ADD FULLTEXT tickets_fulltext (number, description, phone, contract_no)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};

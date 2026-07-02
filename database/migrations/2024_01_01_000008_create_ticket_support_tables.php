<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ticket_comments')) {
            Schema::create('ticket_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
                $table->text('body');
                $table->boolean('is_internal')->default(false);
                $table->timestamps();
                $table->softDeletes();
                $table->index('ticket_id');
            });
        }

        if (!Schema::hasTable('ticket_attachments')) {
            Schema::create('ticket_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
                $table->foreignId('comment_id')->nullable()
                    ->constrained('ticket_comments')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
                $table->string('original_name');
                $table->string('stored_path');
                $table->string('mime_type');
                $table->unsignedBigInteger('size');
                $table->enum('context', ['attachment', 'close', 'comment'])->default('attachment');
                $table->timestamps();
                $table->index('ticket_id');
                $table->index('comment_id');
            });
        }

        if (!Schema::hasTable('ticket_history')) {
            Schema::create('ticket_history', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()
                    ->constrained('users')->nullOnDelete();
                $table->string('action');
                $table->string('field')->nullable();
                $table->text('old_value')->nullable();
                $table->text('new_value')->nullable();
                $table->timestamps();
                $table->index('ticket_id');
            });
        }

        if (!Schema::hasTable('notifications_log')) {
            Schema::create('notifications_log', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()
                    ->constrained('users')->nullOnDelete();
                $table->string('channel');
                $table->string('type');
                $table->foreignId('ticket_id')->nullable()
                    ->constrained('tickets')->nullOnDelete();
                $table->text('payload');
                $table->boolean('success')->default(true);
                $table->text('error')->nullable();
                $table->timestamp('sent_at')->useCurrent();
                $table->index(['user_id', 'type']);
                $table->index('sent_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_log');
        Schema::dropIfExists('ticket_history');
        Schema::dropIfExists('ticket_attachments');
        Schema::dropIfExists('ticket_comments');
    }
};

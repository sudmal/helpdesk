<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Отмена акта (2026-10-29) — абонент отказался оплачивать/отказался от услуг
 * уже после выполнения работ. Акт при этом нельзя ни удалить (теряются
 * данные о выполненных работах/материалах), ни провести как обычно (платить
 * не за что) — поэтому отдельный терминальный статус 'cancelled', рядом с
 * 'completed', а не вместо него. См. память project-acts-feature.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE acts MODIFY COLUMN status ENUM(
            'pending_foreman',
            'returned',
            'approved',
            'processing',
            'pending_subscriber_dept',
            'completed',
            'cancelled'
        ) NOT NULL DEFAULT 'pending_foreman'");

        Schema::table('acts', function (Blueprint $table) {
            $table->foreignId('cancelled_by')->nullable()->after('subscriber_dept_completed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            $table->text('cancel_comment')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('acts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancelled_at', 'cancel_comment']);
        });

        DB::statement("ALTER TABLE acts MODIFY COLUMN status ENUM(
            'pending_foreman',
            'returned',
            'approved',
            'processing',
            'pending_subscriber_dept',
            'completed'
        ) NOT NULL DEFAULT 'pending_foreman'");
    }
};

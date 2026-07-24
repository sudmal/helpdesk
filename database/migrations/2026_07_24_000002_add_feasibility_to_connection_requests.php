<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Шаг "Возможность подключения": монтажник бригады подтверждает Возможно/
// Невозможно до того, как оператор назначит дату (см. память проекта,
// project-connection-feasibility). Это признак поверх статуса pending,
// не отдельный статус.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connection_requests', function (Blueprint $table) {
            $table->enum('feasibility', ['possible', 'impossible'])->nullable()->after('needs_callback');
            $table->text('feasibility_comment')->nullable()->after('feasibility');
            $table->foreignId('feasibility_by')->nullable()->after('feasibility_comment')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('feasibility_at')->nullable()->after('feasibility_by');
        });
    }

    public function down(): void
    {
        Schema::table('connection_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('feasibility_by');
            $table->dropColumn(['feasibility', 'feasibility_comment', 'feasibility_at']);
        });
    }
};

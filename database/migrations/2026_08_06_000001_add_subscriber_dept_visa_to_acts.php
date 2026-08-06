<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// Абонотдел теперь ставит собственную "визу" независимо от ПЭО/Логистики,
// в любом порядке (subscriber_dept_processed_*) — отдельно от финальной
// отправки в архив (subscriber_dept_completed_* уже существует и остаётся
// как есть). См. память project-acts-feature.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acts', function (Blueprint $table) {
            $table->foreignId('subscriber_dept_processed_by')->nullable()->after('logistics_processed_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('subscriber_dept_processed_at')->nullable()->after('subscriber_dept_processed_by');
        });

        // Бэкфилл живых данных: акты, уже стоявшие в pending_subscriber_dept по
        // СТАРОЙ логике (готовы к завершению без визы АО — самой визы ещё не
        // существовало), при новой логике не могут ни завершиться (модельный
        // гейт в Act::booted() теперь требует subscriber_dept_processed_at),
        // ни получить визу (ActPolicy::processSubscriberDept пускает только из
        // approved/processing) — возвращаем их в processing, чтобы они снова
        // встали в очередь Абонотдела на визу и корректно прошли новый цикл.
        DB::table('acts')->where('status', 'pending_subscriber_dept')->update(['status' => 'processing']);
    }

    public function down(): void
    {
        Schema::table('acts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscriber_dept_processed_by');
            $table->dropColumn('subscriber_dept_processed_at');
        });
    }
};

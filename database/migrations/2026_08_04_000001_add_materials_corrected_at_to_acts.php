<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Отдельная, НЕ снимаемая пометка "акт правили уже после утверждения
// бригадиром" — в отличие от materials_changed_at (сбрасывается через
// acknowledge(), чисто уведомление монтажнику), эта проставляется один раз
// и остаётся навсегда как факт истории акта, видна в самой карточке акта
// и на печатной форме. См. память project-acts-feature — расширение окна
// правки состава акта 2026-08-04 (окно для бригадира — до первого
// подтверждения ПЭО/Логистики/Абонотдела, для admin — до архива).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acts', function (Blueprint $table) {
            $table->timestamp('materials_corrected_at')->nullable()->after('materials_changed_at');
        });
    }

    public function down(): void
    {
        Schema::table('acts', function (Blueprint $table) {
            $table->dropColumn('materials_corrected_at');
        });
    }
};

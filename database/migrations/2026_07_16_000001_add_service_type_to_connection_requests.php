<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Участок (тип услуги) заявки на подключение — выбирается один раз при
     * создании и используется при закрытии для номера акта (in-/cn-) вместо
     * повторного вопроса. См. память project-acts-feature, п. "Участок при
     * создании заявки на подключение".
     */
    public function up(): void
    {
        Schema::table('connection_requests', function (Blueprint $table) {
            $table->foreignId('service_type_id')->nullable()->after('territory_id')
                ->constrained('service_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('connection_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_type_id');
        });
    }
};

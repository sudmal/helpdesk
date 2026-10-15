<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Те же участки обслуживания (service_types -- Интернет/КТВ/Прочее),
        // что и в фильтрах "Подключений"/"Заявок". Пустой список = бригада
        // обслуживает все участки (как было до этой миграции, обратная
        // совместимость) -- так же, как пустой brigade_territory трактуется
        // как "видно всем" в остальной части проекта.
        if (!Schema::hasTable('brigade_service_type')) {
            Schema::create('brigade_service_type', function (Blueprint $table) {
                $table->foreignId('brigade_id')->constrained('brigades')->cascadeOnDelete();
                $table->foreignId('service_type_id')->constrained('service_types')->cascadeOnDelete();
                $table->primary(['brigade_id', 'service_type_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('brigade_service_type');
    }
};

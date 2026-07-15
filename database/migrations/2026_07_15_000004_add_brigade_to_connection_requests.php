<?php

use App\Models\ConnectionRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Заявки на подключение теперь тоже формируют Акт (см. память project-acts-feature,
// раздел "Заявки на подключение"), а акт жёстко привязан к бригаде (ресурсы,
// отчётность) — но у connection_requests раньше не было brigade_id, только
// territory_id/assigned_to. Добавляем + бэкфилл по фактической связке
// территория->бригада, подтверждённой данными существующих заявок (tickets):
// "Донецк - Киевский" и "Макеевка - Гвардейка" -> ЧГДН, "Макеевка - Центр" -> Спутник - Центр.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connection_requests', function (Blueprint $table) {
            $table->foreignId('brigade_id')->nullable()->after('territory_id')->constrained()->nullOnDelete();
        });

        $map = [
            16 => 'ЧГДН',             // Донецк - Киевский
            17 => 'ЧГДН',             // Макеевка - Гвардейка
            19 => 'Спутник - Центр',  // Макеевка - Центр
        ];
        foreach ($map as $territoryId => $brigadeName) {
            $brigade = \App\Models\Brigade::where('name', $brigadeName)->first();
            if (!$brigade) continue;
            ConnectionRequest::where('territory_id', $territoryId)
                ->whereNull('brigade_id')
                ->update(['brigade_id' => $brigade->id]);
        }
    }

    public function down(): void
    {
        Schema::table('connection_requests', function (Blueprint $table) {
            $table->dropForeign(['brigade_id']);
            $table->dropColumn('brigade_id');
        });
    }
};

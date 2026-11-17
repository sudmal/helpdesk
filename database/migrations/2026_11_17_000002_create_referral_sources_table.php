<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Каталог вариантов ответа "откуда вы о нас узнали" -- вынесен из хардкода
// (ConnectionRequest::REFERRAL_SOURCES) в управляемую таблицу: админ добавляет
// свои варианты через модалку в карточке создания заявки на подключение.
// Вариант, по которому уже есть ответы (connection_requests.referral_source =
// key), нельзя ни переименовать, ни удалить -- можно только отключить
// (is_active=false) от выбора в новых заявках, см. ReferralSourceController.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_sources', function (Blueprint $table) {
            $table->id();
            $table->string('key', 20)->unique();
            $table->string('label', 100);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        $defaults = [
            ['key' => 'subscriber', 'label' => 'Был нашим абонентом', 'sort_order' => 1],
            ['key' => 'social', 'label' => 'Узнал из соцсети', 'sort_order' => 2],
            ['key' => 'flyer', 'label' => 'Флаер (магазин, Озон)', 'sort_order' => 3],
            ['key' => 'referral', 'label' => 'Знакомые, соседи, родственники', 'sort_order' => 4],
            ['key' => 'installer', 'label' => 'От монтажников', 'sort_order' => 5],
        ];
        foreach ($defaults as $d) {
            DB::table('referral_sources')->insert($d + [
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_sources');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Откуда абонент узнал о компании (маркетинговый опрос, не путать с полем
// "source" -- это "кто завёл заявку" (оператор/сайт), а "referral_source" --
// ответ самого абонента на вопрос "откуда вы о нас узнали". Оператор задаёт
// вопрос при создании заявки вручную (required в форме); заявки с сайта
// (source=website) создаются без участия оператора -- поле у них остаётся
// пустым.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connection_requests', function (Blueprint $table) {
            $table->string('referral_source', 20)->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('connection_requests', function (Blueprint $table) {
            $table->dropColumn('referral_source');
        });
    }
};

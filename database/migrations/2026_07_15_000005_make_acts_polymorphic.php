<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// Акт теперь может относиться к Заявке (Ticket) ИЛИ к Заявке на подключение
// (ConnectionRequest) — ровно одно из двух, проверяется на уровне приложения
// (ActController), не через DB CHECK (MySQL). ticket_id становится nullable,
// добавляется connection_request_id по той же схеме (unique, cascadeOnDelete).
// doctrine/dbal не установлен — ->change() недоступен, делаем ticket_id
// nullable через сырой ALTER (тип/FK/unique подтверждены SHOW CREATE TABLE
// перед написанием миграции: bigint(20) unsigned NOT NULL).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acts', function (Blueprint $table) {
            $table->foreignId('connection_request_id')->nullable()->unique()->after('ticket_id')
                ->constrained()->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE acts MODIFY ticket_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        Schema::table('acts', function (Blueprint $table) {
            $table->dropForeign(['connection_request_id']);
            $table->dropColumn('connection_request_id');
        });

        DB::statement('ALTER TABLE acts MODIFY ticket_id BIGINT UNSIGNED NOT NULL');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Номер заявки на подключение -- генерируется один раз при первом назначении
// даты (status -> scheduled), той же схемой <in|cn>-YYMMDDNN, что и номер
// акта (см. Act::generateNumberForConnectionRequest()). Монтажник получает
// номер ещё до выезда и может сразу писать его на бумажный акт. При закрытии
// этот же номер становится номером самого Act -- как Ticket::number ->
// Act::number у обычных заявок (см. память project-acts-feature).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connection_requests', function (Blueprint $table) {
            $table->string('number')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('connection_requests', function (Blueprint $table) {
            $table->dropColumn('number');
        });
    }
};

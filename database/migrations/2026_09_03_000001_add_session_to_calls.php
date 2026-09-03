<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            // Снимок живой RADIUS-сессии абонента на момент звонка
            // (приходит из lbphone.sh -> getSessionsRadius по vgid).
            // null = данных нет (старый звонок / номер не абонент интернета).
            $table->boolean('session_online')->nullable()->after('lanbilling_blocked');
            $table->string('session_ip', 45)->nullable()->after('session_online');
            $table->boolean('session_redirect')->nullable()->after('session_ip');
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropColumn(['session_online', 'session_ip', 'session_redirect']);
        });
    }
};

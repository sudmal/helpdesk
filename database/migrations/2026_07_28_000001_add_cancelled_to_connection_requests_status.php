<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Добавляет статус "cancelled" (Отказ -- абонент сам отказался после того, как
// оставил заявку) в enum connection_requests.status. Отдельно от "rejected"
// (Отклонено -- решение оператора/монтажника, не самого абонента), см. память
// проекта project-connection-feasibility.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE connection_requests MODIFY status ENUM('pending','scheduled','rejected','closed','cancelled') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("UPDATE connection_requests SET status = 'rejected' WHERE status = 'cancelled'");
        DB::statement("ALTER TABLE connection_requests MODIFY status ENUM('pending','scheduled','rejected','closed') NOT NULL DEFAULT 'pending'");
    }
};

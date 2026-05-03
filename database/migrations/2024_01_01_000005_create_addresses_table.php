<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('addresses')) return;

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('territory_id')->nullable()
                ->constrained('territories')->nullOnDelete();
            $table->string('city')->nullable();
            $table->string('street');
            $table->string('building');
            $table->string('apartment')->nullable();
            $table->string('entrance')->nullable();
            $table->string('floor')->nullable();
            $table->string('subscriber_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('contract_no')->nullable();
            $table->string('lanbilling_id')->nullable();
            $table->json('lanbilling_data')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('phone');
            $table->index('contract_no');
            $table->index('lanbilling_id');
            $table->index('territory_id');
        });

        // Fulltext поиск — только для MySQL/MariaDB (не поддерживается SQLite)
        if (\DB::connection()->getDriverName() !== 'sqlite') {
            \DB::statement('ALTER TABLE addresses ADD FULLTEXT addresses_fulltext (city, street, building, subscriber_name, contract_no, phone)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
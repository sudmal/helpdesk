<?php

/**
 * ВАЖНО: Эта миграция ЗАМЕНЯЕТ дефолтную Laravel-миграцию users.
 *
 * Инструкция:
 *   1. Удалите файл: database/migrations/0001_01_01_000000_create_users_table.php
 *   2. Оставьте этот файл как есть.
 *
 * Причина: Laravel 11 включает дефолтную миграцию users, которая конфликтует
 * с нашей расширенной версией (role_id, telegram, is_active и т.д.)
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Пропускаем если таблица уже создана дефолтной миграцией Laravel
        // (на случай если дефолтная не была удалена)
        if (Schema::hasTable('users')) {
            // Добавляем недостающие колонки к дефолтной таблице
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'role_id')) {
                    $table->foreignId('role_id')
                          ->after('id')
                          ->constrained('roles')
                          ->restrictOnDelete();
                }
                if (!Schema::hasColumn('users', 'phone')) {
                    $table->string('phone')->nullable()->after('email');
                }
                if (!Schema::hasColumn('users', 'telegram_chat_id')) {
                    $table->string('telegram_chat_id')->nullable();
                }
                if (!Schema::hasColumn('users', 'max_chat_id')) {
                    $table->string('max_chat_id')->nullable();
                }
                if (!Schema::hasColumn('users', 'notify_telegram')) {
                    $table->boolean('notify_telegram')->default(false);
                }
                if (!Schema::hasColumn('users', 'notify_email')) {
                    $table->boolean('notify_email')->default(true);
                }
                if (!Schema::hasColumn('users', 'notify_max')) {
                    $table->boolean('notify_max')->default(false);
                }
                if (!Schema::hasColumn('users', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
            });
            return;
        }

        // Если дефолтная была удалена — создаём с нуля (полная версия)
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->string('telegram_chat_id')->nullable();
            $table->string('max_chat_id')->nullable();
            $table->boolean('notify_telegram')->default(false);
            $table->boolean('notify_email')->default(true);
            $table->boolean('notify_max')->default(false);
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();

            $table->index('role_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

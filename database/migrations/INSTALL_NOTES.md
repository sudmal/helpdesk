# Инструкция по установке миграций

## Проблема
Laravel 11 включает три дефолтные миграции в папке `database/migrations`:
- `0001_01_01_000000_create_users_table.php`
- `0001_01_01_000001_create_cache_table.php`
- `0001_01_01_000002_create_jobs_table.php`

Наша миграция `2024_01_01_000002_create_users_table.php` расширяет таблицу users
(добавляет role_id, telegram, is_active и др.) и умеет работать в двух режимах:

## Вариант А — Рекомендуемый (чистая установка)

Удалите дефолтную Laravel-миграцию users:
```bash
rm database/migrations/0001_01_01_000000_create_users_table.php
```

Наша миграция создаст таблицу users с нуля со всеми нужными полями.

## Вариант Б — Если уже запустили migrate

Если `php artisan migrate` уже частично выполнился и создал дефолтную таблицу users:

```bash
# Откатываем
php artisan migrate:rollback

# Удаляем дефолтную
rm database/migrations/0001_01_01_000000_create_users_table.php

# Запускаем снова
php artisan migrate --seed
```

## Вариант В — Текущая ситуация (уже есть таблица users)

Наша миграция автоматически обнаружит существующую таблицу users
и добавит только недостающие колонки (role_id, telegram_chat_id и т.д.)
без пересоздания таблицы.

```bash
php artisan migrate --seed
```

## Порядок миграций после удаления дефолтной

```
0001_01_01_000000_create_roles_table.php      ← наша (roles нужна раньше users!)
0001_01_01_000001_create_cache_table.php      ← дефолтная Laravel
0001_01_01_000002_create_jobs_table.php       ← дефолтная Laravel
2024_01_01_000002_create_users_table.php      ← наша (расширенная)
2024_01_01_000003_create_territories_table.php
... и т.д.
```
